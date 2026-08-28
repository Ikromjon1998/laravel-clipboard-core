<?php

declare(strict_types=1);

namespace Ikromjon\ClipboardCore\Tests;

use Ikromjon\ClipboardCore\ClipboardCoreServiceProvider;
use Ikromjon\ClipboardCore\Contracts\ClipboardSource;
use Ikromjon\ClipboardCore\Sources\ArrayClipboardSource;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** Public so Pest helper functions can reach it via test(). */
    public ArrayClipboardSource $clipboard;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test drives a fake pasteboard, which is the point of the
        // ClipboardSource contract: none of this needs a desktop.
        $this->clipboard = new ArrayClipboardSource;
        $this->app->instance(ClipboardSource::class, $this->clipboard);

        // Pause and suppression state live on disk so they can cross process
        // boundaries, which means they also outlive the container.
        foreach ([config('clipboard.pause_file'), config('clipboard.suppression_file')] as $path) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ClipboardCoreServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('clipboard.pause_file', sys_get_temp_dir().'/clipboard-core-test.paused');
        $app['config']->set('clipboard.suppression_file', sys_get_temp_dir().'/clipboard-core-test-suppressions.json');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
