<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Watcher;

use Illuminate\Support\Carbon;

/**
 * How long to sleep between polls.
 *
 * People copy in bursts, then not at all for long stretches. Polling at a
 * fixed rate means paying burst-speed cost during the quiet hours; this
 * tightens after activity and backs off during silence, which is the whole
 * reason idle CPU stays near zero.
 *
 * Time comes from Carbon so tests can travel rather than sleep.
 */
final class Cadence
{
    private ?Carbon $lastChangeAt = null;

    public function __construct(
        private readonly int $hotMs = 250,
        private readonly int $warmMs = 500,
        private readonly int $coldMs = 1_000,
        private readonly int $hotWindowSeconds = 30,
        private readonly int $coldAfterSeconds = 300,
    ) {}

    /**
     * @param  array<string, int>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            hotMs: $config['hot'] ?? 300,
            warmMs: $config['warm'] ?? 800,
            coldMs: $config['cold'] ?? 2_000,
            hotWindowSeconds: $config['hot_window_seconds'] ?? 30,
            coldAfterSeconds: $config['cold_after_seconds'] ?? 300,
        );
    }

    public function noteChange(): void
    {
        $this->lastChangeAt = Carbon::now();
    }

    public function sleepMilliseconds(): int
    {
        $idleSeconds = $this->idleSeconds();

        return match (true) {
            $idleSeconds < $this->hotWindowSeconds => $this->hotMs,
            $idleSeconds < $this->coldAfterSeconds => $this->warmMs,
            default => $this->coldMs,
        };
    }

    public function tier(): string
    {
        return match ($this->sleepMilliseconds()) {
            $this->hotMs => 'hot',
            $this->warmMs => 'warm',
            default => 'cold',
        };
    }

    /**
     * Before any change is seen the watcher has just started, which is
     * exactly when a user is most likely to be about to copy something.
     */
    private function idleSeconds(): float
    {
        if (! $this->lastChangeAt instanceof Carbon) {
            return 0.0;
        }

        return (float) $this->lastChangeAt->diffInSeconds(Carbon::now(), absolute: true);
    }
}
