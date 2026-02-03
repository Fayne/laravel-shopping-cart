<?php

namespace MobileNowGroup\ShoppingCart\Http\Middleware;

use MobileNowGroup\ShoppingCart\Models\Checkout;

class EnsureCheckoutIsValid
{
    public function handle($request, \Closure $next)
    {
        $checkout = Checkout::where('id', $request->route('checkout'))
            ->where('user_id', auth()->id())
            ->firstOrFail();

        abort_if($checkout->isExpired(), 410);
        abort_if(!$checkout->canProceed(), 409);

        $request->attributes->set('checkout', $checkout);

        return $next($request);
    }
}