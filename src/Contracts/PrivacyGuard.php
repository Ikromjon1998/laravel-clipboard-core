<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Contracts;

use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;

/**
 * Decides whether a snapshot is allowed to be recorded at all.
 *
 * Guards run before anything touches the database, so a rejected snapshot
 * leaves no trace on disk. Bind your own to exclude specific apps, patterns
 * that look like secrets, or anything else your users should never see logged.
 */
interface PrivacyGuard
{
    public function allows(ClipboardSnapshot $snapshot): bool;
}
