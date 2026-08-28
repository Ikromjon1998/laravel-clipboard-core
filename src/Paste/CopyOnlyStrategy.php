<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Paste;

use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Contracts\PasteStrategy;
use Ikromjon\ClipboardCore\Contracts\SuppressionLog;
use Ikromjon\ClipboardCore\Models\Clip;
use Ikromjon\ClipboardCore\Support\Fingerprint;

/**
 * Put the clip on the pasteboard and stop there.
 *
 * The default because it needs no permissions and works in every
 * distribution channel, sandboxed or not. Synthesising a paste keystroke
 * is a separate strategy with an Accessibility prompt attached.
 */
class CopyOnlyStrategy implements PasteStrategy
{
    public function __construct(
        private readonly ClipboardSource $source,
        private readonly SuppressionLog $suppressions,
        private readonly Fingerprint $fingerprint,
    ) {}

    public function apply(Clip $clip): void
    {
        $content = $this->fingerprint->normalise($clip->content);

        // Record before writing: the watcher may poll between these two
        // statements, and it runs in a different process.
        $this->suppressions->suppress($this->fingerprint->of($content));

        $this->source->write($clip->content);
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
