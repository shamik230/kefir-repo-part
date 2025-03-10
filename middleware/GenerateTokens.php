<?php namespace Marketplace\Tokens\Middleware;

use Closure;
use Request;
use Response;

class GenerateTokens
{
    public function handle($request, Closure $next)
    {
        if (Request::header('Authorization') != 'Bearer ' . env('TOKEN_GENERATE')) {

            return Response::make(['error' => "Not authorized"], 401);

        }
        return $next($request);
    }
}
