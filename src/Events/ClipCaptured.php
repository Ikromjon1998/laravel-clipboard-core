<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Events;

use Ikromjon\ClipboardCore\Models\Clip;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new clip was recorded, or an existing one resurfaced.
 *
 * Deliberately a plain event: broadcasting it to a desktop window is a
 * host concern, so hosts listen for this and re-emit whatever their
 * transport needs. Keeping the transport out of core is what lets this
 * package run in a plain Laravel app with no desktop runtime at all.
 */
class ClipCaptured
{
    use Dispatchable;

    public function __construct(
        public readonly Clip $clip,
        public readonly bool $isNew,
    ) {}
}
