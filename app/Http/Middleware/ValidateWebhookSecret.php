<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookSecret
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('services.webhook.secret');

        if (empty($expectedSecret)) {
            return response()->json([
                'status' => false,
                'message' => 'Server error: Webhook secret is not configured.',
            ], 500);
        }

        // Check X-Webhook-Secret header or Bearer token
        $providedSecret = $request->header('X-Webhook-Secret') ?? $request->bearerToken();

        if (empty($providedSecret) || ! hash_equals((string) $expectedSecret, (string) $providedSecret)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Invalid or missing webhook secret token.',
            ], 401);
        }

        return $next($request);
    }
}
