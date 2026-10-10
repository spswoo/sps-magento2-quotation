<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Shipping\Model\Config as ShippingConfig;
use SPS\Quotation\Model\Carrier\QuoteRequired;

/**
 * All installed carriers (except the notice carrier itself), for "Shipping Methods Still Offered".
 */
class Carriers implements OptionSourceInterface
{
    public function __construct(
        private readonly ShippingConfig $shippingConfig,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach (array_keys($this->shippingConfig->getAllCarriers()) as $code) {
            if ($code === QuoteRequired::CODE) {
                continue;
            }
            $title = (string) $this->scopeConfig->getValue("carriers/$code/title") ?: $code;
            $options[] = ['value' => $code, 'label' => "$title ($code)"];
        }
        usort($options, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));
        return $options;
    }
}
