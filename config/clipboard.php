<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | History limit
    |--------------------------------------------------------------------------
    |
    | How many unpinned clips to keep. Pinned clips are exempt and do not
    | consume slots. Pruning runs immediately after each capture, so the
    | table never grows beyond this bound plus the pinned set.
    |
    */

    'limit' => (int) env('CLIPBOARD_LIMIT', 100),

    /*
    |--------------------------------------------------------------------------
    | Table name
    |--------------------------------------------------------------------------
    */

    'table' => env('CLIPBOARD_TABLE', 'clips'),

    /*
    |--------------------------------------------------------------------------
    | Capture guards
    |--------------------------------------------------------------------------
    |
    | max_bytes  Clips larger than this are ignored outright; a clipboard
    |            manager is for snippets, and huge payloads are what make
    |            other managers feel heavy.
    | hash_bytes How much of the content is hashed to detect a change. The
    |            byte length is mixed in, so two clips must share both a
    |            length and this prefix to collide.
    |
    */

    'max_bytes' => (int) env('CLIPBOARD_MAX_BYTES', 2_000_000),

    'hash_bytes' => 65_536,

    /*
    |--------------------------------------------------------------------------
    | Poll cadence (milliseconds)
    |--------------------------------------------------------------------------
    |
    | macOS offers no pasteboard change notification, so the watcher polls.
    | It stays fast just after activity (people copy in bursts) and backs
    | off when idle, which is what keeps idle CPU near zero.
    |
    | The interval is also the miss window: a clip replaced before the next
    | poll is never seen, and a clipboard manager that loses clips is worse
    | than one that costs a little more battery. That is why `cold` is a
    | second rather than the several a pure power argument would suggest.
    |
    | hot_window_seconds  Stay at `hot` for this long after a change.
    | cold_after_seconds  Drop to `cold` once idle this long.
    |
    */

    'cadence' => [
        'hot' => (int) env('CLIPBOARD_CADENCE_HOT', 250),
        'warm' => (int) env('CLIPBOARD_CADENCE_WARM', 500),
        'cold' => (int) env('CLIPBOARD_CADENCE_COLD', 1_000),
        'hot_window_seconds' => 30,
        'cold_after_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pause switch
    |--------------------------------------------------------------------------
    |
    | The watcher runs in its own process, so pausing it has to cross a
    | process boundary. A sentinel file is the simplest thing that works
    | without a queue, a cache driver, or a socket.
    |
    */

    'pause_file' => storage_path('app/clipboard-watcher.paused'),

    /*
    |--------------------------------------------------------------------------
    | Self-write suppression
    |--------------------------------------------------------------------------
    |
    | When the app puts a clip back on the pasteboard, the watcher would
    | otherwise capture it as a fresh copy and reorder the history under the
    | user. The paste and the watcher happen in different processes, so the
    | note has to be left somewhere both can see.
    |
    | Entries expire so that an unconsumed suppression cannot silently
    | swallow a genuine copy of the same text later on.
    |
    */

    'suppression_file' => storage_path('app/clipboard-suppressions.json'),

    'suppression_ttl_seconds' => 10,

];
