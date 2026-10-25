<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login', ['adminMode' => false]);
    }

    public function showAdminLogin(): View
    {
        return view('auth.login', ['adminMode' => true]);
    }

    public function login(Request $request): RedirectResponse
    {
        return $this->authenticate($request, false);
    }

    public function loginAdmin(Request $request): RedirectResponse
    {
        return $this->authenticate($request, true);
    }

    private function authenticate(Request $request, bool $adminOnly): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, ! $adminOnly && $request->boolean('remember'))
            || ($adminOnly && ! Auth::user()->is_admin)) {
            if (Auth::check()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return back()->withErrors(['email' => 'The sign-in details were not accepted or this account does not have administrator access.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($adminOnly ? route('admin.analytics') : route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(9)->letters()->numbers()],
            'privacy_acknowledged' => ['accepted'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
