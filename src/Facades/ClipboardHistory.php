<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Facades;

use Ikromjon\ClipboardCore\Models\Clip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, Clip> recent(?int $limit = null)
 * @method static Collection<int, Clip> search(string $term, ?int $limit = null)
 * @method static Clip|null find(int $id)
 * @method static Clip|null use(Clip|int $clip)
 * @method static Clip|null pin(int $id, bool $pinned = true)
 * @method static bool forget(int $id)
 * @method static int clear(bool $includePinned = false)
 * @method static int count(bool $includePinned = true)
 * @method static bool isPaused()
 * @method static void pause()
 * @method static void resume()
 * @method static bool toggle()
 *
 * @see \Ikromjon\ClipboardCore\ClipboardHistory
 */
class ClipboardHistory extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ikromjon\ClipboardCore\ClipboardHistory::class;
    }
}
