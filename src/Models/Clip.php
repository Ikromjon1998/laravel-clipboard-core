<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Models;

use Ikromjon\ClipboardCore\Enums\ClipKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $content
 * @property string $fingerprint
 * @property ClipKind $kind
 * @property int $byte_size
 * @property bool $pinned
 * @property int $times_copied
 * @property Carbon $first_copied_at
 * @property Carbon $last_copied_at
 */
class Clip extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'kind' => ClipKind::class,
        'pinned' => 'boolean',
        'byte_size' => 'integer',
        'times_copied' => 'integer',
        'first_copied_at' => 'datetime',
        'last_copied_at' => 'datetime',
    ];

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('clipboard.table', 'clips');

        return $table;
    }

    /**
     * The canonical history order: pinned clips stay on top, everything
     * else by recency. Matches the composite index exactly.
     *
     * @param  Builder<Clip>  $query
     * @return Builder<Clip>
     */
    public function scopeInHistoryOrder(Builder $query): Builder
    {
        return $query->orderByDesc('pinned')->orderByDesc('last_copied_at');
    }

    /**
     * @param  Builder<Clip>  $query
     * @return Builder<Clip>
     */
    public function scopeUnpinned(Builder $query): Builder
    {
        return $query->where('pinned', false);
    }

    /**
     * A single-line, length-capped rendering for list rows.
     */
    public function preview(int $length = 80): string
    {
        $flattened = preg_replace('/\s+/u', ' ', $this->content) ?? $this->content;

        return trim(mb_substr($flattened, 0, $length));
    }
}
