<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Api\Data;

/**
 * Quotation header. Amounts are in the store's base currency (`currency_code`), excluding tax unless the store
 * enters catalog prices including tax.
 */
interface QuotationInterface
{
    public const ENTITY_ID = 'entity_id';
    public const INCREMENT_ID = 'increment_id';
    public const STORE_ID = 'store_id';
    public const STATUS = 'status';
    public const CUSTOMER_ID = 'customer_id';
    public const CUSTOMER_EMAIL = 'customer_email';
    public const CUSTOMER_FIRSTNAME = 'customer_firstname';
    public const CUSTOMER_LASTNAME = 'customer_lastname';
    public const CUSTOMER_PHONE = 'customer_phone';
    public const CUSTOMER_COMPANY = 'customer_company';
    public const DEST_COUNTRY_ID = 'dest_country_id';
    public const DEST_REGION_ID = 'dest_region_id';
    public const DEST_REGION = 'dest_region';
    public const DEST_POSTCODE = 'dest_postcode';
    public const DEST_CITY = 'dest_city';
    public const DEST_STREET = 'dest_street';
    public const FREIGHT_AMOUNT = 'freight_amount';
    public const FREIGHT_LABEL = 'freight_label';
    public const SUBTOTAL = 'subtotal';
    public const TAX_AMOUNT = 'tax_amount';
    public const GRAND_TOTAL = 'grand_total';
    public const CURRENCY_CODE = 'currency_code';
    public const CUSTOMER_MESSAGE = 'customer_message';
    public const STAFF_NOTE = 'staff_note';
    public const EXPIRES_AT = 'expires_at';
    public const TOKEN_HASH = 'token_hash';
    public const QUOTE_ID = 'quote_id';
    public const ORDER_ID = 'order_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const SENT_AT = 'sent_at';
    public const REMINDER_SENT_AT = 'reminder_sent_at';
    public const SOURCE = 'source';
    public const SOURCE_QUOTE_ID = 'source_quote_id';

    public const SOURCE_PRODUCT = 'product';
    public const SOURCE_CART = 'cart';
    public const SOURCE_ADMIN = 'admin';

    /** Status values */
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return int|null
     */
    public function getId();

    public function getIncrementId(): ?string;

    public function getStoreId(): ?int;

    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function getCustomerId(): ?int;

    public function getCustomerEmail(): ?string;

    public function getCustomerName(): string;

    public function getFreightAmount(): ?float;

    public function getExpiresAt(): ?string;

    /**
     * @return \SPS\Quotation\Api\Data\QuotationItemInterface[]
     */
    public function getItems(): array;

    /**
     * @param \SPS\Quotation\Api\Data\QuotationItemInterface[] $items
     */
    public function setItems(array $items): self;
}
