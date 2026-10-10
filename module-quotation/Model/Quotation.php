<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Framework\Model\AbstractModel;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Model\ResourceModel\Quotation as QuotationResource;

class Quotation extends AbstractModel implements QuotationInterface
{
    /** @var string */
    protected $_eventPrefix = 'sps_quotation';

    /** @var \SPS\Quotation\Api\Data\QuotationItemInterface[]|null */
    private ?array $items = null;

    protected function _construct(): void
    {
        $this->_init(QuotationResource::class);
    }

    public function getIncrementId(): ?string
    {
        $value = $this->getData(self::INCREMENT_ID);
        return $value === null ? null : (string) $value;
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData(self::STORE_ID);
        return $value === null ? null : (int) $value;
    }

    public function getStatus(): string
    {
        return (string) ($this->getData(self::STATUS) ?: self::STATUS_DRAFT);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData(self::CUSTOMER_ID);
        return $value ? (int) $value : null;
    }

    public function getCustomerEmail(): ?string
    {
        $value = $this->getData(self::CUSTOMER_EMAIL);
        return $value === null ? null : (string) $value;
    }

    public function getCustomerName(): string
    {
        return trim($this->getData(self::CUSTOMER_FIRSTNAME) . ' ' . $this->getData(self::CUSTOMER_LASTNAME));
    }

    public function getFreightAmount(): ?float
    {
        $value = $this->getData(self::FREIGHT_AMOUNT);
        return $value === null || $value === '' ? null : (float) $value;
    }

    public function getExpiresAt(): ?string
    {
        $value = $this->getData(self::EXPIRES_AT);
        return $value ? substr((string) $value, 0, 10) : null;
    }

    public function getItems(): array
    {
        return $this->items ?? [];
    }

    public function setItems(array $items): self
    {
        $this->items = array_values($items);
        return $this;
    }

    /** True once items were assigned (loaded or set) — the repository only rewrites items then. */
    public function hasItemsAssigned(): bool
    {
        return $this->items !== null;
    }
}
