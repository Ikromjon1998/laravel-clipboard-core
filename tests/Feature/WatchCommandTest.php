<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Contracts\PauseSwitch;
use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;
use Ikromjon\ClipboardCore\Models\Clip;

beforeEach(fn () => app(PauseSwitch::class)->resume());

afterEach(fn () => app(PauseSwitch::class)->resume());

it('captures a clip in a single poll', function () {
    $this->clipboard->queue('from the command');

    $this->artisan('clipboard:watch --once')
        ->assertSuccessful()
        ->expectsOutputToContain('watcher: captured');

    expect(Clip::query()->count())->toBe(1);
});

it('records nothing while paused', function () {
    app(PauseSwitch::class)->pause();
    $this->clipboard->queue('typed while paused');

    $this->artisan('clipboard:watch --ticks=1')
        ->assertSuccessful()
        ->expectsOutputToContain('watcher: paused');

    expect(Clip::query()->count())->toBe(0);
});

it('polls repeatedly until the tick budget runs out', function () {
    $this->clipboard->queue('one', 'two', 'three');

    $this->artisan('clipboard:watch --ticks=3')->assertSuccessful();

    expect(Clip::query()->count())->toBe(3);
});

it('survives a failing read and keeps polling', function () {
    $this->app->bind(
        ClipboardSource::class,
        fn () => new class implements ClipboardSource
        {
            public function read(): ClipboardSnapshot
            {
                throw new RuntimeException('pasteboard unavailable');
            }

            public function write(string $text): void {}
        },
    );

    $this->artisan('clipboard:watch --ticks=2')
        ->assertSuccessful()
        ->expectsOutputToContain('watcher: error pasteboard unavailable');
});
