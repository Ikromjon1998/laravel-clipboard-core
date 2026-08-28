<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\Support\Fingerprint;

it('is stable for identical content', function () {
    $fingerprint = new Fingerprint;

    expect($fingerprint->of('hello'))->toBe($fingerprint->of('hello'));
});

it('differs for different content', function () {
    $fingerprint = new Fingerprint;

    expect($fingerprint->of('hello'))->not->toBe($fingerprint->of('world'));
});

it('separates long clips that share a prefix but differ in length', function () {
    // Only the prefix is hashed, so length is what must keep these apart.
    $fingerprint = new Fingerprint(prefixBytes: 16);

    expect($fingerprint->of(str_repeat('a', 100)))
        ->not->toBe($fingerprint->of(str_repeat('a', 101)));
});

it('normalises carriage returns so line endings do not fork a clip', function () {
    $fingerprint = new Fingerprint;

    expect($fingerprint->normalise("a\r\nb"))->toBe("a\nb")
        ->and($fingerprint->normalise("a\rb"))->toBe("a\nb");
});

it('preserves indentation, because copied code must come back verbatim', function () {
    $fingerprint = new Fingerprint;
    $indented = '    return true;';

    expect($fingerprint->normalise($indented))->toBe($indented)
        ->and($fingerprint->of($indented))->not->toBe($fingerprint->of('return true;'));
});
