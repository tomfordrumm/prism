<?php

namespace App\Services\Routing\Contracts;

use Symfony\Component\HttpFoundation\Response;

interface HomeRouteHandlerInterface
{
    public function handle(): Response;
}
