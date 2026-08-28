<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\Contracts\PasteStrategy;
use Ikromjon\ClipboardCore\Contracts\SuppressionLog;
use Ikromjon\ClipboardCore\Models\Clip;
use Ikromjon\ClipboardCore\Support\FileSuppressionLog;
use Ikromjon\ClipboardCore\Support\Fingerprint;

it('consumes a suppression exactly once', function () {
    $log = app(SuppressionLog::class);
    $log->suppress('abc');

    expect($log->consume('abc'))->toBeTrue()
        ->and($log->consume('abc'))->toBeFalse();
});

it('reports nothing for a fingerprint that was never suppressed', function () {
    expect(app(SuppressionLog::class)->consume('never-seen'))->toBeFalse();
});

it('expires a suppression that is never consumed', function () {
    // An unconsumed note must not swallow a genuine copy of the same text
    // minutes later, so entries carry an expiry.
    $log = new FileSuppressionLog(sys_get_temp_dir().'/clipboard-expiry-test.json', ttlSeconds: -1);
    $log->suppress('stale');

    expect($log->consume('stale'))->toBeFalse();
});

it('survives being written and read by separate instances', function () {
    // The paste happens in one process and the watcher polls in another, so
    // no in-memory state is shared between writer and reader.
    $path = sys_get_temp_dir().'/clipboard-cross-process-test.json';
    @unlink($path);

    (new FileSuppressionLog($path))->suppress('shared-fingerprint');

    expect((new FileSuppressionLog($path))->consume('shared-fingerprint'))->toBeTrue();
});

it('keeps suppressions for other clips when one is consumed', function () {
    $log = app(SuppressionLog::class);
    $log->suppress('first');
    $log->suppress('second');

    expect($log->consume('first'))->toBeTrue()
        ->and($log->consume('second'))->toBeTrue();
});

it('stops a pasted clip from being recaptured by a watcher in another process', function () {
    $this->clipboard->queue('paste me back');
    $clip = watcher()->tick();

    // Simulate the app process performing the paste: a strategy resolved
    // fresh, with no shared memory with the watcher above.
    app(PasteStrategy::class)->apply($clip);

    // The pasteboard now echoes our own write back at the watcher.
    $this->clipboard->queue('something else', 'paste me back');
    watcher()->tick();

    expect(watcher()->tick())->toBeNull()
        ->and(Clip::query()->where('content', 'paste me back')->first()->times_copied)->toBe(1);
});

it('normalises content before suppressing so line endings do not defeat it', function () {
    $log = app(SuppressionLog::class);
    $fingerprint = app(Fingerprint::class);

    watcher()->suppress("line one\r\nline two");

    expect($log->consume($fingerprint->of("line one\nline two")))->toBeTrue();
});
