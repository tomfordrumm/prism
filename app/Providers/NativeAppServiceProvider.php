<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        $queueConnection = config('queue.default');

        if ($queueConnection === 'redis' && ! extension_loaded('redis')) {
            $queueConnection = 'database';
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'cache.limiter' => 'file',
            'queue.default' => $queueConnection,
        ]);

        Window::open()
            ->title(config('app.name').' Desktop')
            ->width(1440)
            ->height(900)
            ->minWidth(1100)
            ->minHeight(700)
            ->rememberState();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
