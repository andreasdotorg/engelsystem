<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Middleware;

use Engelsystem\Config\Config;
use Engelsystem\Middleware\RateLimitMiddleware;
use Psr\Http\Message\ResponseInterface;
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

    /**
     * Expose protected method for testing
     */
    public function testIpInRange(string $ip, string $range): bool
    {
        return $this->ipInRange($ip, $range);
    }

    /**
     * Expose protected method for testing
     */
    public function testBuildKey(string $ip, string $endpoint): string
    {
        return $this->buildKey($ip, $endpoint);
    }

    /**
     * Expose protected method for testing
     */
    public function testCreateRateLimitResponse(int $retryAfter): ResponseInterface
    {
        return $this->createRateLimitResponse($retryAfter);
    }

    /**
     * Expose protected method for testing
     */
    public function testPathMatchesPattern(string $path, string $pattern): bool
    {
        return $this->pathMatchesPattern($path, $pattern);
    }
}
