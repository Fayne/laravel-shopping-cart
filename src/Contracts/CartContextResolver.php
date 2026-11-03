<?php

namespace Fayne\ShoppingCart\Contracts;

interface CartContextResolver
{
    public function tenantId(): int|string;

    public function customerId(): int|string|null;

    /** Resolve the guest session on the server. Return null for authenticated customers. */
    public function sessionId(): ?string;
}
