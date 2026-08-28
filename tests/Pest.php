<?php

declare(strict_types=1);

use Ikromjon\ClipboardCore\ClipboardHistory;
use Ikromjon\ClipboardCore\Tests\TestCase;
use Ikromjon\ClipboardCore\Watcher\ClipboardWatcher;

uses(TestCase::class)->in(__DIR__);

/**
 * Resolved per call, so a test that fakes events before touching the
 * history gets an instance wired to the fake dispatcher.
 */
function history(): ClipboardHistory
{
    return app(ClipboardHistory::class);
}

/**
 * Resolve the engine under test. Always the container's singleton, so its
 * change-detection state survives across ticks the way it does in the
 * real watcher process.
 */
function watcher(): ClipboardWatcher
{
    return app(ClipboardWatcher::class);
}
