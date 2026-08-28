<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Contracts;

use Ikromjon\ClipboardCore\Models\Clip;

/**
 * What happens when a user picks a clip.
 *
 * Putting text on the pasteboard needs no permissions; typing it into the
 * frontmost app needs Accessibility access and is unavailable to sandboxed
 * builds. That difference is a strategy, not a feature flag, so hosts can
 * bind whichever one their distribution channel allows.
 */
interface PasteStrategy
{
    public function apply(Clip $clip): void;

    /**
     * Whether this strategy can run right now — used to fall back rather
     * than fail silently when a permission has not been granted.
     */
    public function isAvailable(): bool;
}
