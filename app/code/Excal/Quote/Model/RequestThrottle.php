<?php
/**
 * Cache-backed throttle for quotation requests.
 */
declare(strict_types=1);

namespace Excal\Quote\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Counts successful quotation requests per client IP and per customer email address.
 *
 * The session-based throttle in the Save controller is easy to bypass by dropping cookies.
 * This one survives that, so the form can't be used to flood an inbox (each request emails
 * the address typed into the form) or spam the shop owner.
 */
class RequestThrottle
{
    private const CACHE_PREFIX = 'excal_quote_throttle_';
    private const CACHE_TAG = 'EXCAL_QUOTE_THROTTLE';
    private const WINDOW_SECONDS = 3600;

    /**
     * The per-IP cap is looser than the per-email cap: if the shop sits behind a proxy or CDN
     * that Magento doesn't unwrap, many real customers can share one IP.
     */
    private const IP_MULTIPLIER = 4;
    private const IP_MINIMUM = 20;

    /**
     * @param CacheInterface $cache
     * @param RemoteAddress $remoteAddress
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly RemoteAddress $remoteAddress
    ) {
    }

    /**
     * Whether another request is allowed for this IP and email under the hourly limit.
     *
     * @param string $email
     * @param int $maxPerHour 0 = unlimited
     * @return bool
     */
    public function isAllowed(string $email, int $maxPerHour): bool
    {
        if ($maxPerHour <= 0) {
            return true;
        }
        $ipCap = max($maxPerHour * self::IP_MULTIPLIER, self::IP_MINIMUM);
        foreach ($this->keys($email) as $type => $key) {
            $cap = $type === 'ip' ? $ipCap : $maxPerHour;
            if (count($this->recent($key)) >= $cap) {
                return false;
            }
        }
        return true;
    }

    /**
     * Record a successful request for this IP and email.
     *
     * @param string $email
     * @return void
     */
    public function record(string $email): void
    {
        foreach ($this->keys($email) as $key) {
            $times = $this->recent($key);
            $times[] = time();
            $this->cache->save(
                (string)json_encode($times),
                $key,
                [self::CACHE_TAG],
                self::WINDOW_SECONDS
            );
        }
    }

    /**
     * Cache keys for the current client IP and the (normalised) email.
     *
     * @param string $email
     * @return array<string, string> type ('ip'|'email') => cache key
     */
    private function keys(string $email): array
    {
        $keys = [];
        $ip = (string)$this->remoteAddress->getRemoteAddress();
        if ($ip !== '') {
            $keys['ip'] = self::CACHE_PREFIX . 'ip_' . hash('sha256', $ip);
        }
        $email = strtolower(trim($email));
        if ($email !== '') {
            $keys['email'] = self::CACHE_PREFIX . 'email_' . hash('sha256', $email);
        }
        return $keys;
    }

    /**
     * Timestamps within the window for a key.
     *
     * @param string $key
     * @return int[]
     */
    private function recent(string $key): array
    {
        $raw = $this->cache->load($key);
        $times = $raw ? json_decode((string)$raw, true) : [];
        if (!is_array($times)) {
            return [];
        }
        $cutoff = time() - self::WINDOW_SECONDS;
        return array_values(array_filter(array_map('intval', $times), static fn (int $t) => $t > $cutoff));
    }
}
