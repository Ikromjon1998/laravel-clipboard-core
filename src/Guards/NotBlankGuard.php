<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Guards;

use Ikromjon\ClipboardCore\Contracts\PrivacyGuard;
use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;

/**
 * Empty and whitespace-only clips are noise, not history.
 */
class NotBlankGuard implements PrivacyGuard
{
    public function allows(ClipboardSnapshot $snapshot): bool
    {
        return ! $snapshot->isEmpty();
    }
}
