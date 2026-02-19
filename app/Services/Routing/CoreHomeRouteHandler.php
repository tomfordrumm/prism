<?php

namespace App\Services\Routing;

use App\Services\Routing\Contracts\HomeRouteHandlerInterface;
use Illuminate\Http\RedirectResponse;

class CoreHomeRouteHandler implements HomeRouteHandlerInterface
{
    public function handle(): RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('projects.index');
        }

        return redirect()->route('login');
    }
}
