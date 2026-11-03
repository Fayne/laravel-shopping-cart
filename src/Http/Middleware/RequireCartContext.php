<?php

namespace Fayne\ShoppingCart\Http\Middleware;

use Closure;
use Fayne\ShoppingCart\Contracts\CartContextResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCartContext
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->bound(CartContextResolver::class), 503);
        $context = app(CartContextResolver::class);
        abort_if(trim((string) $context->tenantId()) === '', 503);
        abort_if($context->customerId() === null && trim((string) $context->sessionId()) === '', 401);

        return $next($request);
    }
}
