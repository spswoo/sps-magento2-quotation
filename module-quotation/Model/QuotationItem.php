<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Framework\Model\AbstractModel;
use SPS\Quotation\Api\Data\QuotationItemInterface;
use SPS\Quotation\Model\ResourceModel\QuotationItem as ItemResource;

class QuotationItem extends AbstractModel implements QuotationItemInterface
{
    protected function _construct(): void
    {
        $this->_init(ItemResource::class);
    }

    public function getProductId(): ?int
    {
        $value = $this->getData(self::PRODUCT_ID);
        return $value ? (int) $value : null;
    }

    public function getSku(): ?string
    {
        $value = $this->getData(self::SKU);
        return $value === null ? null : (string) $value;
    }

    public function getName(): ?string
    {
        $value = $this->getData(self::NAME);
        return $value === null ? null : (string) $value;
    }

    public function getQty(): float
    {
        return (float) ($this->getData(self::QTY) ?? 1);
    }

    public function getPrice(): ?float
    {
        $value = $this->getData(self::PRICE);
        return $value === null || $value === '' ? null : (float) $value;
    }

    public function getOriginalPrice(): ?float
    {
        $value = $this->getData(self::ORIGINAL_PRICE);
        return $value === null || $value === '' ? null : (float) $value;
    }

    public function getBuyRequestOptions(): array
    {
        $value = (string) $this->getData(self::OPTIONS);
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setBuyRequestOptions(array $options): self
    {
        return $this->setData(self::OPTIONS, $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null);
    }

    /** Row total at the quoted price (0 while unpriced). */
    public function getRowTotal(): float
    {
        return round((float) $this->getPrice() * $this->getQty(), 4);
    }
}
