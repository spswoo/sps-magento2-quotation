<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Api\Data;

/**
 * Quotation line. `options` holds the product's custom options as a buy request fragment
 * (`{"options": {"<option_id>": "<value id or text>"}}`), so the cart line can be rebuilt exactly.
 */
interface QuotationItemInterface
{
    public const ITEM_ID = 'item_id';
    public const QUOTATION_ID = 'quotation_id';
    public const PRODUCT_ID = 'product_id';
    public const SKU = 'sku';
    public const NAME = 'name';
    public const QTY = 'qty';
    public const ORIGINAL_PRICE = 'original_price';
    public const PRICE = 'price';
    public const OPTIONS = 'options';
    public const SORT_ORDER = 'sort_order';

    /**
     * @return int|null
     */
    public function getId();

    public function getProductId(): ?int;

    public function getSku(): ?string;

    public function getName(): ?string;

    public function getQty(): float;

    public function getPrice(): ?float;

    public function getOriginalPrice(): ?float;

    /**
     * Buy request fragment for the product's custom options.
     *
     * @return array<string, mixed>
     */
    public function getBuyRequestOptions(): array;

    /**
     * @param array<string, mixed> $options
     */
    public function setBuyRequestOptions(array $options): self;
}
