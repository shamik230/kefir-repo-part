<?php namespace Marketplace\Tokens;

use Closure;

class BlockchainMintMiddleware
{
    public function handle($request, Closure $next)
    {

        // if(!Request::header('token') && Request::header('token') != env('TOKEN')){
        //     return Response::make(['error'=> "Not authorized"], 401);

        // }
        return $next($request);
    }
}
