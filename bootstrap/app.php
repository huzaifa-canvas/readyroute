<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\VerifySocketSecret;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(LocaleMiddleware::class);
        $middleware->api(ForceJsonResponse::class);

        $middleware->alias([
            'role'          => RoleMiddleware::class,
            'permission'    => EnsurePermission::class,
            'plan'          => EnsurePlanFeature::class,
            'subscribed'    => EnsureActiveSubscription::class,
            'socket.secret' => VerifySocketSecret::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin*')) {
                return route('login');
            }
            return route('login');
        });

        // Where the guest middleware sends someone already signed in, e.g.
        // opening /login again. Laravel's default is "/", which only works
        // because "/" knows each role's panel.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->panelHome() ?? '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
         * Every API failure is returned in the same envelope as every API
         * success — { status, message, data, errors } — so the mobile app has
         * one response shape to parse rather than one per error type.
         */
        $envelope = function (string $message, int $code, mixed $errors = null) {
            return response()->json([
                'status'  => false,
                'message' => $message,
                'data'    => null,
                'errors'  => $errors,
            ], $code);
        };

        $wantsApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (ValidationException $e, Request $request) use ($envelope, $wantsApi) {
            if ($wantsApi($request)) {
                return $envelope($e->validator->errors()->first(), 422, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($envelope, $wantsApi) {
            if ($wantsApi($request)) {
                return $envelope('Unauthenticated. Please sign in again.', 401);
            }

            return redirect()->guest(route('login'));
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($envelope, $wantsApi) {
            if ($wantsApi($request)) {
                return $envelope('You do not have access to this resource.', 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($envelope, $wantsApi) {
            if ($wantsApi($request)) {
                return $envelope('Resource not found.', 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($envelope, $wantsApi) {
            if ($wantsApi($request)) {
                return $envelope('Resource not found.', 404);
            }
        });

        /*
         * An expired CSRF token on a web form — typically a login page left
         * open past the session lifetime. A bare "419 Page Expired" leaves the
         * user stranded, so they go back to the form instead, with what they
         * typed (never the password) and a plain explanation. A stale logout
         * form just lands on the login page: the session it meant to end has
         * already ended.
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($wantsApi) {
            if ($e->getStatusCode() !== 419 || $wantsApi($request)) {
                return null;
            }

            if ($request->routeIs('logout')) {
                return redirect()->route('login');
            }

            return redirect()->back(fallback: route('login'))
                ->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']))
                ->withErrors(['session' => 'Your session expired while the page was open. Please try again.']);
        });

        // Anything else that carries an HTTP status — rate limiting, method not
        // allowed, maintenance mode — still comes back in the envelope.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($envelope, $wantsApi) {
            if ($wantsApi($request)) {
                $message = $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Request could not be completed.';

                return $envelope($message, $e->getStatusCode());
            }
        });
    })->create();
