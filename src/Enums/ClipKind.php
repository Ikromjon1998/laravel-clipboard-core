<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Enums;

enum ClipKind: string
{
    case Text = 'text';

    case Url = 'url';

    /**
     * Classify content for filtering and display.
     *
     * Deliberately strict: a URL is a single-token string with an http(s)
     * scheme. Prose that merely mentions a link stays text, because users
     * filtering by "links" mean things they can open.
     */
    public static function classify(string $content): self
    {
        $trimmed = trim($content);

        if ($trimmed === '' || preg_match('/\s/u', $trimmed) === 1) {
            return self::Text;
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
            return self::Text;
        }

        return filter_var($trimmed, FILTER_VALIDATE_URL) === false
            ? self::Text
            : self::Url;
    }
}
