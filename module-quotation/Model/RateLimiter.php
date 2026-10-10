<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Framework\App\CacheInterface;

/**
 * Fixed-window counter per key (e.g. visitor IP) in the Magento cache.
 */
class RateLimiter
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * Count one hit; false when the key already reached $limit in the current window.
     */
    public function hit(string $scope, string $key, int $limit, int $windowSeconds = 3600): bool
    {
        if ($limit <= 0) {
            return true;
        }
        $window = intdiv(time(), $windowSeconds);
        $cacheKey = 'sps_quotation_rl_' . $scope . '_' . hash('sha256', $key) . '_' . $window;
        $count = (int) $this->cache->load($cacheKey);
        if ($count >= $limit) {
            return false;
        }
        $this->cache->save((string) ($count + 1), $cacheKey, [], $windowSeconds);
        return true;
    }
}
