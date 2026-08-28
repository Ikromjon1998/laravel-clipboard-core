<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Contracts;

/**
 * Records writes this application made to the pasteboard, so the watcher can
 * ignore its own echo.
 *
 * This has to cross a process boundary. The watcher polls in its own OS
 * process, while pastes are triggered from wherever the UI runs, so an
 * in-memory list would be written in one process and read in another —
 * which is to say, never read at all.
 *
 * Entries expire: a suppression that is never consumed must not silently
 * swallow a genuine copy of the same text minutes later.
 */
interface SuppressionLog
{
    public function suppress(string $fingerprint): void;

    /**
     * Consume a suppression if one is pending.
     *
     * @return bool True when this fingerprint was our own write.
     */
    public function consume(string $fingerprint): bool;
}
