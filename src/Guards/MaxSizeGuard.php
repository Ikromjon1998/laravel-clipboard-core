<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Guards;

use Ikromjon\ClipboardCore\Contracts\PrivacyGuard;
use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;

/**
 * Refuses clips past a byte ceiling.
 *
 * Copying a large file's contents should not silently turn a microscopic
 * history database into a large one, and nobody searches their history for
 * a megabyte of minified JavaScript.
 */
class MaxSizeGuard implements PrivacyGuard
{
    public function __construct(private readonly int $maxBytes) {}

    public function allows(ClipboardSnapshot $snapshot): bool
    {
        return $snapshot->bytes() <= $this->maxBytes;
    }
}
