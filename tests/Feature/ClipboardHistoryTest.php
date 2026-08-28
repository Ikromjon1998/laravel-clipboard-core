<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\Contracts\PauseSwitch;
use Ikromjon\ClipboardCore\Events\WatcherPaused;
use Ikromjon\ClipboardCore\Events\WatcherResumed;
use Ikromjon\ClipboardCore\Models\Clip;
use Illuminate\Support\Facades\Event;

// The pause switch is a file on disk, so it outlives the container.
beforeEach(fn () => app(PauseSwitch::class)->resume());

afterEach(fn () => app(PauseSwitch::class)->resume());

function capture(string ...$contents): void
{
    foreach ($contents as $content) {
        test()->clipboard->queue($content);
        watcher()->tick();
    }
}

it('lists pinned clips before recent ones', function () {
    capture('oldest', 'middle', 'newest');

    $oldest = Clip::query()->where('content', 'oldest')->first();
    history()->pin($oldest->id);

    expect(history()->recent()->pluck('content')->all())
        ->toBe(['oldest', 'newest', 'middle']);
});

it('searches case-insensitively within content', function () {
    capture('composer require nativephp/desktop', 'npm install', 'NativePHP docs');

    expect(history()->search('nativephp')->pluck('content')->all())
        ->toHaveCount(2);
});

it('treats wildcard characters in a search term literally', function () {
    capture('100% done', 'nothing to see');

    expect(history()->search('100%')->pluck('content')->all())->toBe(['100% done'])
        ->and(history()->search('%')->pluck('content')->all())->toBe(['100% done']);
});

it('returns recent clips for an empty search', function () {
    capture('one', 'two');

    expect(history()->search('   ')->pluck('content')->all())->toBe(['two', 'one']);
});

it('writes a chosen clip back to the clipboard', function () {
    capture('paste me');
    $clip = Clip::query()->first();

    history()->use($clip->id);

    expect($this->clipboard->written())->toBe(['paste me']);
});

it('suppresses the watcher when handing a clip back', function () {
    capture('paste me');
    history()->use(Clip::query()->first()->id);

    // The strategy wrote to the fake pasteboard; the next poll must ignore it.
    $this->clipboard->queue('paste me');

    expect(watcher()->tick())->toBeNull()
        ->and(Clip::query()->where('content', 'paste me')->first()->times_copied)->toBe(1);
});

it('returns null when using a clip that does not exist', function () {
    expect(history()->use(9_999))->toBeNull()
        ->and($this->clipboard->written())->toBe([]);
});

it('pins, unpins, forgets and counts', function () {
    capture('a', 'b');
    $clip = Clip::query()->where('content', 'a')->first();

    expect(history()->pin($clip->id)->pinned)->toBeTrue()
        ->and(history()->pin($clip->id, false)->pinned)->toBeFalse()
        ->and(history()->count())->toBe(2)
        ->and(history()->forget($clip->id))->toBeTrue()
        ->and(history()->count())->toBe(1);
});

it('clears unpinned clips but spares pinned ones by default', function () {
    capture('keep', 'drop');
    history()->pin(Clip::query()->where('content', 'keep')->first()->id);

    expect(history()->clear())->toBe(1)
        ->and(history()->count())->toBe(1);

    expect(history()->clear(includePinned: true))->toBe(1)
        ->and(history()->count())->toBe(0);
});

it('toggles pause state and announces it once', function () {
    Event::fake([WatcherPaused::class, WatcherResumed::class]);

    expect(history()->isPaused())->toBeFalse()
        ->and(history()->toggle())->toBeTrue()
        ->and(history()->isPaused())->toBeTrue();

    // Pausing an already-paused watcher is a no-op, not a second event.
    history()->pause();

    expect(history()->toggle())->toBeFalse();

    Event::assertDispatchedTimes(WatcherPaused::class, 1);
    Event::assertDispatchedTimes(WatcherResumed::class, 1);
});
