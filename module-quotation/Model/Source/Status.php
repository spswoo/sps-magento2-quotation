<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use SPS\Quotation\Api\Data\QuotationInterface as Q;

class Status implements OptionSourceInterface
{
    /** @return array<string, \Magento\Framework\Phrase> */
    public function getLabels(): array
    {
        return [
            Q::STATUS_REQUESTED => __('Requested'),
            Q::STATUS_DRAFT => __('Draft'),
            Q::STATUS_SENT => __('Sent'),
            Q::STATUS_ACCEPTED => __('Opened by customer'),
            Q::STATUS_ORDERED => __('Ordered'),
            Q::STATUS_EXPIRED => __('Expired'),
            Q::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    public function getLabel(string $status): string
    {
        return (string) ($this->getLabels()[$status] ?? $status);
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
