<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Middleware;

use Engelsystem\Config\Config;
use Engelsystem\Middleware\RateLimitMiddleware;
use Psr\Log\LoggerInterface;

/**
 * Testable version that allows mocking APCu behavior
 */
class TestableRateLimitMiddleware extends RateLimitMiddleware
{
    public function __construct(
        Config $config,
        LoggerInterface $log,
        bool $apcuAvailable = false,
        protected int $mockAttempts = 0
    ) {
        parent::__construct($config, $log);
        $this->apcuAvailable = $apcuAvailable;
    }

    protected function isRateLimited(string $key, int $limit, int $window): bool
    {
        return $this->mockAttempts >= $limit;
    }
}
