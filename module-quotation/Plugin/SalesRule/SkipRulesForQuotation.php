<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Plugin\SalesRule;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Validator;
use SPS\Quotation\Model\QuotationCartResolver;

/**
 * "The quote is final": no cart price rule (discounts, free shipping, rule-based fees) applies to a cart built from
 * a quotation. Item rules are skipped in Validator::process(), shipping-amount rules in processShippingAmount().
 */
class SkipRulesForQuotation
{
    public function __construct(private readonly QuotationCartResolver $resolver)
    {
    }

    public function aroundProcess(Validator $subject, callable $proceed, AbstractItem $item, Rule $rule)
    {
        return $this->isQuotation($item->getQuote()) ? $subject : $proceed($item, $rule);
    }

    public function aroundProcessShippingAmount(Validator $subject, callable $proceed, Address $address)
    {
        return $this->isQuotation($address->getQuote()) ? $subject : $proceed($address);
    }

    private function isQuotation(?Quote $quote): bool
    {
        return $quote !== null && $this->resolver->isQuotationCart($quote);
    }
}
