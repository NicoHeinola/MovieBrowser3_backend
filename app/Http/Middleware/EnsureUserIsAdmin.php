<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'code' => ApiErrorCode::Unauthenticated,
                'params' => (object) [],
            ], 401);
        }

        if (!$user->is_admin) {
            return response()->json([
                'code' => ApiErrorCode::Forbidden,
                'params' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
