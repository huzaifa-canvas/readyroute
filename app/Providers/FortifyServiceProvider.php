<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse;
use Illuminate\Validation\ValidationException;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse {
                public function toResponse($request)
                {
                    $user = auth()->user();
                    if ($user->isAdmin()) {
                        return redirect()->intended('/admin');
                    }
                    if ($user->isDispatcher()) {
                        return redirect()->intended('/dispatcher');
                    }
                    // Drivers cannot login to web panel
                    auth()->logout();
                    throw ValidationException::withMessages([
                        'email' => ['Drivers can only log in using the mobile app.'],
                    ]);
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(function () {
            return view('content.authentications.auth-login-cover');
        });

        Fortify::registerView(function () {
            return view('content.authentications.auth-register-cover');
        });

        Fortify::requestPasswordResetLinkView(function () {
            return view('content.authentications.auth-forgot-password-cover');
        });

        Fortify::resetPasswordView(function (Request $request) {
            return view('content.authentications.auth-reset-password-cover', ['request' => $request]);
        });

        // Authenticate using check
        Fortify::authenticateUsing(function (Request $request) {
            $user = \App\Models\User::where('email', $request->email)->first();

            if ($user && \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                if ($user->isDriver()) {
                    throw ValidationException::withMessages([
                        'email' => ['Drivers can only log in using the mobile app.'],
                    ]);
                }
                return $user;
            }
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
