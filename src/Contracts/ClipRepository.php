<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Contracts;

use Ikromjon\ClipboardCore\Models\Clip;
use Illuminate\Support\Collection;

/**
 * Storage for clip history.
 *
 * The default implementation is a SQLite-backed ring buffer, but the
 * contract says nothing about SQL — swap in an encrypted store, a
 * memory-only store for a privacy mode, or a synced store.
 */
interface ClipRepository
{
    /**
     * Record a clip, or bump an existing identical one to the top.
     *
     * Implementations must be idempotent per fingerprint: recording the
     * same content twice yields one row with an incremented copy count.
     */
    public function record(string $content, string $fingerprint, string $kind): Clip;

    /** @return Collection<int, Clip> Pinned clips first, then most recent. */
    public function recent(int $limit): Collection;

    /** @return Collection<int, Clip> */
    public function search(string $term, int $limit): Collection;

    public function find(int $id): ?Clip;

    public function pin(int $id, bool $pinned = true): ?Clip;

    public function forget(int $id): bool;

    /**
     * Drop unpinned clips beyond the newest $keep.
     *
     * @return int Number of clips removed.
     */
    public function prune(int $keep): int;

    /** @return int Number of clips removed. */
    public function clear(bool $includePinned = false): int;

    public function count(bool $includePinned = true): int;
}
