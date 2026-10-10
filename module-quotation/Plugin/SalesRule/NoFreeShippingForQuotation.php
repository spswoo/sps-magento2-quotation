<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Plugin\SalesRule;

use Magento\OfflineShipping\Model\Quote\Address\FreeShipping;
use Magento\Quote\Model\Quote;
use SPS\Quotation\Model\QuotationCartResolver;

/** The quoted freight is charged even when a free-shipping rule would match. */
class NoFreeShippingForQuotation
{
    public function __construct(private readonly QuotationCartResolver $resolver)
    {
    }

    /**
     * @param bool $result
     * @param array<int, mixed> $items
     */
    public function afterIsFreeShipping(FreeShipping $subject, $result, Quote $quote, $items = [])
    {
        return $result && $this->resolver->isQuotationCart($quote) ? false : $result;
    }
}
