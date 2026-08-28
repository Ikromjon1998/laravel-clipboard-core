<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Support;

/**
 * Content identity for change detection and deduplication.
 *
 * Hashing a bounded prefix keeps the cost flat regardless of clip size —
 * the watcher does this on every poll, so it has to stay cheap. The byte
 * length is mixed in, so two clips must share both a length and that
 * prefix before they can collide.
 */
final class Fingerprint
{
    private const PREFERRED_ALGO = 'xxh64';

    private const FALLBACK_ALGO = 'crc32c';

    public function __construct(private readonly int $prefixBytes = 65_536) {}

    public function of(string $content): string
    {
        return hash(
            self::algorithm(),
            substr($content, 0, $this->prefixBytes).'|'.strlen($content),
        );
    }

    /**
     * Whitespace-only differences are still different clips: users copy
     * indented code and expect it back verbatim. Normalisation here is
     * limited to line endings, which vary by source app for identical text.
     */
    public function normalise(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    private static function algorithm(): string
    {
        static $algo = null;

        return $algo ??= in_array(self::PREFERRED_ALGO, hash_algos(), true)
            ? self::PREFERRED_ALGO
            : self::FALLBACK_ALGO;
    }
}
