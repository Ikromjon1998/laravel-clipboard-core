# laravel-clipboard-core

[![Latest version on Packagist](https://img.shields.io/packagist/v/ikromjon/laravel-clipboard-core.svg?style=flat-square)](https://packagist.org/packages/ikromjon/laravel-clipboard-core)
[![Tests](https://img.shields.io/github/actions/workflow/status/Ikromjon1998/laravel-clipboard-core/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Ikromjon1998/laravel-clipboard-core/actions/workflows/tests.yml)
[![Quality](https://img.shields.io/github/actions/workflow/status/Ikromjon1998/laravel-clipboard-core/quality.yml?branch=main&label=phpstan%20%2B%20pint&style=flat-square)](https://github.com/Ikromjon1998/laravel-clipboard-core/actions/workflows/quality.yml)
[![Total downloads](https://img.shields.io/packagist/dt/ikromjon/laravel-clipboard-core.svg?style=flat-square)](https://packagist.org/packages/ikromjon/laravel-clipboard-core)
[![License](https://img.shields.io/packagist/l/ikromjon/laravel-clipboard-core.svg?style=flat-square)](LICENSE)

Clipboard history for Laravel: capture what gets copied, deduplicate it, prune it, and react to it.

This is the engine behind [laravel-clipboard](https://github.com/Ikromjon1998/laravel-clipboard), a macOS menu bar clipboard manager, extracted so you can build your own clipboard tooling on the same foundation. It ships **no UI** — no windows, no tray, no hotkeys, no Blade. Bring your own interface, or none at all.

```php
use Ikromjon\ClipboardCore\Facades\ClipboardHistory;

ClipboardHistory::recent(20);            // pinned first, then most recent
ClipboardHistory::search('composer');    // instant over a bounded history
ClipboardHistory::use($clip);            // hand it back to the system clipboard
```

## Why this exists

macOS provides no clipboard-change notification, so every clipboard manager on the platform polls. Doing that well is fiddlier than it looks. You need change detection that stays cheap when nothing changed, a cadence that does not drain the battery while you sleep, deduplication that resurfaces rather than duplicates, a hard bound on storage growth, and a way to *not* record the password your password manager just put on the pasteboard.

This package is that engine, with those parts solved and tested.

## Installation

```bash
composer require ikromjon/laravel-clipboard-core
php artisan migrate
```

> In a NativePHP desktop app use `php artisan native:migrate` instead — NativePHP runs on its own database connection, and plain `migrate` will quietly migrate the wrong one.

Then start the watcher. In a [NativePHP](https://nativephp.com) app, run it as a supervised child process so a crash cannot take your UI down with it:

```php
// app/Providers/NativeAppServiceProvider.php
use Native\Desktop\Facades\ChildProcess;

ChildProcess::artisan('clipboard:watch', alias: 'watcher', persistent: true);
```

In a plain Laravel app, `php artisan clipboard:watch` is an ordinary long-running command — run it under Supervisor, systemd, or any process manager.

That is the whole integration. Everything below is optional.

## Usage

### Reading history

```php
use Ikromjon\ClipboardCore\Facades\ClipboardHistory;

ClipboardHistory::recent();          // pinned clips first, then by recency
ClipboardHistory::recent(10);        // cap the number returned
ClipboardHistory::search('SELECT');  // case-insensitive; % and _ match literally
ClipboardHistory::find($id);
ClipboardHistory::count();
```

Each result is a `Clip` model:

```php
$clip->content;         // the full text
$clip->preview(80);     // single-line, length-capped, for list rows
$clip->kind;            // ClipKind::Text or ClipKind::Url
$clip->pinned;          // exempt from pruning
$clip->times_copied;    // incremented each time it is copied again
$clip->last_copied_at;
```

### Managing clips

```php
ClipboardHistory::use($clip);            // put it back on the clipboard
ClipboardHistory::pin($id);              // exempt from pruning, sorts first
ClipboardHistory::pin($id, false);
ClipboardHistory::forget($id);
ClipboardHistory::clear();               // unpinned only
ClipboardHistory::clear(includePinned: true);
```

### Pausing capture

```php
ClipboardHistory::pause();
ClipboardHistory::isPaused();
ClipboardHistory::resume();
ClipboardHistory::toggle();
```

Pausing crosses a process boundary, so it works even though the watcher runs separately. On resume, whatever was copied during the pause is treated as already-seen and is never recorded.

### Reacting to events

| Event | Fired when |
| --- | --- |
| `ClipCaptured` | A clip was recorded — `$clip`, and `$isNew` to distinguish new from resurfaced |
| `ClipsPruned` | Old clips were dropped — `$removed`, `$limit` |
| `ClipRejected` | A guard refused a snapshot — `$bytes` only, never content |
| `WatcherPaused` / `WatcherResumed` | Capture was paused or resumed |

These are plain Laravel events, deliberately. Broadcasting one to a desktop window is a host concern, so hosts listen and re-emit over whatever transport they use:

```php
use Ikromjon\ClipboardCore\Events\ClipCaptured;

Event::listen(ClipCaptured::class, function (ClipCaptured $event) {
    ClipboardUpdated::dispatch($event->clip);   // your own broadcast event
});
```

## Configuration

Publish the config if you want to change the defaults:

```bash
php artisan vendor:publish --tag=clipboard-config
```

```php
'limit'      => 100,          // unpinned clips to keep; pinned are exempt
'max_bytes'  => 2_000_000,    // clips larger than this are ignored
'cadence'    => [
    'hot'  => 250,            // ms, within 30s of the last change
    'warm' => 500,            // ms, default
    'cold' => 1_000,          // ms, after 5 minutes of silence
],
```

## How the watcher behaves

Polling adapts, because people copy in bursts and then not at all:

| Tier | Interval | When |
| --- | --- | --- |
| Hot | 250 ms | Within 30 s of the last change |
| Warm | 500 ms | Default |
| Cold | 1 s | After 5 minutes of silence |

A poll that finds nothing new costs one read and one hash of a bounded prefix, which is what keeps idle CPU near zero.

The interval is also a **miss window**: anything polling-based loses a clip replaced before the next poll. That is inherent to the platform rather than to this package — macOS exposes no change notification, so every clipboard manager on it has some version of this window. The tiers above keep it at a second or less. Closing it properly means a native helper watching `NSPasteboard.changeCount`, which is cheap enough to poll several times a second and can feed this engine through the same `ClipboardSource` contract.

Storage is a ring buffer. Pruning runs immediately after each capture, keeps the newest `limit` clips, and never touches pinned ones. Re-copying something already in history bumps its recency and copy count instead of inserting a duplicate.

### Self-writes

When you hand a clip back with `use()`, the watcher would otherwise see it as a fresh copy and reorder the history under the user's cursor. `SuppressionLog` prevents that, and it is file-backed rather than in-memory for a specific reason: **the paste and the poll usually happen in different OS processes.** An in-memory note would be written in the UI process and read in the watcher process, which is to say never read at all. Entries expire after ten seconds, so an unconsumed note cannot silently swallow a genuine copy of the same text later.

## Replacing behaviour

Every default is bound by interface, so swapping one is a single `bind()` in your own service provider.

| Contract | Default | Swap it when |
| --- | --- | --- |
| `ClipboardSource` | `NativePhpClipboardSource`, or `ProcessClipboardSource` when a probe is configured | You are testing, or reading from somewhere else entirely |
| `ClipRepository` | `DatabaseClipRepository` | You want encryption, sync, or memory-only storage |
| `PrivacyGuard` | blank + concealed + size guards | You need per-app exclusions or secret detection |
| `PasteStrategy` | `CopyOnlyStrategy` | You can synthesise a paste keystroke |
| `PauseSwitch` | `FilePauseSwitch` | Your processes share a cache or socket instead |
| `SuppressionLog` | `FileSuppressionLog` | Same — it exists for the same reason |

```php
$this->app->bind(PrivacyGuard::class, fn () => new CompositeGuard(
    new NotBlankGuard,
    new MaxSizeGuard(500_000),
    new NeverFromPasswordManagers,   // your own
));
```

## Seeing what the runtime cannot

Some pasteboard state is invisible to a cross-platform runtime. On macOS, applications mark secret contents with custom pasteboard types that Electron does not expose — which is exactly the information a clipboard manager needs in order to *not* record your passwords.

`ProcessClipboardSource` reads from a long-running helper that can see them, over a deliberately small protocol: one JSON object per line on stdout.

```
{"change":42,"concealed":false,"app":"Safari","text":"hello"}
{"change":43,"concealed":true,"app":"1Password"}
{"change":44,"concealed":false,"app":"Xcode","oversize":true}
```

| Field | Meaning |
| --- | --- |
| `change` | Monotonic counter; emitted only when something changed |
| `concealed` | The owning application marked this secret — **omit `text`** |
| `app` | Frontmost application, for exclusion rules (optional) |
| `text` | The contents; absent when concealed, oversize, or not text |
| `oversize` | Text existed but exceeded the helper's byte ceiling |

Point the package at one and it takes over reading; writes continue through the runtime, because a probe only observes:

```php
// config/clipboard.php
'probe' => ['command' => ['/path/to/clipboard-probe', '--interval-ms', '150']],
```

The protocol is intentionally implementable in any language. A reference macOS helper in ~140 lines of Swift lives in [laravel-clipboard](https://github.com/Ikromjon1998/laravel-clipboard/blob/main/native/ClipboardProbe.swift); it polls `NSPasteboard.changeCount`, which is a plain integer, so idle cost is one comparison and it can poll several times a second without measurable expense.

## Testing without a desktop

The `ClipboardSource` contract exists so the engine can be tested anywhere. `ArrayClipboardSource` is an in-memory pasteboard you queue values into:

```php
use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Sources\ArrayClipboardSource;
use Ikromjon\ClipboardCore\Watcher\ClipboardWatcher;

$clipboard = new ArrayClipboardSource;
$this->app->instance(ClipboardSource::class, $clipboard);

$clipboard->queue('hello', 'hello', 'world');

app(ClipboardWatcher::class)->tick();   // captures "hello"
app(ClipboardWatcher::class)->tick();   // null — nothing changed
app(ClipboardWatcher::class)->tick();   // captures "world"
```

The watcher exposes a single `tick()` rather than owning a loop, so the caller controls the sleeping and tests never wait. This package's own suite runs in about two seconds on Linux, with no Electron and no desktop.

## Things you could build with it

- **Team clipboard** — broadcast `ClipCaptured` over WebSockets to a shared history
- **Snippet sync** — mirror pinned clips into a gist, a repo, or a notes app
- **Compliance auditing** — bind a `PrivacyGuard` that flags secrets leaving a machine
- **Form filler** — a repository of pinned templates, pasted by hotkey
- **Analytics** — `times_copied` is already tracked on every clip

## Privacy, honestly

Guards run before anything reaches the database, so a rejected clip leaves no trace on disk. `NotConcealedGuard` honours the [nspasteboard.org](https://nspasteboard.org) convention that password managers use to mark contents concealed or transient.

**It can only honour what the source reports**, and that depends on which source you use.

`NativePhpClipboardSource` reads through Electron, which cannot see custom pasteboard types. It therefore always reports `concealed: false`, the guard passes everything through, and copies from a password manager *are* recorded. If that is your setup, treat "never logs passwords" as unenforced and give users a visible pause control.

`ProcessClipboardSource` fixes this by reading from a native helper that can see those types. The guarantee is structural rather than a check: a conforming helper never emits the text of a concealed item, so secret content does not enter PHP at all and no bug on this side can write it to disk. As a second line of defence, text arriving alongside `concealed: true` is discarded on arrival anyway.

Clip content is stored as plaintext in your application's database, protected by file permissions. Encryption at rest is not provided — bind your own `ClipRepository` if you need it.

## Requirements

PHP 8.3+, Laravel 12 or 13.

`nativephp/desktop` ^2.0 is optional. Without it the package falls back to an in-memory source, so it installs and its tests run in a plain Laravel app with no desktop runtime.

## Testing

```bash
composer test       # Pest
composer analyse    # PHPStan, level 8, no baseline
composer lint       # Pint
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what has changed recently.

## Contributing

Pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). You do not need macOS or a desktop runtime to work on this package.

## Security

If you find a vulnerability that could expose clipboard contents, please email ikromjon98.98@icloud.com rather than opening a public issue. See [SECURITY.md](SECURITY.md).

## Credits

- [Ikromjon Ochilov](https://github.com/Ikromjon1998)
- [All contributors](https://github.com/Ikromjon1998/laravel-clipboard-core/contributors)

Built on [NativePHP](https://nativephp.com), which makes desktop applications in PHP possible in the first place.

## License

MIT. See [LICENSE](LICENSE).
