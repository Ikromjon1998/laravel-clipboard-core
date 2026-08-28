<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;
use Ikromjon\ClipboardCore\Sources\ArrayClipboardSource;
use Ikromjon\ClipboardCore\Sources\ProcessClipboardSource;

/**
 * Builds a throwaway helper that emits the given lines and then idles, so the
 * protocol can be tested on any platform — no macOS, no compiled binary.
 *
 * @param  list<string>  $lines
 * @return list<string>
 */
function fakeProbe(array $lines): array
{
    $script = '';

    foreach ($lines as $line) {
        $script .= 'echo '.escapeshellarg($line).'; ';
    }

    // Stay alive afterwards: a real probe is a long-running process, and the
    // source must not mistake "nothing new" for "helper died".
    return ['/bin/sh', '-c', $script.'sleep 30'];
}

function probeSource(array $lines): ProcessClipboardSource
{
    return new ProcessClipboardSource(fakeProbe($lines), new ArrayClipboardSource);
}

/** The helper writes asynchronously, so give it a moment to produce output. */
function readAfterOutput(ProcessClipboardSource $source): ClipboardSnapshot
{
    $snapshot = $source->read();

    for ($attempt = 0; $attempt < 40 && $snapshot->text === null && ! $snapshot->concealed; $attempt++) {
        usleep(25_000);
        $snapshot = $source->read();
    }

    return $snapshot;
}

it('reads text a helper reports', function () {
    $source = probeSource(['{"change":1,"concealed":false,"app":"Safari","text":"hello from the probe"}']);

    $snapshot = readAfterOutput($source);

    expect($snapshot->text)->toBe('hello from the probe')
        ->and($snapshot->concealed)->toBeFalse()
        ->and($snapshot->sourceApp)->toBe('Safari');
});

it('never exposes text for a concealed item', function () {
    $source = probeSource(['{"change":1,"concealed":true,"app":"1Password"}']);

    $snapshot = readAfterOutput($source);

    expect($snapshot->concealed)->toBeTrue()
        ->and($snapshot->text)->toBeNull()
        ->and($snapshot->sourceApp)->toBe('1Password');
});

it('drops text even if a helper wrongly sends it alongside concealed', function () {
    // The guarantee cannot depend on every helper being written correctly.
    $source = probeSource(['{"change":1,"concealed":true,"app":"Bad","text":"hunter2"}']);

    $snapshot = readAfterOutput($source);

    expect($snapshot->concealed)->toBeTrue()
        ->and($snapshot->text)->toBeNull();
});

it('keeps the most recent of several changes', function () {
    $source = probeSource([
        '{"change":1,"concealed":false,"text":"first"}',
        '{"change":2,"concealed":false,"text":"second"}',
        '{"change":3,"concealed":false,"text":"third"}',
    ]);

    expect(readAfterOutput($source)->text)->toBe('third');
});

it('reports no text for an oversize clip', function () {
    $source = probeSource(['{"change":1,"concealed":false,"oversize":true}']);
    $source->read();
    usleep(150_000);

    expect($source->read()->text)->toBeNull();
});

it('ignores malformed lines without losing the stream', function () {
    $source = probeSource([
        'not json at all',
        '{"broken":',
        '{"change":9,"concealed":false,"text":"survived"}',
    ]);

    expect(readAfterOutput($source)->text)->toBe('survived');
});

it('reports an empty snapshot when the helper cannot be started', function () {
    $source = new ProcessClipboardSource(['/nonexistent/probe'], new ArrayClipboardSource);

    expect($source->read()->text)->toBeNull()
        ->and($source->read()->isEmpty())->toBeTrue();
});

it('delegates writes, because a probe only observes', function () {
    $writer = new ArrayClipboardSource;
    $source = new ProcessClipboardSource(fakeProbe([]), $writer);

    $source->write('put me on the pasteboard');

    expect($writer->written())->toBe(['put me on the pasteboard']);
});
