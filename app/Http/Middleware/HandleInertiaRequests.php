<?php

namespace App\Http\Middleware;

use App\Models\Construction\Project;
use App\Services\Construction\ConstructionAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var ConstructionAuthorizationService $constructionAuthorization */
        $constructionAuthorization = app(ConstructionAuthorizationService::class);

        $user = null;
        $guard = null;

        if (Auth::guard('superadmin')->check()) {
            $user = Auth::guard('superadmin')->user();
            $guard = 'superadmin';
        } elseif (Auth::guard('admin')->check()) {
            $user = Auth::guard('admin')->user();
            $guard = 'admin';
        } elseif (Auth::guard('member')->check()) {
            $user = Auth::guard('member')->user();
            $guard = 'member';
        } elseif (Auth::guard('callingteam')->check()) {
            $user = Auth::guard('callingteam')->user();
            $guard = 'callingteam';
        }

        $constructionPermissions = $constructionAuthorization->permissionsFor($user);
        $genericPermissions = [];

        if ($user && method_exists($user, 'getAllPermissions')) {
            $genericPermissions = $user->getAllPermissions()->pluck('name')->all();
        }

        $mergedPermissions = collect($genericPermissions)
            ->merge($constructionPermissions)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Resolve the current project from the route (e.g. project show page)
        // or from the ?project= query parameter.
        // Route model binding resolves the parameter to a Project instance,
        // so handle both model instances and raw IDs.
        $currentProject = null;
        $route = $request->route();
        $routeProjectParam = $route ? $route->parameter('project') : null;

        if ($routeProjectParam instanceof Project) {
            $currentProject = $routeProjectParam->load('client', 'company');
        } elseif ($routeProjectParam) {
            $currentProject = Project::with('client', 'company')
                ->find($routeProjectParam);
        }

        if ($currentProject === null && $request->query('project')) {
            $currentProject = Project::with('client', 'company')
                ->find($request->query('project'));
        }

        return array_merge(parent::share($request), [
            'messages' => flash()->render('array'),

            'auth' => [
                'user' => $user,
                'guard' => $guard,
                'permissions' => $mergedPermissions,
                'construction_permissions' => $constructionPermissions,
            ],
            'current_project' => $currentProject
                ? [
                    'id' => $currentProject->id,
                    'name' => $currentProject->name,
                    'project_code' => $currentProject->project_code,
                    'status' => $currentProject->status,
                    'current_stage' => $currentProject->current_stage,
                    'client' => $currentProject->client?->name,
                    'company' => $currentProject->company?->name,
                ]
                : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                // 'error' => $request->session()->get('errors')
                //     ? null
                //     : $request->session()->get('error'),
                // 'warning' => $request->session()->get('warning'),
                // 'info' => $request->session()->get('info'),
            ],

            // 'errors' => $request->session()->get('errors')
            //     ? $request->session()->get('errors')->getBag('default')->getMessages()
            //     : (object)[],
        ]);
    }
}
