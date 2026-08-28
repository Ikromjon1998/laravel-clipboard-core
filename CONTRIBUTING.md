# Contributing

Thanks for considering a contribution. This package aims to be a readable reference for how a NativePHP desktop engine can be structured, so clarity counts as much as correctness here.

## Getting set up

```bash
git clone git@github.com:Ikromjon1998/laravel-clipboard-core.git
cd laravel-clipboard-core
composer install
composer test
```

You do **not** need macOS, Electron, or NativePHP to work on this package. The test suite drives an in-memory clipboard through the `ClipboardSource` contract and runs anywhere PHP does. If a change you are making cannot be tested that way, that is usually a sign it belongs in the app repo rather than here.

## Before opening a pull request

```bash
composer lint       # Pint, Laravel preset
composer analyse    # PHPStan level 8, must stay clean
composer test       # Pest
```

All three run in CI. PHPStan runs at level 8 with no baseline — please fix the cause rather than adding ignores.

## What belongs here

This package owns **capturing and storing** clipboard history. It does not own showing it. Windows, tray icons, hotkeys, views, and licensing live in the app that consumes this package.

A good test of whether something belongs here: could a developer building a completely different clipboard tool — a team clipboard, a compliance auditor, a snippet manager — reasonably want it? If it only makes sense for one particular menu-bar app, it belongs in that app.

## Conventions

- `declare(strict_types=1)` in every file.
- New behaviour is introduced behind a contract when a host might reasonably want to replace it.
- Comments explain *why*, not *what*. If a line needs a comment to say what it does, prefer rewriting the line.
- Events carry no sensitive content beyond what a host already has access to — `ClipRejected` deliberately reports only a byte count.

## Reporting a security issue

Please do not open a public issue for anything that could expose users' clipboard contents. Email ikromjon98.98@icloud.com instead.
