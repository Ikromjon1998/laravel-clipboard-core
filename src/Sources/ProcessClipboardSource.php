<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Sources;

use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Data\ClipboardSnapshot;

/**
 * Reads pasteboard state from a long-running helper process.
 *
 * The helper exists to see what a cross-platform runtime cannot. On macOS,
 * applications declare that their contents are secret through custom
 * pasteboard types, which Electron's clipboard API does not expose — so
 * without a native helper, a clipboard manager records what you copy out of
 * a password manager and there is no way for it to know.
 *
 * The protocol is one JSON object per line on stdout:
 *
 *     {"change":42,"concealed":false,"app":"Safari","text":"hello"}
 *     {"change":43,"concealed":true,"app":"1Password"}
 *     {"change":44,"concealed":false,"app":"Xcode","oversize":true}
 *
 * | Field       | Meaning                                                  |
 * |-------------|----------------------------------------------------------|
 * | `change`    | Monotonic counter; only present when something changed   |
 * | `concealed` | The owning app marked this secret — **omit `text`**      |
 * | `app`       | Frontmost application, for exclusion rules (optional)    |
 * | `text`      | The contents; absent when concealed, oversize, non-text  |
 * | `oversize`  | Text existed but exceeded the helper's byte ceiling      |
 *
 * A helper must never emit `text` alongside `concealed`. That rule is what
 * makes the guarantee real: secret content never enters this process, so no
 * bug on this side can write it to disk.
 *
 * Writing is delegated, because a probe only observes.
 */
class ProcessClipboardSource implements ClipboardSource
{
    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $buffer = '';

    private ClipboardSnapshot $current;

    private int $restartAfter = 0;

    /** @param list<string> $command */
    public function __construct(
        private readonly array $command,
        private readonly ClipboardSource $writer,
        private readonly int $restartBackoffSeconds = 5,
    ) {
        $this->current = ClipboardSnapshot::empty();
    }

    public function read(): ClipboardSnapshot
    {
        $this->ensureRunning();
        $this->drain();

        return $this->current;
    }

    public function write(string $text): void
    {
        $this->writer->write($text);
    }

    public function __destruct()
    {
        $this->stop();
    }

    private function ensureRunning(): void
    {
        if ($this->isRunning()) {
            return;
        }

        // A helper that keeps dying must not become a spawn loop; between
        // attempts the source simply reports whatever it last saw.
        if (time() < $this->restartAfter) {
            return;
        }

        $this->stop();
        $this->restartAfter = time() + $this->restartBackoffSeconds;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($this->command, $descriptors, $pipes);

        if (! is_resource($process)) {
            return;
        }

        // Reads must never block the poll loop: the helper is silent whenever
        // the pasteboard is idle, which is almost always.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->process = $process;
        $this->pipes = $pipes;
        $this->buffer = '';
    }

    private function isRunning(): bool
    {
        if (! is_resource($this->process)) {
            return false;
        }

        return proc_get_status($this->process)['running'];
    }

    /** Consume whatever the helper has emitted since the last poll. */
    private function drain(): void
    {
        if (! isset($this->pipes[1]) || ! is_resource($this->pipes[1])) {
            return;
        }

        while (($chunk = fread($this->pipes[1], 65536)) !== false && $chunk !== '') {
            $this->buffer .= $chunk;
        }

        // Discard stderr so a chatty helper cannot fill its pipe and stall.
        if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
            while (fread($this->pipes[2], 65536) !== false) {
                break;
            }
        }

        while (($newline = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $newline);
            $this->buffer = substr($this->buffer, $newline + 1);

            $snapshot = self::parse($line);

            if ($snapshot instanceof ClipboardSnapshot) {
                $this->current = $snapshot;
            }
        }
    }

    private static function parse(string $line): ?ClipboardSnapshot
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        $decoded = json_decode($line, true);

        if (! is_array($decoded)) {
            return null;
        }

        $app = isset($decoded['app']) && is_string($decoded['app']) ? $decoded['app'] : null;
        $concealed = ($decoded['concealed'] ?? false) === true;
        $text = $decoded['text'] ?? null;

        // Belt and braces: a helper that breaks the contract and sends text
        // for a concealed item still gets its text dropped here.
        if ($concealed || ! is_string($text)) {
            return new ClipboardSnapshot(text: null, concealed: $concealed, sourceApp: $app);
        }

        return new ClipboardSnapshot(text: $text, concealed: false, sourceApp: $app);
    }

    private function stop(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $this->pipes = [];

        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }

        $this->process = null;
    }
}
