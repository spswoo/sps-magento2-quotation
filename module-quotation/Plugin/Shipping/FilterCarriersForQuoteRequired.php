<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Plugin\Shipping;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Shipping;
use SPS\Quotation\Model\Carrier\QuoteRequired;
use SPS\Quotation\Model\QuoteRequired\Checker;

/**
 * When the cart contains a "Shipping Quote Required" product, only the allowed carriers (and the notice carrier)
 * are asked for rates; every other carrier is skipped silently.
 */
class FilterCarriersForQuoteRequired
{
    public function __construct(private readonly Checker $checker)
    {
    }

    /**
     * @param string $carrierCode
     * @param RateRequest|mixed $request
     */
    public function aroundCollectCarrierRates(Shipping $subject, callable $proceed, $carrierCode, $request)
    {
        if ($carrierCode === QuoteRequired::CODE
            || !$request instanceof RateRequest
            || !$this->checker->isEnabled($request->getStoreId())
            || in_array($carrierCode, $this->checker->getAllowedCarriers($request->getStoreId()), true)
            || !$this->checker->getFlaggedItemNames((array) $request->getAllItems(), $request->getStoreId())
        ) {
            return $proceed($carrierCode, $request);
        }
        return $subject;
    }
}
