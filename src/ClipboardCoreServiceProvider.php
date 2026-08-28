<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore;

use Ikromjon\ClipboardCore\Console\WatchCommand;
use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Contracts\ClipRepository;
use Ikromjon\ClipboardCore\Contracts\PasteStrategy;
use Ikromjon\ClipboardCore\Contracts\PauseSwitch;
use Ikromjon\ClipboardCore\Contracts\PrivacyGuard;
use Ikromjon\ClipboardCore\Contracts\SuppressionLog;
use Ikromjon\ClipboardCore\Guards\CompositeGuard;
use Ikromjon\ClipboardCore\Guards\MaxSizeGuard;
use Ikromjon\ClipboardCore\Guards\NotBlankGuard;
use Ikromjon\ClipboardCore\Guards\NotConcealedGuard;
use Ikromjon\ClipboardCore\Paste\CopyOnlyStrategy;
use Ikromjon\ClipboardCore\Repositories\DatabaseClipRepository;
use Ikromjon\ClipboardCore\Sources\ArrayClipboardSource;
use Ikromjon\ClipboardCore\Sources\NativePhpClipboardSource;
use Ikromjon\ClipboardCore\Support\FilePauseSwitch;
use Ikromjon\ClipboardCore\Support\FileSuppressionLog;
use Ikromjon\ClipboardCore\Support\Fingerprint;
use Ikromjon\ClipboardCore\Watcher\Cadence;
use Ikromjon\ClipboardCore\Watcher\ClipboardWatcher;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Native\Desktop\Facades\Clipboard as NativeClipboard;

class ClipboardCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/clipboard.php', 'clipboard');

        $this->bindContracts();
        $this->bindEngine();
    }

    public function boot(): void
    {
        // Loaded rather than published: the schema is an implementation
        // detail of the ring buffer, and a host that edits it will get
        // surprising behaviour from pruning and deduplication.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([WatchCommand::class]);

            $this->publishes([
                __DIR__.'/../config/clipboard.php' => config_path('clipboard.php'),
            ], 'clipboard-config');
        }
    }

    private function bindContracts(): void
    {
        // Falls back to an in-memory pasteboard when NativePHP is absent, so
        // the package installs and its tests run in a plain Laravel app.
        $this->app->bind(ClipboardSource::class, fn (): ClipboardSource => class_exists(NativeClipboard::class)
            ? new NativePhpClipboardSource
            : new ArrayClipboardSource);

        $this->app->bind(ClipRepository::class, DatabaseClipRepository::class);

        $this->app->bind(PauseSwitch::class, fn (): PauseSwitch => new FilePauseSwitch(
            $this->stringConfig('clipboard.pause_file', storage_path('app/clipboard-watcher.paused')),
        ));

        $this->app->bind(PrivacyGuard::class, fn (): PrivacyGuard => new CompositeGuard(
            new NotBlankGuard,
            new NotConcealedGuard,
            new MaxSizeGuard($this->intConfig('clipboard.max_bytes', 2_000_000)),
        ));

        $this->app->bind(SuppressionLog::class, fn (): SuppressionLog => new FileSuppressionLog(
            $this->stringConfig('clipboard.suppression_file', storage_path('app/clipboard-suppressions.json')),
            $this->intConfig('clipboard.suppression_ttl_seconds', 10),
        ));

        $this->app->bind(PasteStrategy::class, fn ($app): PasteStrategy => new CopyOnlyStrategy(
            $app->make(ClipboardSource::class),
            $app->make(SuppressionLog::class),
            $app->make(Fingerprint::class),
        ));
    }

    private function bindEngine(): void
    {
        $this->app->singleton(Fingerprint::class, fn (): Fingerprint => new Fingerprint(
            $this->intConfig('clipboard.hash_bytes', 65_536),
        ));

        $this->app->singleton(Cadence::class, fn (): Cadence => Cadence::fromConfig(
            $this->intArrayConfig('clipboard.cadence'),
        ));

        // A singleton because it carries the change-detection state that
        // makes polling cheap; a fresh instance every resolve would treat
        // every poll as a change.
        $this->app->singleton(ClipboardWatcher::class, fn ($app): ClipboardWatcher => new ClipboardWatcher(
            source: $app->make(ClipboardSource::class),
            clips: $app->make(ClipRepository::class),
            guard: $app->make(PrivacyGuard::class),
            fingerprint: $app->make(Fingerprint::class),
            events: $app->make(Dispatcher::class),
            cadence: $app->make(Cadence::class),
            suppressions: $app->make(SuppressionLog::class),
            limit: $this->intConfig('clipboard.limit', 100),
        ));

        // Bound rather than shared: it holds no state, and caching it would
        // pin whichever event dispatcher happened to exist at first resolve.
        $this->app->bind(ClipboardHistory::class, fn ($app): ClipboardHistory => new ClipboardHistory(
            clips: $app->make(ClipRepository::class),
            paste: $app->make(PasteStrategy::class),
            pause: $app->make(PauseSwitch::class),
            events: $app->make(Dispatcher::class),
            limit: $this->intConfig('clipboard.limit', 100),
        ));
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /** @return array<string, int> */
    private function intArrayConfig(string $key): array
    {
        $value = config($key, []);

        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $name => $number) {
            if (is_string($name) && is_numeric($number)) {
                $result[$name] = (int) $number;
            }
        }

        return $result;
    }
}
