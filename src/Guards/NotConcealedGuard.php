<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Guards;

use Ikromjon\ClipboardCore\Contracts\PrivacyGuard;
use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;

/**
 * Honours the convention password managers use to say "do not log this".
 *
 * Only as good as the snapshot it is given: sources that cannot read custom
 * pasteboard types always report concealed = false, so this guard passes
 * everything through. That is a limitation of the source, not of the rule,
 * and it is why the README is explicit about which sources can enforce it.
 *
 * @see https://nspasteboard.org
 */
class NotConcealedGuard implements PrivacyGuard
{
    public function allows(ClipboardSnapshot $snapshot): bool
    {
        return ! $snapshot->concealed;
    }
}
