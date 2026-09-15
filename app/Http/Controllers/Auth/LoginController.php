<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use App\Models\LoginOtp;
use App\Notifications\LoginOtpNotification;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Credentials are validated but the session is not established here —
     * a one-time code is emailed to the configured verification addresses
     * (never to the user's own email) and login only completes once that
     * code is confirmed in LoginOtpController::verify().
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        if (method_exists($this, 'hasTooManyLoginAttempts') && $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if (! $this->guard()->validate($this->credentials($request))) {
            $this->incrementLoginAttempts($request);

            return $this->sendFailedLoginResponse($request);
        }

        $this->clearLoginAttempts($request);

        $user = $this->guard()->getLastAttempted();

        $settings = GeneralSetting::current();
        $recipients = collect(explode(',', (string) $settings->login_auth_emails))
            ->merge([$settings->admin_email])
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        // No verification address configured yet — fall back to a normal
        // login rather than locking every user out of the app.
        if ($recipients->isEmpty()) {
            $this->guard()->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended($this->redirectPath());
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        LoginOtp::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);

        $otp = LoginOtp::create([
            'user_id' => $user->id,
            'code' => $code,
            'ip_address' => $request->ip(),
            'expires_at' => now()->addMinutes(10),
        ]);

        try {
            Notification::route('mail', $recipients->all())
                ->notify(new LoginOtpNotification($user, $code, $request->ip(), now()));
        } catch (\Throwable $e) {
            Log::error('Login OTP email failed to send: '.$e->getMessage());

            $otp->update(['used_at' => now()]);

            throw ValidationException::withMessages([
                $this->username() => 'Could not send the login verification code. Contact your administrator.',
            ]);
        }

        $request->session()->put('login_otp_user_id', $user->id);
        $request->session()->put('login_otp_remember', $request->boolean('remember'));

        return redirect()->route('login.otp.form');
    }
}
