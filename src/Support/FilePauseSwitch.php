<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Support;

use Ikromjon\ClipboardCore\Contracts\PauseSwitch;

/**
 * Pause state as the presence of a sentinel file.
 *
 * A file is the one channel guaranteed to work between a menu click in the
 * app process and a loop running in a separate watcher process, with no
 * cache driver, queue, or socket in play.
 */
class FilePauseSwitch implements PauseSwitch
{
    public function __construct(private readonly string $path) {}

    public function isPaused(): bool
    {
        clearstatcache(true, $this->path);

        return file_exists($this->path);
    }

    public function pause(): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, recursive: true);
        }

        touch($this->path);
    }

    public function resume(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
