<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at instanceof CarbonInterface
                        ? $user->email_verified_at->toISOString()
                        : null,
                    'created_at' => $user->created_at?->toISOString(),
                    'updated_at' => $user->updated_at?->toISOString(),
                    'chat_enter_behavior' => $user->chat_enter_behavior ?? 'send',
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'tenancy' => $this->tenancyPayload($request),
            'projects' => $this->projectsPayload($request),
            'currentProjectUuid' => $this->currentProjectUuid($request),
        ];
    }

    private function tenancyPayload(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [
                'currentTenant' => null,
                'tenantsCount' => 0,
                'needsTenant' => false,
            ];
        }

        $tenantsCount = $user->tenants()->count();
        $currentTenant = currentTenant();

        return [
            'currentTenant' => $currentTenant ? [
                'id' => $currentTenant->id,
                'name' => $currentTenant->name,
            ] : null,
            'tenantsCount' => $tenantsCount,
            'needsTenant' => $tenantsCount === 0,
        ];
    }

    private function projectsPayload(Request $request): array
    {
        if (! $request->user() || ! currentTenantId()) {
            return [];
        }

        return Project::query()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
            ])
            ->all();
    }

    private function currentProjectUuid(Request $request): ?string
    {
        $routeProject = $request->route('project');

        if ($routeProject instanceof Project) {
            return $routeProject->uuid;
        }

        if (is_string($routeProject) && ! is_numeric($routeProject)) {
            return $routeProject;
        }

        return null;
    }
}
