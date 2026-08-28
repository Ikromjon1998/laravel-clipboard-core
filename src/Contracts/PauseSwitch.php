<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Contracts;

/**
 * Cross-process pause state.
 *
 * The watcher runs in its own OS process, so "pause watching" in a menu has
 * to reach it somehow. This is deliberately the smallest abstraction that
 * does that without requiring a cache driver, queue, or socket.
 */
interface PauseSwitch
{
    public function isPaused(): bool;

    public function pause(): void;

    public function resume(): void;
}
