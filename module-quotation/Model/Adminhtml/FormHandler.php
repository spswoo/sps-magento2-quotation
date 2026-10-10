<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model\Adminhtml;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use SPS\Quotation\Api\Data\QuotationInterface as Q;
use SPS\Quotation\Model\ItemOptions;
use SPS\Quotation\Model\Quotation;
use SPS\Quotation\Model\QuotationItem;
use SPS\Quotation\Model\QuotationItemFactory;
use SPS\Quotation\Model\TotalsCalculator;

/**
 * Applies the admin editor's POST data to a quotation (header, destination, freight, items) and recalculates totals.
 */
class FormHandler
{
    private const TEXT_FIELDS = [
        Q::CUSTOMER_EMAIL => 255, Q::CUSTOMER_FIRSTNAME => 255, Q::CUSTOMER_LASTNAME => 255,
        Q::CUSTOMER_PHONE => 64, Q::CUSTOMER_COMPANY => 255, Q::DEST_REGION => 255, Q::DEST_POSTCODE => 20,
        Q::DEST_CITY => 255, Q::DEST_STREET => 1000, Q::FREIGHT_LABEL => 255, Q::STAFF_NOTE => 5000,
    ];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly QuotationItemFactory $itemFactory,
        private readonly ItemOptions $itemOptions,
        private readonly TotalsCalculator $totals
    ) {
    }

    /**
     * @param array<string, mixed> $data quotation[...] fields
     * @param array<int|string, array<string, mixed>> $items items[n][product_id|qty|price|original_price|options]
     * @throws LocalizedException
     */
    public function apply(Quotation $quotation, array $data, array $items): void
    {
        if ($quotation->getStatus() === Q::STATUS_ORDERED) {
            throw new LocalizedException(__('An ordered quotation cannot be changed.'));
        }
        if (!$quotation->getId() && !$quotation->getData(Q::SOURCE)) {
            $quotation->setData(Q::SOURCE, Q::SOURCE_ADMIN);
        }
        $storeId = (int) ($data[Q::STORE_ID] ?? $quotation->getStoreId());
        $store = $this->storeManager->getStore($storeId);
        $quotation->setData(Q::STORE_ID, (int) $store->getId());

        foreach (self::TEXT_FIELDS as $field => $max) {
            if (array_key_exists($field, $data)) {
                $value = mb_substr(trim((string) $data[$field]), 0, $max);
                $quotation->setData($field, $value === '' ? null : $value);
            }
        }
        $email = (string) $quotation->getCustomerEmail();
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('"%1" is not a valid e-mail address.', $email));
        }
        $quotation->setData(Q::CUSTOMER_ID, $this->findCustomerId($email, (int) $store->getWebsiteId()));

        $country = strtoupper(trim((string) ($data[Q::DEST_COUNTRY_ID] ?? '')));
        $quotation->setData(Q::DEST_COUNTRY_ID, $country === '' ? null : substr($country, 0, 2));
        $regionId = (int) ($data[Q::DEST_REGION_ID] ?? 0);
        $quotation->setData(Q::DEST_REGION_ID, $regionId ?: null);
        if ($quotation->getData(Q::DEST_POSTCODE)) {
            $quotation->setData(Q::DEST_POSTCODE, strtoupper((string) $quotation->getData(Q::DEST_POSTCODE)));
        }

        $quotation->setData(Q::FREIGHT_AMOUNT, $this->amount($data[Q::FREIGHT_AMOUNT] ?? null));
        $expires = trim((string) ($data[Q::EXPIRES_AT] ?? ''));
        if ($expires !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
            throw new LocalizedException(__('Enter the expiry date as YYYY-MM-DD.'));
        }
        $quotation->setData(Q::EXPIRES_AT, $expires === '' ? null : $expires);

        $quotation->setItems($this->buildItems($items, (int) $store->getId()));
        $this->totals->collect($quotation);
        // a cart built before this change holds the old prices: the next visit to the link builds a new one
        $quotation->setData(Q::QUOTE_ID, null);
    }

    /**
     * @param array<int|string, array<string, mixed>> $rows
     * @return QuotationItem[]
     * @throws LocalizedException
     */
    private function buildItems(array $rows, int $storeId): array
    {
        $items = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = (float) ($row['qty'] ?? 0);
            if (!$productId || $qty <= 0) {
                continue;
            }
            try {
                /** @var Product $product */
                $product = $this->productRepository->getById($productId, false, $storeId);
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('Product ID %1 no longer exists.', $productId));
            }
            /** @var QuotationItem $item */
            $item = $this->itemFactory->create();
            $original = $this->amount($row['original_price'] ?? null);
            $item->addData([
                'product_id' => $productId,
                'sku' => (string) $product->getSku(),
                'name' => (string) $product->getName(),
                'qty' => $qty,
                'original_price' => $original ?? (float) $product->getFinalPrice($qty),
                'price' => $this->amount($row['price'] ?? null),
            ]);
            $options = is_array($row['options'] ?? null) ? $row['options'] : [];
            // configuration the editor doesn't edit (e.g. from a cart request) is passed through unchanged
            $extra = json_decode((string) ($row['extra'] ?? ''), true);
            $extra = is_array($extra) ? $this->itemOptions->getExtra($extra) : [];
            $item->setBuyRequestOptions($extra + $this->itemOptions->normalize($product, $options, false));
            $items[] = $item;
        }
        return $items;
    }

    private function findCustomerId(string $email, int $websiteId): ?int
    {
        if ($email === '') {
            return null;
        }
        try {
            return (int) $this->customerRepository->get($email, $websiteId)->getId();
        } catch (\Throwable) {
            return null;
        }
    }

    private function amount(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $number = (float) str_replace([',', ' ', '$'], '', (string) $value);
        return max(0.0, round($number, 4));
    }
}
