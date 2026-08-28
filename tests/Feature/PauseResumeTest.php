<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\Contracts\PauseSwitch;
use Ikromjon\ClipboardCore\Models\Clip;

beforeEach(fn () => app(PauseSwitch::class)->resume());

afterEach(fn () => app(PauseSwitch::class)->resume());

it('does not capture what was copied during a pause', function () {
    history()->pause();

    // The watcher process keeps polling; it is the pause switch that stops it.
    $this->clipboard->queue('a secret typed while paused');

    history()->resume();
    watcher()->tick();

    expect(Clip::query()->count())->toBe(0);
});

it('captures a clip copied after resuming, even before the watcher notices', function () {
    // The regression this exists for: the watcher only sees a resume on its
    // next poll, up to a second later. A clip copied in that gap used to be
    // mistaken for paused-era content and silently dropped.
    history()->pause();
    $this->clipboard->queue('secret from the paused period');

    history()->resume();

    // User copies immediately after resuming — before any watcher tick.
    $this->clipboard->queue('copied right after resuming');
    watcher()->tick();

    expect(Clip::query()->pluck('content')->all())->toBe(['copied right after resuming']);
});

it('keeps capturing normally once resumed', function () {
    history()->pause();
    history()->resume();

    $this->clipboard->queue('first', 'second');
    watcher()->tick();
    watcher()->tick();

    expect(Clip::query()->pluck('content')->all())
        ->toEqualCanonicalizing(['first', 'second']);
});

it('leaves an untouched clipboard alone across a pause cycle', function () {
    $this->clipboard->queue('copied before pausing');
    watcher()->tick();

    history()->pause();
    history()->resume();
    watcher()->tick();

    // Still one clip, not a second copy of the same content.
    expect(Clip::query()->count())->toBe(1)
        ->and(Clip::query()->first()->times_copied)->toBe(1);
});
