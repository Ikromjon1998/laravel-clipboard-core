<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Support;

use Ikromjon\ClipboardCore\Contracts\SuppressionLog;

/**
 * Cross-process suppression as a small JSON file of fingerprint => expiry.
 *
 * A file rather than a cache or a socket for the same reason the pause switch
 * is one: the writer and the reader are different OS processes that share
 * nothing but a filesystem, and this must work before any optional
 * infrastructure is configured.
 */
class FileSuppressionLog implements SuppressionLog
{
    public function __construct(
        private readonly string $path,
        private readonly int $ttlSeconds = 10,
    ) {}

    public function suppress(string $fingerprint): void
    {
        $entries = $this->read();
        $entries[$fingerprint] = time() + $this->ttlSeconds;

        $this->write($entries);
    }

    public function consume(string $fingerprint): bool
    {
        $entries = $this->read();
        $expiry = $entries[$fingerprint] ?? null;

        unset($entries[$fingerprint]);
        $this->write($entries);

        return is_int($expiry) && $expiry >= time();
    }

    /** @return array<string, int> */
    private function read(): array
    {
        clearstatcache(true, $this->path);

        if (! is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);

        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $now = time();
        $entries = [];

        foreach ($decoded as $fingerprint => $expiry) {
            // Dropping expired entries on read keeps the file bounded without
            // needing a separate sweep.
            if (is_string($fingerprint) && is_int($expiry) && $expiry >= $now) {
                $entries[$fingerprint] = $expiry;
            }
        }

        return $entries;
    }

    /** @param array<string, int> $entries */
    private function write(array $entries): void
    {
        if ($entries === []) {
            if (is_file($this->path)) {
                @unlink($this->path);
            }

            return;
        }

        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, recursive: true);
        }

        file_put_contents($this->path, json_encode($entries), LOCK_EX);
    }
}
