<?php

use App\Http\Middleware\ContractorMiddleware;
use App\Http\Middleware\EnsureConstructionPermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PartnerMiddleware;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
        '*',
    ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'auth.superadmin' => SuperAdminMiddleware::class,
            'authorized' => RedirectIfAuthenticated::class,
            'member' => \App\Http\Middleware\MemberMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'callingteam' => \App\Http\Middleware\CallingTeamMiddleware::class,
            'construction.permission' => EnsureConstructionPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, \Illuminate\Http\Request $request) {
            $maxPost = ini_get('post_max_size') ?: 'unknown';
            $maxUpload = ini_get('upload_max_filesize') ?: 'unknown';

            if ($request->is('api/*') || $request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PAYLOAD_TOO_LARGE',
                    'message' => "The uploaded file(s) or data is too large. Server limit is {$maxPost} (max single file: {$maxUpload}).",
                    'max_post_size' => $maxPost,
                    'max_upload_size' => $maxUpload,
                ], 413);
            }

            return back()->withInput()->withErrors([
                'file' => "The uploaded file or form data is too large. Maximum allowed size is {$maxPost}.",
            ])->with('error', "The uploaded file or form data is too large. Maximum allowed size is {$maxPost}.");
        });

        $exceptions->render(function (\Laravel\Sanctum\Exceptions\MissingAbilityException $e, \Illuminate\Http\Request $request) {
            return response()->json([
                'success' => false,
                'error_code' => 'INSUFFICIENT_TOKEN_ABILITIES',
                'message' => 'Token does not have the required permissions to perform this action.',
                'required_abilities' => $e->abilities(),
            ], 403);
        });

        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, \Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });
    })
    ->create();
