<?php

declare(strict_types=1);

namespace Engelsystem\Middleware;

use Engelsystem\Config\Config;
use Engelsystem\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    protected bool $apcuAvailable;

    public function __construct(
        protected Config $config,
        protected LoggerInterface $log
    ) {
        $this->apcuAvailable = function_exists('apcu_fetch') && apcu_enabled();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isEnabled()) {
            return $handler->handle($request);
        }

        if (!$this->apcuAvailable) {
            $this->log->warning('Rate limiting disabled: APCu not available');
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $endpoint = $this->matchEndpoint($path);

        if ($endpoint === null) {
            return $handler->handle($request);
        }

        $ip = $this->getClientIp($request);
        $key = $this->buildKey($ip, $endpoint['pattern']);
        $limit = $endpoint['limit'];
        $window = $endpoint['window'];

        if ($this->isRateLimited($key, $limit, $window)) {
            $this->log->info('Rate limit exceeded', [
                'ip' => $ip,
                'path' => $path,
                'limit' => $limit,
                'window' => $window,
            ]);

            return $this->createRateLimitResponse($window);
        }

        return $handler->handle($request);
    }

    protected function isEnabled(): bool
    {
        $config = $this->config->get('rate_limits', []);
        return $config['enabled'] ?? false;
    }

    protected function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';

        $trustedProxies = $this->config->get('trusted_proxies', []);
        if (!$this->isTrustedProxy($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }

        $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if ($forwardedFor === '') {
            return $remoteAddr;
        }

        $ips = array_map('trim', explode(',', $forwardedFor));
        return $ips[0] ?? $remoteAddr;
    }

    protected function isTrustedProxy(string $ip, array $trustedProxies): bool
    {
        foreach ($trustedProxies as $proxy) {
            if ($this->ipInRange($ip, $proxy)) {
                return true;
            }
        }
        return false;
    }

    protected function ipInRange(string $ip, string $range): bool
    {
        if (str_contains($range, '/')) {
            [$subnet, $bits] = explode('/', $range);
            $bits = (int) $bits;

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                $mask = -1 << (32 - $bits);
                return ($ipLong & $mask) === ($subnetLong & $mask);
            }
        }

        return $ip === $range;
    }

    /**
     * @return array{pattern: string, limit: int, window: int}|null
     */
    protected function matchEndpoint(string $path): ?array
    {
        $endpoints = $this->config->get('rate_limits.endpoints', []);

        foreach ($endpoints as $pattern => $config) {
            if ($this->pathMatchesPattern($path, $pattern)) {
                return [
                    'pattern' => $pattern,
                    'limit' => $config['limit'],
                    'window' => $config['window'],
                ];
            }
        }

        return null;
    }

    protected function pathMatchesPattern(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Prefix match for patterns ending without trailing character (e.g., '/api/')
        if (str_ends_with($pattern, '/') && str_starts_with($path, $pattern)) {
            return true;
        }

        return false;
    }

    protected function buildKey(string $ip, string $endpoint): string
    {
        return 'ratelimit:' . md5($ip . ':' . $endpoint);
    }

    protected function isRateLimited(string $key, int $limit, int $window): bool
    {
        $attempts = apcu_fetch($key, $success);

        if (!$success) {
            apcu_store($key, 1, $window);
            return false;
        }

        if ($attempts >= $limit) {
            return true;
        }

        apcu_inc($key);
        return false;
    }

    protected function createRateLimitResponse(int $retryAfter): ResponseInterface
    {
        return new Response(
            'Too Many Requests',
            429,
            ['Retry-After' => (string) $retryAfter]
        );
    }
}
