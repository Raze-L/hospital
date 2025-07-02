<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AntiSpamRegistrationMiddleware
{
    /**
     * 处理传入的请求.
     *
     * @param Request $request
     * @param Closure(Request): (Response) $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 从请求中获取邮箱地址
        $email = $request->input('email');
        if (!$email) {
            return response()->json(['error' => 'Email address is required for registration.'], 400);
        }

        $key ='registration:' . $email;

        // 限制每个邮箱最多注册 3 个账号，使用永久的限制（没有时间限制）
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['error' => 'You have reached the maximum number of registrations with this email. Please use another email or contact support.'], 429);
        }

        // 记录一次注册尝试，使用永久的限制（没有时间限制）
        RateLimiter::hit($key, 0);

        return $next($request);
    }
}



