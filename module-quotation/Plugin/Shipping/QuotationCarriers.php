<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Plugin\Shipping;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Shipping;
use SPS\Quotation\Model\Carrier\QuotationFreight;
use SPS\Quotation\Model\QuotationCartResolver;

/**
 * Carts built from a quotation are only offered the quoted freight (every other carrier is skipped).
 * Runs before the quote-required filter (sortOrder in di.xml).
 */
class QuotationCarriers
{
    public function __construct(private readonly QuotationCartResolver $resolver)
    {
    }

    /**
     * @param string $carrierCode
     * @param RateRequest|mixed $request
     */
    public function aroundCollectCarrierRates(Shipping $subject, callable $proceed, $carrierCode, $request)
    {
        if ($carrierCode === QuotationFreight::CODE || !$request instanceof RateRequest || !$this->isQuotationRequest($request)) {
            return $proceed($carrierCode, $request);
        }
        return $subject;
    }

    private function isQuotationRequest(RateRequest $request): bool
    {
        foreach ((array) $request->getAllItems() as $item) {
            $quote = $item->getQuote();
            if ($quote) {
                return $this->resolver->isQuotationCart($quote);
            }
        }
        return false;
    }
}
