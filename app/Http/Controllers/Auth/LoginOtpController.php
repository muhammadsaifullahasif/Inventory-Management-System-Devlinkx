<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginOtpController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showForm(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        if (! $request->session()->has('login_otp_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.login-otp');
    }

    public function verify(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $userId = $request->session()->get('login_otp_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $otp = LoginOtp::where('user_id', $userId)
            ->where('code', $request->input('code'))
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->isExpired()) {
            return back()->withErrors(['code' => 'That code is invalid or has expired.']);
        }

        $otp->update(['used_at' => now()]);

        $user = User::findOrFail($userId);
        $remember = (bool) $request->session()->pull('login_otp_remember', false);
        $request->session()->forget('login_otp_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
