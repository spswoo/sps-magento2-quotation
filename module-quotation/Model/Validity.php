<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use SPS\Quotation\Api\Data\QuotationInterface;

/**
 * Expiry in the store's time zone: a quotation is valid through its expires_at day.
 */
class Validity
{
    public function __construct(private readonly TimezoneInterface $timezone)
    {
    }

    public function today(?int $storeId): string
    {
        return $this->timezone->scopeDate($storeId)->format('Y-m-d');
    }

    public function isExpired(QuotationInterface $quotation): bool
    {
        $expires = $quotation->getExpiresAt();
        return $expires !== null && $expires < $this->today($quotation->getStoreId());
    }
}
