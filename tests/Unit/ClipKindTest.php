<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\Enums\ClipKind;

it('recognises a bare http(s) url', function (string $input) {
    expect(ClipKind::classify($input))->toBe(ClipKind::Url);
})->with([
    'https://nativephp.com',
    'http://localhost:8100/hud',
    'https://github.com/laravel/laravel/pull/1?tab=files#diff',
]);

it('treats anything else as text', function (string $input) {
    expect(ClipKind::classify($input))->toBe(ClipKind::Text);
})->with([
    'plain prose',
    'see https://nativephp.com for details',
    'ftp://example.com/file.txt',
    'nativephp.com',
    'mailto:someone@example.com',
    '',
    '   ',
]);

it('ignores surrounding whitespace when classifying', function () {
    expect(ClipKind::classify("  https://nativephp.com \n"))->toBe(ClipKind::Url);
});
