<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Sources;

use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;
use Native\Desktop\Facades\Clipboard;
use Throwable;

/**
 * Reads the real macOS pasteboard through NativePHP.
 *
 * Every call crosses a loopback HTTP boundary into the Electron process, so
 * a read can fail transiently while the app is starting or shutting down.
 * Those failures are normal and must not kill a long-running watcher, hence
 * the empty snapshot rather than an exception.
 *
 * Electron's clipboard API exposes no custom pasteboard types, so this source
 * cannot see the concealed/transient markers password managers set. A native
 * helper is required for that; see the package README.
 */
class NativePhpClipboardSource implements ClipboardSource
{
    public function read(): ClipboardSnapshot
    {
        try {
            $text = Clipboard::text();
        } catch (Throwable) {
            return ClipboardSnapshot::empty();
        }

        return is_string($text) && $text !== ''
            ? ClipboardSnapshot::text($text)
            : ClipboardSnapshot::empty();
    }

    public function write(string $text): void
    {
        Clipboard::text($text);
    }
}
