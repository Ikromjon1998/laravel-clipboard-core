<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Contracts;

use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;

/**
 * The system pasteboard, abstracted.
 *
 * Implementations exist for NativePHP (the real pasteboard) and for tests
 * (an in-memory array). Keeping this behind a contract is what lets the
 * entire engine be tested on Linux CI with no Electron and no desktop.
 */
interface ClipboardSource
{
    /**
     * Read the current pasteboard contents.
     *
     * Implementations must not throw when the pasteboard is empty or
     * unreadable; they return an empty snapshot instead.
     */
    public function read(): ClipboardSnapshot;

    /**
     * Replace the pasteboard contents with the given text.
     */
    public function write(string $text): void;
}
