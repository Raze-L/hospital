<?php

namespace App\Http;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AntiSpamRegistrationMiddleware;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tymon\JWTAuth\Http\Middleware\Authenticate;

class Kernel extends HttpKernel
{
    /**
     * 应用程序的全局中间件堆栈.
     *
     * 这些中间件在每次请求应用程序时都会运行.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // ...
    ];

    /**
     * 应用程序的路由中间件组.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            // ...
        ],

        'api' => [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            SubstituteBindings::class,
        ],
    ];

    /**
     * 应用程序的路由中间件.
     *
     * 这些中间件可以分配给路由组或单个路由.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        // ...

        'anti.spam.registration' => AntiSpamRegistrationMiddleware::class,
        'throttle' => ThrottleRequests::class,
        'jwt.auth' => \App\Http\Middleware\JwtAuthenticate::class,
        'admin' => AdminMiddleware::class,

    ];
}
