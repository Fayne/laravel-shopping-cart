<?php

namespace Fayne\ShoppingCart\Tests\Fixtures;

use Fayne\ShoppingCart\Traits\CartPerformanceMonitoring;

/** Exposes the optional trait to package static analysis. */
class PerformanceMonitorProbe
{
    use CartPerformanceMonitoring;
}
