<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Http\Resources\ApiError\ApiErrorDetail;
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
            return ApiErrorDetail::response(ApiErrorCode::Unauthenticated, 401);
        }

        if (!$user->is_admin) {
            return ApiErrorDetail::response(ApiErrorCode::Forbidden, 403);
        }

        return $next($request);
    }
}
