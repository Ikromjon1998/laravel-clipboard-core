<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Guards;

use Ikromjon\ClipboardCore\Contracts\PrivacyGuard;
use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;

/**
 * Every guard must allow a snapshot for it to be recorded.
 *
 * Unanimous consent rather than majority: when the subject is what gets
 * written to disk, one objection is enough.
 */
class CompositeGuard implements PrivacyGuard
{
    /** @var list<PrivacyGuard> */
    private array $guards;

    public function __construct(PrivacyGuard ...$guards)
    {
        $this->guards = array_values($guards);
    }

    public function allows(ClipboardSnapshot $snapshot): bool
    {
        foreach ($this->guards as $guard) {
            if (! $guard->allows($snapshot)) {
                return false;
            }
        }

        return true;
    }

    public function with(PrivacyGuard $guard): self
    {
        return new self(...[...$this->guards, $guard]);
    }
}
