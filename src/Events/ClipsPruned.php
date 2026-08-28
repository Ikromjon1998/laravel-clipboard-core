<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ClipsPruned
{
    use Dispatchable;

    public function __construct(
        public readonly int $removed,
        public readonly int $limit,
    ) {}
}
