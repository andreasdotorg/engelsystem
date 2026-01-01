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
        if (function_exists('apcu_fetch') && apcu_enabled()) {
            $this->markTestSkipped('APCu is available, cannot test degradation');
        }

        $this->config->set('rate_limits', ['enabled' => true]);

        $this->log->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('APCu not available'));

        $middleware = new RateLimitMiddleware($this->config, $this->log);
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
}
