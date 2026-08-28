<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Data;

/**
 * One reading of the pasteboard.
 *
 * `concealed` and `sourceApp` cannot be determined through Electron's
 * clipboard API — they are populated by a native helper when one is
 * present, and default to "unknown" otherwise. Guards treat them as
 * advisory, never as a guarantee.
 */
final readonly class ClipboardSnapshot
{
    public function __construct(
        public ?string $text = null,
        public bool $concealed = false,
        public ?string $sourceApp = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public static function text(string $text): self
    {
        return new self(text: $text);
    }

    /**
     * A snapshot a password manager marked as concealed or transient.
     *
     * @see https://nspasteboard.org
     */
    public static function concealed(string $text, ?string $sourceApp = null): self
    {
        return new self(text: $text, concealed: true, sourceApp: $sourceApp);
    }

    public function isEmpty(): bool
    {
        return $this->text === null || trim($this->text) === '';
    }

    public function bytes(): int
    {
        return $this->text === null ? 0 : strlen($this->text);
    }
}
