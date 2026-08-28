<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore;

use Ikromjon\ClipboardCore\Contracts\ClipRepository;
use Ikromjon\ClipboardCore\Contracts\PasteStrategy;
use Ikromjon\ClipboardCore\Contracts\PauseSwitch;
use Ikromjon\ClipboardCore\Events\WatcherPaused;
use Ikromjon\ClipboardCore\Events\WatcherResumed;
use Ikromjon\ClipboardCore\Models\Clip;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;

/**
 * The package's front door.
 *
 * Everything a host application needs in one object: read the history,
 * pin and delete, hand a clip back to the pasteboard, and pause capture.
 * Reach past it to the contracts when you want to replace behaviour.
 */
class ClipboardHistory
{
    public function __construct(
        private readonly ClipRepository $clips,
        private readonly PasteStrategy $paste,
        private readonly PauseSwitch $pause,
        private readonly Dispatcher $events,
        private readonly int $limit,
    ) {}

    /** @return Collection<int, Clip> */
    public function recent(?int $limit = null): Collection
    {
        return $this->clips->recent($limit ?? $this->limit);
    }

    /** @return Collection<int, Clip> */
    public function search(string $term, ?int $limit = null): Collection
    {
        return $this->clips->search($term, $limit ?? $this->limit);
    }

    public function find(int $id): ?Clip
    {
        return $this->clips->find($id);
    }

    /**
     * Hand a clip back to the system, using whichever paste strategy is bound.
     */
    public function use(Clip|int $clip): ?Clip
    {
        $clip = $clip instanceof Clip ? $clip : $this->clips->find($clip);

        if (! $clip instanceof Clip) {
            return null;
        }

        $this->paste->apply($clip);

        return $clip;
    }

    public function pin(int $id, bool $pinned = true): ?Clip
    {
        return $this->clips->pin($id, $pinned);
    }

    public function forget(int $id): bool
    {
        return $this->clips->forget($id);
    }

    public function clear(bool $includePinned = false): int
    {
        return $this->clips->clear($includePinned);
    }

    public function count(bool $includePinned = true): int
    {
        return $this->clips->count($includePinned);
    }

    public function isPaused(): bool
    {
        return $this->pause->isPaused();
    }

    public function pause(): void
    {
        if ($this->pause->isPaused()) {
            return;
        }

        $this->pause->pause();
        $this->events->dispatch(new WatcherPaused);
    }

    public function resume(): void
    {
        if (! $this->pause->isPaused()) {
            return;
        }

        $this->pause->resume();
        $this->events->dispatch(new WatcherResumed);
    }

    public function toggle(): bool
    {
        $this->isPaused() ? $this->resume() : $this->pause();

        return $this->isPaused();
    }
}
