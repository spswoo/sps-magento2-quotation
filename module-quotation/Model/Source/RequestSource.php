<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use SPS\Quotation\Api\Data\QuotationInterface as Q;

/** Where a quotation was started: product page request, cart request or by staff in the admin. */
class RequestSource implements OptionSourceInterface
{
    /** @return array<string, \Magento\Framework\Phrase> */
    public function getLabels(): array
    {
        return [
            Q::SOURCE_PRODUCT => __('Product page'),
            Q::SOURCE_CART => __('Cart'),
            Q::SOURCE_ADMIN => __('Admin'),
        ];
    }

    public function getLabel(?string $source): string
    {
        return $source === null || $source === '' ? '' : (string) ($this->getLabels()[$source] ?? $source);
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->getLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }
}
