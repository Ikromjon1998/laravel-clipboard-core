<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Watcher;

use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Contracts\ClipRepository;
use Ikromjon\ClipboardCore\Contracts\PrivacyGuard;
use Ikromjon\ClipboardCore\Contracts\SuppressionLog;
use Ikromjon\ClipboardCore\Enums\ClipKind;
use Ikromjon\ClipboardCore\Events\ClipCaptured;
use Ikromjon\ClipboardCore\Events\ClipRejected;
use Ikromjon\ClipboardCore\Events\ClipsPruned;
use Ikromjon\ClipboardCore\Models\Clip;
use Ikromjon\ClipboardCore\Support\Fingerprint;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The engine: one poll of the pasteboard, and what follows from it.
 *
 * Exposed as a single `tick()` rather than an internal loop so the caller
 * owns the sleeping. That is what makes the whole engine testable — a test
 * calls tick() directly and never waits.
 */
class ClipboardWatcher
{
    private ?string $lastFingerprint = null;

    public function __construct(
        private readonly ClipboardSource $source,
        private readonly ClipRepository $clips,
        private readonly PrivacyGuard $guard,
        private readonly Fingerprint $fingerprint,
        private readonly Dispatcher $events,
        private readonly Cadence $cadence,
        private readonly SuppressionLog $suppressions,
        private readonly int $limit = 100,
    ) {}

    /**
     * Poll once.
     *
     * @return Clip|null The captured clip, or null when nothing changed,
     *                   the content was refused, or it was our own write.
     */
    public function tick(): ?Clip
    {
        $snapshot = $this->source->read();

        if ($snapshot->text === null) {
            return null;
        }

        $content = $this->fingerprint->normalise($snapshot->text);
        $fingerprint = $this->fingerprint->of($content);

        // The overwhelmingly common case: nothing changed since last poll.
        // Everything above this line is one read and one hash.
        if ($fingerprint === $this->lastFingerprint) {
            return null;
        }

        $this->lastFingerprint = $fingerprint;

        // Our own paste, echoed back by the pasteboard. Recorded by whichever
        // process performed the paste, consumed here.
        if ($this->suppressions->consume($fingerprint)) {
            return null;
        }

        if (! $this->guard->allows($snapshot)) {
            $this->events->dispatch(new ClipRejected($snapshot->bytes()));

            return null;
        }

        $clip = $this->clips->record(
            $content,
            $fingerprint,
            ClipKind::classify($content)->value,
        );

        $this->cadence->noteChange();

        // A first copy is the only way to reach a count of one, so this
        // distinguishes a brand-new clip from one that resurfaced without
        // costing an extra query.
        $this->events->dispatch(new ClipCaptured($clip, isNew: $clip->times_copied === 1));

        $removed = $this->clips->prune($this->limit);

        if ($removed > 0) {
            $this->events->dispatch(new ClipsPruned($removed, $this->limit));
        }

        return $clip;
    }

    /**
     * Announce that this app is about to put text on the pasteboard, so the
     * resulting change is ignored exactly once — including by a watcher
     * running in a different process.
     */
    public function suppress(string $content): void
    {
        $this->suppressions->suppress(
            $this->fingerprint->of($this->fingerprint->normalise($content)),
        );
    }

    public function cadence(): Cadence
    {
        return $this->cadence;
    }

    /**
     * Accept whatever is currently on the pasteboard as already-seen,
     * without recording it.
     *
     * This is what "resume" must do: anything copied while paused was
     * deliberately not captured, and resuming should not retroactively
     * capture it — but it must not look like "no change" forever either.
     */
    public function resync(): void
    {
        $snapshot = $this->source->read();

        $this->lastFingerprint = $snapshot->text === null
            ? null
            : $this->fingerprint->of($this->fingerprint->normalise($snapshot->text));
    }

    /**
     * Reset change detection, so the next tick treats whatever is on the
     * pasteboard as new.
     */
    public function forgetLastSeen(): void
    {
        $this->lastFingerprint = null;
    }
}
