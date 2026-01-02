<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Middleware;

use Engelsystem\Config\Config;
use Engelsystem\Http\Response;
use Engelsystem\Middleware\RateLimitMiddleware;
use Engelsystem\Test\Unit\Middleware\TestableRateLimitMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * @covers \Engelsystem\Middleware\RateLimitMiddleware
 */
class RateLimitMiddlewareTest extends TestCase
{
    protected Config $config;
    protected LoggerInterface|MockObject $log;
    protected ServerRequestInterface|MockObject $request;
    protected RequestHandlerInterface|MockObject $handler;
    protected UriInterface|MockObject $uri;
    protected Response $response;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new Config();
        $this->log = $this->createMock(LoggerInterface::class);
        $this->request = $this->getMockForAbstractClass(ServerRequestInterface::class);
        $this->handler = $this->getMockForAbstractClass(RequestHandlerInterface::class);
        $this->uri = $this->getMockForAbstractClass(UriInterface::class);
        $this->response = new Response();

        $this->request->method('getUri')->willReturn($this->uri);
        $this->handler->method('handle')->willReturn($this->response);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::__construct
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::process
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::isEnabled
     */
    public function testDisabledRateLimiting(): void
    {
        $this->config->set('rate_limits', ['enabled' => false]);

        $middleware = new RateLimitMiddleware($this->config, $this->log);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::process
     */
    public function testNoApcuAvailable(): void
    {
        $this->config->set('rate_limits', ['enabled' => true]);

        $this->log->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('APCu not available'));

        // Use TestableRateLimitMiddleware with apcuAvailable = false to test the degradation path
        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, false);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::process
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::matchEndpoint
     */
    public function testNonMatchingEndpoint(): void
    {
        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/login' => ['limit' => 5, 'window' => 300],
            ],
        ]);

        $this->uri->method('getPath')->willReturn('/some-other-path');

        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, true);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::process
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::matchEndpoint
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::pathMatchesPattern
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::getClientIp
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::buildKey
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::isRateLimited
     */
    public function testAllowedRequest(): void
    {
        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/login' => ['limit' => 5, 'window' => 300],
            ],
        ]);

        $this->uri->method('getPath')->willReturn('/login');
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.1.1']);
        $this->request->method('getHeaderLine')->willReturn('');

        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, true, 0);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::process
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::isRateLimited
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::createRateLimitResponse
     */
    public function testBlockedRequest(): void
    {
        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/login' => ['limit' => 5, 'window' => 300],
            ],
        ]);

        $this->uri->method('getPath')->willReturn('/login');
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.1.1']);
        $this->request->method('getHeaderLine')->willReturn('');

        $this->log->expects($this->once())
            ->method('info')
            ->with('Rate limit exceeded', $this->anything());

        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, true, 10);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertEquals(429, $result->getStatusCode());
        $this->assertTrue($result->hasHeader('Retry-After'));
        $this->assertEquals('300', $result->getHeaderLine('Retry-After'));
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::pathMatchesPattern
     */
    public function testWildcardEndpointMatching(): void
    {
        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/api/' => ['limit' => 100, 'window' => 60],
            ],
        ]);

        $this->uri->method('getPath')->willReturn('/api/v1/users');
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.1.1']);
        $this->request->method('getHeaderLine')->willReturn('');

        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, true, 150);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertEquals(429, $result->getStatusCode());
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::getClientIp
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::isTrustedProxy
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::ipInRange
     */
    public function testTrustedProxyIp(): void
    {
        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/login' => ['limit' => 5, 'window' => 300],
            ],
        ]);
        $this->config->set('trusted_proxies', ['127.0.0.1', '10.0.0.0/8']);

        $this->uri->method('getPath')->willReturn('/login');
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '10.0.0.1']);
        $this->request->method('getHeaderLine')
            ->with('X-Forwarded-For')
            ->willReturn('203.0.113.50, 10.0.0.1');

        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, true, 10);
        $result = $middleware->process($this->request, $this->handler);

        // The middleware should use 203.0.113.50 as the client IP (first in X-Forwarded-For)
        $this->assertEquals(429, $result->getStatusCode());
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::getClientIp
     */
    public function testUntrustedProxyIgnoresForwardedFor(): void
    {
        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/login' => ['limit' => 5, 'window' => 300],
            ],
        ]);
        $this->config->set('trusted_proxies', ['127.0.0.1']);

        $this->uri->method('getPath')->willReturn('/login');
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.1.100']);
        $this->request->method('getHeaderLine')
            ->with('X-Forwarded-For')
            ->willReturn('203.0.113.50');

        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, true, 0);
        $result = $middleware->process($this->request, $this->handler);

        // Should pass because 192.168.1.100 is not trusted, so X-Forwarded-For is ignored
        // and 192.168.1.100 has 0 attempts
        $this->assertSame($this->response, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::getClientIp
     */
    public function testTrustedProxyEmptyForwardedFor(): void
    {
        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/login' => ['limit' => 5, 'window' => 300],
            ],
        ]);
        $this->config->set('trusted_proxies', ['10.0.0.1']);

        $this->uri->method('getPath')->willReturn('/login');
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '10.0.0.1']);
        $this->request->method('getHeaderLine')
            ->with('X-Forwarded-For')
            ->willReturn('');

        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, true, 0);
        $result = $middleware->process($this->request, $this->handler);

        // Should use REMOTE_ADDR since X-Forwarded-For is empty
        $this->assertSame($this->response, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::ipInRange
     */
    public function testIpInRangeExactMatch(): void
    {
        $middleware = new TestableRateLimitMiddleware($this->config, $this->log);

        $this->assertTrue($middleware->testIpInRange('192.168.1.1', '192.168.1.1'));
        $this->assertFalse($middleware->testIpInRange('192.168.1.1', '192.168.1.2'));
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::ipInRange
     */
    public function testIpInRangeCidr(): void
    {
        $middleware = new TestableRateLimitMiddleware($this->config, $this->log);

        // /24 network
        $this->assertTrue($middleware->testIpInRange('192.168.1.100', '192.168.1.0/24'));
        $this->assertTrue($middleware->testIpInRange('192.168.1.1', '192.168.1.0/24'));
        $this->assertTrue($middleware->testIpInRange('192.168.1.254', '192.168.1.0/24'));
        $this->assertFalse($middleware->testIpInRange('192.168.2.1', '192.168.1.0/24'));

        // /8 network
        $this->assertTrue($middleware->testIpInRange('10.255.255.255', '10.0.0.0/8'));
        $this->assertTrue($middleware->testIpInRange('10.0.0.1', '10.0.0.0/8'));
        $this->assertFalse($middleware->testIpInRange('11.0.0.1', '10.0.0.0/8'));
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::ipInRange
     */
    public function testIpInRangeNonIpv4WithCidr(): void
    {
        $middleware = new TestableRateLimitMiddleware($this->config, $this->log);

        // Non-IPv4 address with CIDR should fall through to exact match (which fails)
        $this->assertFalse($middleware->testIpInRange('invalid', '192.168.1.0/24'));
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::buildKey
     */
    public function testBuildKey(): void
    {
        $middleware = new TestableRateLimitMiddleware($this->config, $this->log);

        $key1 = $middleware->testBuildKey('192.168.1.1', '/login');
        $key2 = $middleware->testBuildKey('192.168.1.2', '/login');
        $key3 = $middleware->testBuildKey('192.168.1.1', '/api');

        // Keys should be different for different IPs or endpoints
        $this->assertNotEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);

        // Same IP and endpoint should produce same key
        $this->assertEquals($key1, $middleware->testBuildKey('192.168.1.1', '/login'));

        // Key should have expected prefix
        $this->assertStringStartsWith('ratelimit:', $key1);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::createRateLimitResponse
     */
    public function testCreateRateLimitResponse(): void
    {
        $middleware = new TestableRateLimitMiddleware($this->config, $this->log);

        $response = $middleware->testCreateRateLimitResponse(120);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertEquals('120', $response->getHeaderLine('Retry-After'));
        $this->assertStringContainsString('Too Many Requests', (string) $response->getBody());
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::pathMatchesPattern
     */
    public function testPathMatchesPatternExact(): void
    {
        $middleware = new TestableRateLimitMiddleware($this->config, $this->log);

        $this->assertTrue($middleware->testPathMatchesPattern('/login', '/login'));
        $this->assertFalse($middleware->testPathMatchesPattern('/login', '/logout'));
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::pathMatchesPattern
     */
    public function testPathMatchesPatternPrefix(): void
    {
        $middleware = new TestableRateLimitMiddleware($this->config, $this->log);

        // Pattern with trailing slash matches prefix
        $this->assertTrue($middleware->testPathMatchesPattern('/api/v1/users', '/api/'));
        $this->assertTrue($middleware->testPathMatchesPattern('/api/', '/api/'));
        $this->assertFalse($middleware->testPathMatchesPattern('/apiv1', '/api/'));
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::pathMatchesPattern
     */
    public function testPathMatchesPatternNoMatch(): void
    {
        $middleware = new TestableRateLimitMiddleware($this->config, $this->log);

        // Pattern without trailing slash requires exact match
        $this->assertFalse($middleware->testPathMatchesPattern('/login/extra', '/login'));
        $this->assertFalse($middleware->testPathMatchesPattern('/different', '/login'));
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::isTrustedProxy
     */
    public function testIsTrustedProxyEmptyList(): void
    {
        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/login' => ['limit' => 5, 'window' => 300],
            ],
        ]);
        $this->config->set('trusted_proxies', []);

        $this->uri->method('getPath')->willReturn('/login');
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);
        $this->request->method('getHeaderLine')
            ->with('X-Forwarded-For')
            ->willReturn('203.0.113.50');

        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, true, 0);
        $result = $middleware->process($this->request, $this->handler);

        // No trusted proxies, so X-Forwarded-For ignored
        $this->assertSame($this->response, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::getClientIp
     */
    public function testGetClientIpWithMissingRemoteAddr(): void
    {
        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/login' => ['limit' => 5, 'window' => 300],
            ],
        ]);

        $this->uri->method('getPath')->willReturn('/login');
        $this->request->method('getServerParams')->willReturn([]);
        $this->request->method('getHeaderLine')->willReturn('');

        $middleware = new TestableRateLimitMiddleware($this->config, $this->log, true, 0);
        $result = $middleware->process($this->request, $this->handler);

        // Should default to 127.0.0.1
        $this->assertSame($this->response, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::isEnabled
     */
    public function testIsEnabledWithEmptyConfig(): void
    {
        // No rate_limits config at all
        $middleware = new RateLimitMiddleware($this->config, $this->log);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    /**
     * @covers \Engelsystem\Middleware\RateLimitMiddleware::isRateLimited
     */
    public function testIsRateLimitedWithApcu(): void
    {
        if (!function_exists('apcu_fetch') || !apcu_enabled()) {
            $this->markTestSkipped('APCu not available');
        }

        $this->config->set('rate_limits', [
            'enabled' => true,
            'endpoints' => [
                '/test-apcu' => ['limit' => 2, 'window' => 10],
            ],
        ]);

        $this->uri->method('getPath')->willReturn('/test-apcu');
        $this->request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '192.168.99.99']);
        $this->request->method('getHeaderLine')->willReturn('');

        // Use real middleware (not testable) to test actual APCu integration
        $middleware = new RateLimitMiddleware($this->config, $this->log);

        // First two requests should pass
        $result1 = $middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result1);

        $result2 = $middleware->process($this->request, $this->handler);
        $this->assertSame($this->response, $result2);

        // Third request should be rate limited
        $result3 = $middleware->process($this->request, $this->handler);
        $this->assertEquals(429, $result3->getStatusCode());
    }
}
