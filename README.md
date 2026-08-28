# laravel-clipboard-core

The clipboard-watching and history engine behind [laravel-clipboard](https://github.com/Ikromjon1998/laravel-clipboard) — extracted so you can build your own clipboard tooling on top of it.

It captures clipboard changes, deduplicates them, prunes old ones, and dispatches events. It ships **no UI**: no windows, no tray, no hotkeys, no Blade. That is the point. Bring your own interface, or none at all.

```php
use Ikromjon\ClipboardCore\Facades\ClipboardHistory;

ClipboardHistory::recent(20);            // pinned first, then most recent
ClipboardHistory::search('composer');    // instant over a bounded history
ClipboardHistory::use($clip);            // hand it back to the system clipboard
```

## Why this exists

macOS provides no clipboard-change notification — every clipboard manager on the platform polls. Doing that well is more fiddly than it looks: you need change detection that stays cheap when nothing has changed, a cadence that does not burn battery while you sleep, deduplication that resurfaces rather than duplicates, a hard bound on storage growth, and a way to *not* record the password your password manager just put on the pasteboard.

This package is that engine, with the fiddly parts solved and tested.

## Installation

```bash
composer require ikromjon/laravel-clipboard-core
php artisan migrate
```

Then start the watcher. In a [NativePHP](https://nativephp.com) desktop app, run it as a supervised child process so a crash cannot take your UI down with it:

```php
// app/Providers/NativeAppServiceProvider.php
use Native\Desktop\Facades\ChildProcess;

ChildProcess::artisan('clipboard:watch', alias: 'watcher', persistent: true);
```

In a plain Laravel app, `php artisan clipboard:watch` is a normal long-running command — run it under Supervisor, systemd, or Horizon's process manager.

That is the whole integration. Everything below is optional.

## What you get

| Contract | Default | Swap it when |
| --- | --- | --- |
| `ClipboardSource` | `NativePhpClipboardSource` | You have a native helper, or you are testing |
| `ClipRepository` | `DatabaseClipRepository` | You want encryption, sync, or memory-only storage |
| `PrivacyGuard` | blank + concealed + size guards | You need per-app exclusions or secret detection |
| `PasteStrategy` | `CopyOnlyStrategy` | You can synthesise a paste keystroke |
| `PauseSwitch` | `FilePauseSwitch` | Your processes share a cache or socket instead |
| `SuppressionLog` | `FileSuppressionLog` | Same — it exists for the same reason |

Every default is bound in the container by interface, so replacing one is a single `$this->app->bind(...)` in your own provider.

### Events

| Event | Fired when |
| --- | --- |
| `ClipCaptured` | A clip was recorded (`$isNew` distinguishes new from resurfaced) |
| `ClipsPruned` | Old clips were dropped to stay within the limit |
| `ClipRejected` | A guard refused a snapshot — carries only a byte count, never content |
| `WatcherPaused` / `WatcherResumed` | Capture was paused or resumed |

These are plain events, deliberately. Broadcasting one to a desktop window is a host concern, so hosts listen and re-emit over whatever transport they use:

```php
Event::listen(ClipCaptured::class, fn ($event) => ClipboardUpdated::dispatch($event->clip));
```

## How the watcher behaves

Polling cadence adapts, because people copy in bursts and then not at all:

| Tier | Interval | When |
| --- | --- | --- |
| Hot | 250 ms | Within 30 s of the last change |
| Warm | 500 ms | Default |
| Cold | 1 s | After 5 minutes of silence |

A poll that finds nothing new costs one read and one hash of a bounded prefix — that is what keeps idle CPU near zero. Intervals are configurable in `config/clipboard.php`.

The interval is also a **miss window**: anything polling-based will lose a clip that gets replaced before the next poll. That is inherent to the platform, not to this package — macOS exposes no change notification, so every clipboard manager on it has some version of this window. The tiers above are chosen to keep that window at a second or less; closing it properly means a native helper watching `NSPasteboard.changeCount`, which is cheap enough to poll several times a second and can feed this engine through the same `ClipboardSource` contract.

Storage is a ring buffer. Pruning runs immediately after each capture, keeps the newest `limit` clips (default 100), and **never** touches pinned clips — they are exempt and do not consume slots. Re-copying something already in history bumps its recency and its copy count rather than inserting a duplicate.

### Self-writes

When you hand a clip back with `ClipboardHistory::use()`, the watcher would otherwise see it as a fresh copy and reorder the history under the user's cursor. `SuppressionLog` prevents that, and it is file-backed rather than in-memory for a specific reason: **the paste and the poll usually happen in different OS processes.** An in-memory note would be written in the UI process and read in the watcher process, which is to say never read at all. Entries expire after ten seconds so an unconsumed note cannot silently swallow a genuine copy of the same text later.

## Testing without a desktop

The `ClipboardSource` contract exists so the engine can be tested anywhere. `ArrayClipboardSource` is an in-memory pasteboard you queue values into:

```php
use Ikromjon\ClipboardCore\Sources\ArrayClipboardSource;
use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Watcher\ClipboardWatcher;

$clipboard = new ArrayClipboardSource;
$this->app->instance(ClipboardSource::class, $clipboard);

$clipboard->queue('hello', 'hello', 'world');

app(ClipboardWatcher::class)->tick();   // captures "hello"
app(ClipboardWatcher::class)->tick();   // null — nothing changed
app(ClipboardWatcher::class)->tick();   // captures "world"
```

The watcher exposes a single `tick()` rather than owning a loop, so tests never sleep. This package's own suite runs in under three seconds on Linux with no Electron and no desktop.

## Things you could build with it

- **Team clipboard** — broadcast `ClipCaptured` over WebSockets to a shared history
- **Snippet sync** — mirror pinned clips into a gist, a repo, or a notes app
- **Compliance auditing** — bind a `PrivacyGuard` that flags secrets leaving a machine
- **Form filler** — a repository of pinned templates, pasted by hotkey
- **Analytics** — `times_copied` is already tracked on every clip

## Privacy, honestly

Guards run before anything reaches the database, so a rejected clip leaves no trace on disk. `NotConcealedGuard` honours the [nspasteboard.org](https://nspasteboard.org) convention that password managers use to mark contents as concealed or transient.

**But it can only honour what the source reports.** Electron's clipboard API cannot read custom pasteboard types, so `NativePhpClipboardSource` always reports `concealed: false` and the guard passes everything through. Enforcing that convention requires a native helper that reads `org.nspasteboard.ConcealedType` and feeds it into a `ClipboardSnapshot`. Until you have one, treat "never logs passwords" as unenforced, and give users a visible pause control.

Clip content is stored as plaintext in your application's database, protected by file permissions. Encryption at rest is not provided — bind your own `ClipRepository` if you need it.

## Requirements

PHP 8.3+, Laravel 11/12/13. `nativephp/desktop` ^2.0 is optional: without it the package falls back to an in-memory source, so it installs and tests cleanly in a plain Laravel app.

## Contributing

Issues and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Run the checks with:

```bash
composer test      # Pest
composer analyse   # PHPStan level 8
composer lint      # Pint
```

## License

MIT. See [LICENSE](LICENSE).
