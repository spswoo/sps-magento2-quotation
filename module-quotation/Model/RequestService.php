<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Catalog\Model\Product;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;
use SPS\Quotation\Model\QuoteRequired\Checker;

/**
 * Creates a quotation (status "requested") from the storefront "Request a shipping quote" form.
 */
class RequestService
{
    private const MAX_QTY = 9999;

    public function __construct(
        private readonly QuotationFactory $quotationFactory,
        private readonly QuotationItemFactory $itemFactory,
        private readonly QuotationRepositoryInterface $repository,
        private readonly ItemOptions $itemOptions,
        private readonly TotalsCalculator $totals,
        private readonly EmailSender $emailSender,
        private readonly Checker $checker,
        private readonly AllowedCountries $allowedCountries,
        private readonly DirectoryHelper $directoryHelper,
        private readonly RegionFactory $regionFactory
    ) {
    }

    /**
     * @param array<string, mixed> $input firstname, lastname, email, phone, company, country_id, postcode, city,
     *                                     qty, message, options (option_id => value)
     * @throws LocalizedException with a customer-facing message
     */
    public function create(Product $product, array $input, int $storeId, ?int $customerId): QuotationInterface
    {
        if (!$this->checker->isProductFlagged($product, $storeId)) {
            throw new LocalizedException(__('Quotes cannot be requested for this product.'));
        }
        $field = static fn(string $key, int $max = 255): string
            => mb_substr(trim(strip_tags((string) ($input[$key] ?? ''))), 0, $max);

        $email = $field('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('Please enter a valid e-mail address.'));
        }
        foreach (['firstname' => __('first name'), 'lastname' => __('last name'), 'phone' => __('phone number'),
                     'postcode' => __('postal / ZIP code')] as $key => $label) {
            if ($field($key) === '') {
                throw new LocalizedException(__('Please enter your %1.', $label));
            }
        }
        $country = strtoupper($field('country_id', 2));
        $allowed = $this->allowedCountries->getAllowedCountries(ScopeInterface::SCOPE_STORE, $storeId);
        if (!in_array($country, $allowed, true)) {
            throw new LocalizedException(__('Please choose a country we ship to.'));
        }
        [$regionId, $regionName] = $this->region($country, (int) ($input['region_id'] ?? 0), $field('region'));
        $qty = (int) ($input['qty'] ?? 1);
        if ($qty < 1 || $qty > self::MAX_QTY) {
            throw new LocalizedException(__('Please enter a quantity between 1 and %1.', self::MAX_QTY));
        }

        /** @var Quotation $quotation */
        $quotation = $this->quotationFactory->create();
        $quotation->addData([
            QuotationInterface::STORE_ID => $storeId,
            QuotationInterface::STATUS => QuotationInterface::STATUS_REQUESTED,
            QuotationInterface::CUSTOMER_ID => $customerId,
            QuotationInterface::CUSTOMER_EMAIL => $email,
            QuotationInterface::CUSTOMER_FIRSTNAME => $field('firstname'),
            QuotationInterface::CUSTOMER_LASTNAME => $field('lastname'),
            QuotationInterface::CUSTOMER_PHONE => $field('phone', 64),
            QuotationInterface::CUSTOMER_COMPANY => $field('company') ?: null,
            QuotationInterface::DEST_COUNTRY_ID => $country,
            QuotationInterface::DEST_REGION_ID => $regionId,
            QuotationInterface::DEST_REGION => $regionName,
            QuotationInterface::DEST_POSTCODE => strtoupper($field('postcode', 20)),
            QuotationInterface::DEST_CITY => $field('city') ?: null,
            QuotationInterface::CUSTOMER_MESSAGE => $field('message', 2000) ?: null,
        ]);

        /** @var QuotationItem $item */
        $item = $this->itemFactory->create();
        $item->addData([
            'product_id' => (int) $product->getId(),
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'qty' => $qty,
            'original_price' => (float) $product->getFinalPrice($qty),
            'price' => null,
        ]);
        $options = is_array($input['options'] ?? null) ? $input['options'] : [];
        $item->setBuyRequestOptions($this->itemOptions->normalize($product, $options, false));
        $quotation->setItems([$item]);

        $this->totals->collect($quotation);
        $this->repository->save($quotation);
        $this->emailSender->sendRequestNotifications($quotation);
        return $quotation;
    }

    /**
     * State/province: a region of the country (by ID) where the country has a list, free text otherwise.
     *
     * @return array{0: int|null, 1: string|null}
     * @throws LocalizedException
     */
    private function region(string $country, int $regionId, string $regionText): array
    {
        if ($regionId) {
            $region = $this->regionFactory->create()->load($regionId);
            if ($region->getId() && $region->getCountryId() === $country) {
                return [(int) $region->getId(), (string) ($region->getName() ?: $region->getDefaultName())];
            }
        }
        if (in_array($country, $this->directoryHelper->getCountriesWithStatesRequired(), true)) {
            throw new LocalizedException(__('Please choose your state / province.'));
        }
        return [null, $regionText !== '' ? $regionText : null];
    }
}
