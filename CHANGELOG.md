# Changelog

All notable changes to `laravel-clipboard-core` are documented here.

This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Until 1.0.0, minor versions may contain breaking changes to contracts; patch versions will not.

## v0.1.1 - 2026-08-29

### Fixed

- A clip copied immediately after resuming could be silently dropped. The watcher only observes a resume on its next poll, and it used to baseline change detection at that moment — so anything copied in the gap was mistaken for paused-era content. `ClipboardHistory::resume()` now records the clipboard as it stood when the user resumed, which is the only point in the sequence without a race.

## v0.1.0 - 2026-08-29

First release. The engine is complete and tested; contracts may still change before 1.0.

### Supported versions

- PHP 8.3+, Laravel 12 and 13. Laravel 11 is not supported: every 11.x release is now flagged by a security advisory, so Composer refuses to install it under its default policy.

### Added

- `ClipboardSource` contract with a NativePHP implementation and an in-memory `ArrayClipboardSource` for testing.
- `ClipboardWatcher` engine exposing a single `tick()`, with fingerprint-based change detection, self-write suppression, and adaptive `Cadence` (hot/warm/cold).
- `ClipRepository` contract and a SQLite-friendly `DatabaseClipRepository` implementing ring-buffer pruning that exempts pinned clips, and upsert deduplication that resurfaces repeats.
- `PrivacyGuard` contract with blank, size, and nspasteboard concealed-type guards, composed so any one refusal blocks a capture.
- `PasteStrategy` contract with a permission-free `CopyOnlyStrategy` default.
- `PauseSwitch` contract with a file-based implementation for cross-process pausing.
- `SuppressionLog` contract with an expiring, file-based implementation, so a clip pasted from history is not recaptured by a watcher running in another process.
- `ClipboardHistory` service and facade as the package's front door.
- `clipboard:watch` command with `--once` and `--ticks=` for testing.
- Events: `ClipCaptured`, `ClipsPruned`, `ClipRejected`, `WatcherPaused`, `WatcherResumed`.
