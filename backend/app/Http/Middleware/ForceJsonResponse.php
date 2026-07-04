<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every API response carries the correct Content-Type header
 * (application/json) and that Laravel treats the request as expecting JSON,
 * so validation errors and exceptions are automatically serialised as JSON
 * rather than HTML.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Tell Laravel this request wants JSON — affects exception rendering,
        // redirect-on-validation-fail behaviour, etc.
        $request->headers->set('Accept', 'application/json');

        /** @var Response $response */
        $response = $next($request);

        // Ensure the Content-Type header is set correctly on every response
        if (! $response->headers->has('Content-Type') ||
            ! str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}
