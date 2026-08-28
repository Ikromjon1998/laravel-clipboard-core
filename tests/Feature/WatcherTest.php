<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;
use Ikromjon\ClipboardCore\Enums\ClipKind;
use Ikromjon\ClipboardCore\Events\ClipCaptured;
use Ikromjon\ClipboardCore\Events\ClipRejected;
use Ikromjon\ClipboardCore\Events\ClipsPruned;
use Ikromjon\ClipboardCore\Models\Clip;
use Illuminate\Support\Facades\Event;

it('captures a new clip', function () {
    $this->clipboard->queue('hello world');

    $clip = watcher()->tick();

    expect($clip)->toBeInstanceOf(Clip::class)
        ->and($clip->content)->toBe('hello world')
        ->and($clip->times_copied)->toBe(1)
        ->and($clip->pinned)->toBeFalse()
        ->and(Clip::query()->count())->toBe(1);
});

it('ignores an unchanged clipboard', function () {
    $this->clipboard->queue('same', 'same', 'same');

    expect(watcher()->tick())->toBeInstanceOf(Clip::class)
        ->and(watcher()->tick())->toBeNull()
        ->and(watcher()->tick())->toBeNull()
        ->and(Clip::query()->count())->toBe(1);
});

it('resurfaces a repeated clip instead of duplicating it', function () {
    $this->clipboard->queue('first', 'second', 'first');

    watcher()->tick();
    watcher()->tick();
    $clip = watcher()->tick();

    expect(Clip::query()->count())->toBe(2)
        ->and($clip->content)->toBe('first')
        ->and($clip->times_copied)->toBe(2);

    // The resurfaced clip is now the most recent.
    expect(Clip::query()->orderByDesc('last_copied_at')->first()->content)->toBe('first');
});

it('classifies urls apart from text', function () {
    $this->clipboard->queue('https://nativephp.com/docs', 'just some prose');

    expect(watcher()->tick()->kind)->toBe(ClipKind::Url)
        ->and(watcher()->tick()->kind)->toBe(ClipKind::Text);
});

it('normalises line endings so the same text is one clip', function () {
    $this->clipboard->queue("line one\r\nline two", "line one\nline two");

    watcher()->tick();

    expect(watcher()->tick())->toBeNull()
        ->and(Clip::query()->count())->toBe(1);
});

it('refuses blank clips', function () {
    $this->clipboard->queue('   ', "\n\t ");

    expect(watcher()->tick())->toBeNull()
        ->and(watcher()->tick())->toBeNull()
        ->and(Clip::query()->count())->toBe(0);
});

it('refuses clips beyond the size ceiling', function () {
    config()->set('clipboard.max_bytes', 32);
    Event::fake([ClipRejected::class]);

    $this->clipboard->queue(str_repeat('x', 64));

    expect(watcher()->tick())->toBeNull()
        ->and(Clip::query()->count())->toBe(0);

    Event::assertDispatched(ClipRejected::class, fn (ClipRejected $e) => $e->bytes === 64);
});

it('refuses clips a password manager marked concealed', function () {
    $this->clipboard->queue(ClipboardSnapshot::concealed('hunter2', '1Password'));

    expect(watcher()->tick())->toBeNull()
        ->and(Clip::query()->count())->toBe(0);
});

it('ignores its own writes so pasting does not reorder history', function () {
    $this->clipboard->queue('original');
    watcher()->tick();

    // The app pastes that clip back, which puts it on the pasteboard again.
    watcher()->suppress('original');
    $this->clipboard->queue('something else', 'original');
    watcher()->tick();

    expect(watcher()->tick())->toBeNull()
        ->and(Clip::query()->where('content', 'original')->first()->times_copied)->toBe(1);
});

it('only suppresses a self-write once', function () {
    watcher()->suppress('repeated');
    $this->clipboard->queue('repeated', 'other', 'repeated');

    expect(watcher()->tick())->toBeNull();

    watcher()->tick();

    expect(watcher()->tick())->toBeInstanceOf(Clip::class)
        ->and(Clip::query()->where('content', 'repeated')->exists())->toBeTrue();
});

it('prunes to the configured limit, keeping the newest', function () {
    config()->set('clipboard.limit', 3);
    Event::fake([ClipsPruned::class]);

    foreach (range(1, 6) as $n) {
        $this->clipboard->queue("clip {$n}");
        watcher()->tick();
    }

    expect(Clip::query()->count())->toBe(3)
        ->and(Clip::query()->pluck('content')->all())
        ->toEqualCanonicalizing(['clip 4', 'clip 5', 'clip 6']);

    Event::assertDispatched(ClipsPruned::class);
});

it('never prunes pinned clips', function () {
    config()->set('clipboard.limit', 2);

    $this->clipboard->queue('keep me');
    $pinned = watcher()->tick();
    $pinned->forceFill(['pinned' => true])->save();

    foreach (range(1, 5) as $n) {
        $this->clipboard->queue("filler {$n}");
        watcher()->tick();
    }

    expect(Clip::query()->find($pinned->id))->not->toBeNull()
        // Two unpinned survivors plus the exempt pinned clip.
        ->and(Clip::query()->count())->toBe(3);
});

it('dispatches ClipCaptured, flagging new versus resurfaced', function () {
    Event::fake([ClipCaptured::class]);
    $this->clipboard->queue('a', 'b', 'a');

    watcher()->tick();
    watcher()->tick();
    watcher()->tick();

    Event::assertDispatchedTimes(ClipCaptured::class, 3);
    Event::assertDispatched(ClipCaptured::class, fn (ClipCaptured $e) => $e->clip->content === 'a' && $e->isNew === false);
});

it('treats content after a resync as already seen', function () {
    $this->clipboard->queue('secret typed while paused');
    watcher()->resync();

    $this->clipboard->queue('secret typed while paused');

    expect(watcher()->tick())->toBeNull()
        ->and(Clip::query()->count())->toBe(0);
});
