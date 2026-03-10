<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\SuperAdmin;
use App\Models\Member;
class AdminAuthController extends Controller
{
    /**
     * @return mixed
     */
    public function login()
    {
        return Inertia::render('SuperAdmin/Auth/Login');
    }


    public function forgotPassword(Request $request){
                return Inertia::render('Auth/SuperForgetPassword', []);
    }


    /**
     * Super Amdin Login
     * @param Request $request
     * @return mixed
     */

    public function verify(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);
        $field = $this->determineLoginField($request->identifier);
        $user = SuperAdmin::where($field, $request->identifier)->first();
        if (!$user) {
            return back()->withErrors([
                'login' => 'The provided credentials do not match our records.',
            ])->onlyInput('login');
        }
        if (Auth::guard('superadmin')->attempt([$field => $request->identifier, 'password' => $request->password])) {
            $request->session()->regenerate();
            return redirect()->route('super.dashboard');
        }

        return back()->withErrors([
            'login' => 'The provided password is incorrect.',
        ])->onlyInput('login');
    }
    protected function determineLoginField($login): string
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if (is_numeric($login)) {
            return 'phone';
        }
        return 'username';
    }
}
