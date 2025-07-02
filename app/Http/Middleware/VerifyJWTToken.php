<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpFoundation\Response;

class VerifyJWTToken
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $header = $request->header('Authorization');
            if (!$header || !Str::startsWith($header, 'Bearer ')) {
                return response()->json(['error' => 'Token not provided'], 400);
            }

            $token = Str::after($header, 'Bearer ');
            JWTAuth::parseToken()->authenticate(); // 验证令牌
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token has expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token validation failed'], 400);
        }

        return $next($request);
    }
}
