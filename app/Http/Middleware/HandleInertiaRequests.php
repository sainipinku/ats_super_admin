<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use App\Models\FcmToken;

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
        }

        $notificationEnabled = collect();

        if ($user) {
            $notificationEnabled = FcmToken::where('user_id', $user->id)
                ->where('guard', $guard)
                ->pluck('device_id');
        }
        return array_merge(parent::share($request), [
            'messages' => flash()->render('array'),

            'auth' => [
                'user' => $user,
                'guard' => $guard,
            ],
                            'is_notification_enabled' => $notificationEnabled,
            'creds'  => [
                'apiKey' => env('FIREBASE_API_KEY'),
                'authDomain' => env('FIREBASE_AUTH_DOMAIN'),
                'projectId' => env('FIREBASE_PROJECT_ID'),
                'storageBucket' => env('FIREBASE_STORAGE_BUCKET'),
                'messagingSenderId' => env('FIREBASE_MESSAGING_SENDER_ID'),
                'appId' => env('FIREBASE_APP_ID'),
                'measurementId' => env('FIREBASE_MEASUREMENT_ID'),
                'vapidApiKey' => env('FIREBASE_VAPID_API_KEY')
            ],
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
