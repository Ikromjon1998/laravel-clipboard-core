<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Console;

use Ikromjon\ClipboardCore\Contracts\PauseSwitch;
use Ikromjon\ClipboardCore\Watcher\ClipboardWatcher;
use Illuminate\Console\Command;
use Throwable;

/**
 * The long-running watcher process.
 *
 * Owns only the loop, the sleeping, and the pause check — all judgement
 * about what to capture lives in the watcher, which is why this command
 * needs almost no test coverage of its own.
 *
 * Everything written to stdout is picked up by the host as process output,
 * so lines are terse and prefixed.
 */
class WatchCommand extends Command
{
    protected $signature = 'clipboard:watch
                            {--once : Poll a single time and exit}
                            {--ticks= : Stop after this many polls (for smoke tests)}';

    protected $description = 'Watch the system clipboard and record every change';

    public function handle(ClipboardWatcher $watcher, PauseSwitch $pause): int
    {
        $limit = $this->option('once') ? 1 : $this->intOption('ticks');
        $cadence = $watcher->cadence();
        $wasPaused = false;
        $ticks = 0;

        $this->line('watcher: started pid='.getmypid());

        while ($limit === null || $ticks < $limit) {
            $ticks++;

            if ($pause->isPaused()) {
                if (! $wasPaused) {
                    $this->line('watcher: paused');
                    $wasPaused = true;
                }

                $this->rest(1_000);

                continue;
            }

            if ($wasPaused) {
                // No resync here on purpose. ClipboardHistory::resume()
                // already recorded the clipboard as it stood when the user
                // resumed; baselining again now would swallow anything they
                // copied between that moment and this poll.
                $this->line('watcher: resumed');
                $wasPaused = false;
            }

            try {
                $clip = $watcher->tick();
            } catch (Throwable $e) {
                // A watcher that dies on one bad poll is worse than one that
                // logs and keeps going; the host restarts it only if it exits.
                $this->line('watcher: error '.$e->getMessage());
                $this->rest($cadence->sleepMilliseconds());

                continue;
            }

            if ($clip !== null) {
                $this->line(sprintf(
                    'watcher: captured id=%d kind=%s bytes=%d',
                    $clip->id,
                    $clip->kind->value,
                    $clip->byte_size,
                ));
            }

            if ($limit === null || $ticks < $limit) {
                $this->rest($cadence->sleepMilliseconds());
            }
        }

        return self::SUCCESS;
    }

    protected function rest(int $milliseconds): void
    {
        usleep($milliseconds * 1_000);
    }

    private function intOption(string $name): ?int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
