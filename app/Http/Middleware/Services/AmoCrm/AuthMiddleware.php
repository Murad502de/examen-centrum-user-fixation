<?php

namespace App\Http\Middleware\Services\AmoCrm;

use App\Traits\Middleware\Services\AmoCRM\AmoTokenExpirationControlTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthMiddleware
{
    use AmoTokenExpirationControlTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (self::amoTokenExpirationControl()) {
            return $next($request);
        } else {
            return response()->json(
                ['message' => 'Access denied'],
                Response::HTTP_OK
            );
        }
    }
}
