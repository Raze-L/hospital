<?php
namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JwtAuthenticate
{
    public function handle($request, Closure $next)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['error' => '用户不存在'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => '令牌已过期'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => '无效令牌'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => '缺少令牌'], 401);
        }

        return $next($request);
    }
}
