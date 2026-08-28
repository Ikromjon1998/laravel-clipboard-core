<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Sources;

use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;

/**
 * An in-memory pasteboard for tests and local development.
 *
 * This is what makes the engine testable without a desktop: queue a series
 * of snapshots, run the watcher, and assert on what it recorded. Bind it in
 * place of the NativePHP source and the rest of the package cannot tell.
 */
class ArrayClipboardSource implements ClipboardSource
{
    /** @var list<ClipboardSnapshot> */
    private array $queue = [];

    private ClipboardSnapshot $current;

    /** @var list<string> */
    private array $written = [];

    public function __construct(?ClipboardSnapshot $initial = null)
    {
        $this->current = $initial ?? ClipboardSnapshot::empty();
    }

    /**
     * Queue snapshots to be returned by successive reads. Once the queue is
     * exhausted, reads keep returning the last value — a real pasteboard
     * does not empty itself between polls.
     */
    public function queue(ClipboardSnapshot|string ...$snapshots): self
    {
        foreach ($snapshots as $snapshot) {
            $this->queue[] = is_string($snapshot)
                ? ClipboardSnapshot::text($snapshot)
                : $snapshot;
        }

        return $this;
    }

    public function read(): ClipboardSnapshot
    {
        if ($this->queue !== []) {
            $this->current = array_shift($this->queue);
        }

        return $this->current;
    }

    public function write(string $text): void
    {
        $this->written[] = $text;
        $this->current = ClipboardSnapshot::text($text);
    }

    /** @return list<string> Everything written, in order. */
    public function written(): array
    {
        return $this->written;
    }

    public function pending(): int
    {
        return count($this->queue);
    }
}
