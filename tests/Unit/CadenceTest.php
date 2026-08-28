<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\Watcher\Cadence;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('starts hot, because a just-started watcher is about to be used', function () {
    expect((new Cadence)->tier())->toBe('hot');
});

it('stays hot inside the burst window', function () {
    Carbon::setTestNow('2026-08-29 12:00:00');
    $cadence = new Cadence;
    $cadence->noteChange();

    Carbon::setTestNow('2026-08-29 12:00:20');

    expect($cadence->tier())->toBe('hot')
        ->and($cadence->sleepMilliseconds())->toBe(250);
});

it('cools to warm once the burst window passes', function () {
    Carbon::setTestNow('2026-08-29 12:00:00');
    $cadence = new Cadence;
    $cadence->noteChange();

    Carbon::setTestNow('2026-08-29 12:01:00');

    expect($cadence->tier())->toBe('warm')
        ->and($cadence->sleepMilliseconds())->toBe(500);
});

it('drops to cold after a long silence', function () {
    Carbon::setTestNow('2026-08-29 12:00:00');
    $cadence = new Cadence;
    $cadence->noteChange();

    Carbon::setTestNow('2026-08-29 12:30:00');

    expect($cadence->tier())->toBe('cold')
        ->and($cadence->sleepMilliseconds())->toBe(1_000);
});

it('returns to hot the moment something is copied', function () {
    Carbon::setTestNow('2026-08-29 12:00:00');
    $cadence = new Cadence;
    $cadence->noteChange();

    Carbon::setTestNow('2026-08-29 13:00:00');
    expect($cadence->tier())->toBe('cold');

    $cadence->noteChange();
    expect($cadence->tier())->toBe('hot');
});

it('reads its tiers from configuration', function () {
    $cadence = Cadence::fromConfig([
        'hot' => 50,
        'warm' => 100,
        'cold' => 200,
        'hot_window_seconds' => 5,
        'cold_after_seconds' => 10,
    ]);

    Carbon::setTestNow('2026-08-29 12:00:00');
    $cadence->noteChange();
    expect($cadence->sleepMilliseconds())->toBe(50);

    Carbon::setTestNow('2026-08-29 12:00:07');
    expect($cadence->sleepMilliseconds())->toBe(100);

    Carbon::setTestNow('2026-08-29 12:00:30');
    expect($cadence->sleepMilliseconds())->toBe(200);
});
