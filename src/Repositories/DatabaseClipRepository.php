<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Repositories;

use Ikromjon\ClipboardCore\Contracts\ClipRepository;
use Ikromjon\ClipboardCore\Models\Clip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A ring buffer that happens to be a database table.
 *
 * Everything here is written for a table of ~100 rows: no pagination, no
 * full-text index, no caching. Those become correct at a scale this app
 * deliberately never reaches.
 */
class DatabaseClipRepository implements ClipRepository
{
    public function record(string $content, string $fingerprint, string $kind): Clip
    {
        $now = Carbon::now();
        $existing = Clip::query()->where('fingerprint', $fingerprint)->first();

        // Re-copying an old clip resurfaces it rather than duplicating it,
        // which is why this is an update rather than an insert.
        if ($existing instanceof Clip) {
            $existing->forceFill([
                'last_copied_at' => $now,
                'times_copied' => $existing->times_copied + 1,
            ])->save();

            return $existing->refresh();
        }

        $clip = new Clip;
        $clip->forceFill([
            'content' => $content,
            'fingerprint' => $fingerprint,
            'kind' => $kind,
            'byte_size' => strlen($content),
            'pinned' => false,
            'times_copied' => 1,
            'first_copied_at' => $now,
            'last_copied_at' => $now,
        ])->save();

        return $clip;
    }

    public function recent(int $limit): Collection
    {
        return Clip::query()->inHistoryOrder()->limit($limit)->get();
    }

    public function search(string $term, int $limit): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return $this->recent($limit);
        }

        // A term containing % or _ must match literally — people search their
        // history for "100%" and for snake_case names. SQLite defines no
        // default escape character, so the clause has to name one explicitly.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        return Clip::query()
            ->whereRaw("content LIKE ? ESCAPE '\\'", ['%'.$escaped.'%'])
            ->inHistoryOrder()
            ->limit($limit)
            ->get();
    }

    public function find(int $id): ?Clip
    {
        return Clip::query()->find($id);
    }

    public function pin(int $id, bool $pinned = true): ?Clip
    {
        $clip = $this->find($id);

        if (! $clip instanceof Clip) {
            return null;
        }

        $clip->forceFill(['pinned' => $pinned])->save();

        return $clip;
    }

    public function forget(int $id): bool
    {
        $clip = $this->find($id);

        return $clip instanceof Clip && $clip->delete() === true;
    }

    public function prune(int $keep): int
    {
        if ($keep < 0) {
            $keep = 0;
        }

        // Pinned clips are exempt and do not consume slots, so the survivor
        // set is computed over unpinned rows only.
        $survivors = Clip::query()
            ->unpinned()
            ->orderByDesc('last_copied_at')
            ->orderByDesc('id')
            ->limit($keep)
            ->pluck('id')
            ->all();

        $doomed = Clip::query()->unpinned();

        if ($survivors !== []) {
            $doomed->whereNotIn('id', $survivors);
        }

        return $doomed->delete();
    }

    public function clear(bool $includePinned = false): int
    {
        $query = Clip::query();

        if (! $includePinned) {
            $query->unpinned();
        }

        return $query->delete();
    }

    public function count(bool $includePinned = true): int
    {
        $query = Clip::query();

        if (! $includePinned) {
            $query->unpinned();
        }

        return $query->count();
    }
}
