<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicFoldersDoNotShadowRoutesTest extends TestCase
{
    /**
     * Apache serves an existing public/ folder itself (403) instead of passing the request to Laravel,
     * so no folder in public/ may share its name with the first segment of a route.
     */
    public function test_no_public_folder_shares_a_name_with_a_route(): void
    {
        $folders = collect(File::directories(public_path()))->map(fn (string $path): string => basename($path));

        $routeSegments = collect(Route::getRoutes()->getRoutes())
            ->map(fn (RouteDefinition $route): string => explode('/', $route->uri())[0])
            ->reject(fn (string $segment): bool => $segment === '' || str_starts_with($segment, '{'))
            ->unique();

        $this->assertSame([], $folders->intersect($routeSegments)->values()->all());
    }
}
