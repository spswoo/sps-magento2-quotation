<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model\QuoteRequired;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use SPS\Quotation\Setup\Patch\Data\AddQuoteRequiredAttribute as Attribute;

/**
 * Which cart items need a shipping quote (product attribute sps_shipping_quote_required), plus the configuration
 * shared by the notice carrier, the carrier filter and the storefront notices
 * (Stores > Configuration > Sales > Delivery Methods > Shipping Quote Required).
 */
class Checker
{
    private const XML = 'carriers/spsquote/';

    /** @var array<int, bool> product id => flag */
    private array $flags = [];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ProductResource $productResource
    ) {
    }

    public function isEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML . 'active', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Carrier codes still offered when the cart needs a quote.
     *
     * @return string[]
     */
    public function getAllowedCarriers($store = null): array
    {
        $value = (string) $this->scopeConfig->getValue(self::XML . 'allowed_carriers', ScopeInterface::SCOPE_STORE, $store);
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    public function isProductFlagged(Product $product, $store = null): bool
    {
        $id = (int) $product->getId();
        if (!isset($this->flags[$id])) {
            $value = $product->getData(Attribute::CODE);
            if ($value === null && $id) {
                $value = $this->productResource->getAttributeRawValue($id, Attribute::CODE, $store ?? 0);
            }
            $this->flags[$id] = (bool) (is_array($value) ? reset($value) : $value);
        }
        return $this->flags[$id];
    }

    /**
     * Names of the cart items that need a quote (parent or child product flagged).
     *
     * @param iterable<\Magento\Quote\Model\Quote\Item\AbstractItem> $items
     * @return string[]
     */
    public function getFlaggedItemNames(iterable $items, $store = null): array
    {
        $names = [];
        foreach ($items as $item) {
            if ($item->getParentItem() || !$item->getProduct()) {
                continue;
            }
            $flagged = $this->isProductFlagged($item->getProduct(), $store);
            foreach ($item->getChildren() ?: [] as $child) {
                $flagged = $flagged || ($child->getProduct() && $this->isProductFlagged($child->getProduct(), $store));
            }
            if ($flagged) {
                $names[] = trim((string) preg_replace('/\s+/', ' ', (string) $item->getName()));
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * Configured message with placeholders filled: {products}, {phone}, {email}.
     *
     * @param string[] $productNames
     */
    public function getMessage(string $field, array $productNames = [], $store = null): string
    {
        $template = (string) $this->scopeConfig->getValue(self::XML . $field, ScopeInterface::SCOPE_STORE, $store);
        return strtr($template, [
            '{products}' => $productNames ? implode(', ', $productNames) : (string) __('this item'),
            '{phone}' => $this->getPhone($store),
            '{email}' => $this->getEmail($store),
        ]);
    }

    public function getPhone($store = null): string
    {
        return trim((string) $this->scopeConfig->getValue('general/store_information/phone', ScopeInterface::SCOPE_STORE, $store));
    }

    public function getEmail($store = null): string
    {
        return trim((string) $this->scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE, $store));
    }
}
