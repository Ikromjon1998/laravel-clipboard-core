<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A guard refused a snapshot.
 *
 * Carries no content — the whole point is that rejected material leaves no
 * trace. Only the byte size is reported, so hosts can surface "ignored a
 * large clip" without ever handling the clip itself.
 */
class ClipRejected
{
    use Dispatchable;

    public function __construct(public readonly int $bytes) {}
}
