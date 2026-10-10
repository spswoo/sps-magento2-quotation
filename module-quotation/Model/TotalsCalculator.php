<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Tax\Model\Config as TaxConfig;
use Psr\Log\LoggerInterface;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Model\QuotationItem;

/**
 * Subtotal, freight and ESTIMATED tax for the quotation's destination, using the store's tax rules (customer tax
 * class, product tax classes, shipping tax class, "prices include tax" settings). The checkout calculates the
 * final tax for the address the customer enters.
 */
class TotalsCalculator
{
    /** @var array<int, int> product id => tax class id */
    private array $productTaxClass = [];

    public function __construct(
        private readonly TaxCalculation $taxCalculation,
        private readonly TaxConfig $taxConfig,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly GroupManagementInterface $groupManagement,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Set subtotal, tax_amount, grand_total and currency_code on the quotation.
     */
    public function collect(QuotationInterface $quotation): void
    {
        /** @var Quotation $quotation */
        $store = $this->storeManager->getStore((int) $quotation->getStoreId());
        $storeId = (int) $store->getId();
        $request = $this->rateRequest($quotation, $store);
        $pricesIncludeTax = $this->taxConfig->priceIncludesTax($storeId);
        $shippingIncludesTax = $this->taxConfig->shippingPriceIncludesTax($storeId);

        $subtotal = 0.0;
        $itemsTax = 0.0;
        foreach ($quotation->getItems() as $item) {
            /** @var QuotationItem $item */
            $row = $this->round($item->getRowTotal());
            $subtotal += $row;
            if ($request && $item->getProductId()) {
                $rate = $this->rate($request, $this->productTaxClassId((int) $item->getProductId(), $storeId));
                $itemsTax += $this->taxFor($row, $rate, $pricesIncludeTax);
            }
        }
        $freight = $this->round((float) $quotation->getFreightAmount());
        $freightTax = 0.0;
        if ($request && $freight > 0) {
            $rate = $this->rate($request, (int) $this->taxConfig->getShippingTaxClass($storeId));
            $freightTax = $this->taxFor($freight, $rate, $shippingIncludesTax);
        }
        // tax is added on top only for the parts entered excluding tax
        $grand = $subtotal + $freight
            + ($pricesIncludeTax ? 0.0 : $itemsTax)
            + ($shippingIncludesTax ? 0.0 : $freightTax);

        $quotation->setData(QuotationInterface::SUBTOTAL, $this->round($subtotal));
        $quotation->setData(QuotationInterface::TAX_AMOUNT, $this->round($itemsTax + $freightTax));
        $quotation->setData(QuotationInterface::GRAND_TOTAL, $this->round($grand));
        $quotation->setData(QuotationInterface::CURRENCY_CODE, $store->getBaseCurrencyCode());
    }

    private function rateRequest(Quotation $quotation, $store): ?DataObject
    {
        $country = (string) $quotation->getData(QuotationInterface::DEST_COUNTRY_ID);
        if ($country === '') {
            return null;
        }
        $address = new DataObject([
            'country_id' => $country,
            'region_id' => (int) $quotation->getData(QuotationInterface::DEST_REGION_ID) ?: null,
            'postcode' => (string) $quotation->getData(QuotationInterface::DEST_POSTCODE),
        ]);
        try {
            return $this->taxCalculation->getRateRequest(
                $address,
                $address,
                $this->customerTaxClassId($quotation),
                $store,
                $quotation->getCustomerId()
            );
        } catch (\Throwable $e) {
            $this->logger->warning('SPS_Quotation: tax estimate failed: ' . $e->getMessage());
            return null;
        }
    }

    private function customerTaxClassId(Quotation $quotation): int
    {
        try {
            if ($quotation->getCustomerId()) {
                $groupId = (int) $this->customerRepository->getById($quotation->getCustomerId())->getGroupId();
                return (int) $this->groupRepository->getById($groupId)->getTaxClassId();
            }
            return (int) $this->groupManagement->getNotLoggedInGroup()->getTaxClassId();
        } catch (\Throwable) {
            return (int) $this->groupManagement->getNotLoggedInGroup()->getTaxClassId();
        }
    }

    private function productTaxClassId(int $productId, int $storeId): int
    {
        if (!isset($this->productTaxClass[$productId])) {
            try {
                $this->productTaxClass[$productId] = (int) $this->productRepository
                    ->getById($productId, false, $storeId)->getData('tax_class_id');
            } catch (\Throwable) {
                $this->productTaxClass[$productId] = 0;
            }
        }
        return $this->productTaxClass[$productId];
    }

    private function rate(DataObject $request, int $taxClassId): float
    {
        if (!$taxClassId) {
            return 0.0;
        }
        return (float) $this->taxCalculation->getRate((clone $request)->setData('product_class_id', $taxClassId));
    }

    private function taxFor(float $amount, float $rate, bool $includesTax): float
    {
        if ($rate <= 0 || $amount == 0.0) {
            return 0.0;
        }
        return $this->round($includesTax ? $amount - $amount / (1 + $rate / 100) : $amount * $rate / 100);
    }

    private function round(float $amount): float
    {
        return (float) $this->priceCurrency->round($amount);
    }
}
