<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Catalog\Helper\Product\Configuration as ProductConfiguration;
use Magento\Catalog\Model\Product;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Store\Model\ScopeInterface;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;
use SPS\Quotation\Model\QuoteRequired\Checker;
use SPS\Quotation\Model\ResourceModel\Quotation\CollectionFactory;

/**
 * Creates quotation requests (status "requested") from the storefront: a single product ("Request a shipping quote"
 * on the product page) or the whole cart ("Request a quote for my cart").
 *
 * One customer, one open request: when the same e-mail already has a request that staff haven't started on
 * (status "requested") and it concerns the same products — or comes from the same cart — that request is updated
 * instead of creating another one. Requests staff are working on (draft, sent, …) are never changed.
 */
class RequestService
{
    private const MAX_QTY = 9999;
    /** how many recent open requests of one e-mail are checked for a merge */
    private const MERGE_CANDIDATES = 20;

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
        private readonly RegionFactory $regionFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly QuotationCartResolver $cartResolver,
        private readonly ProductConfiguration $productConfiguration,
        private readonly TimezoneInterface $timezone,
        private readonly KnownStreet $knownStreet
    ) {
    }

    /**
     * Request for one product (product page).
     *
     * @param array<string, mixed> $input firstname, lastname, email, phone, company, country_id, region_id, region,
     *                                     postcode, city, qty, message, options (option_id => value)
     * @return QuotationInterface the new or updated request; data key "merged" is true when an open request was updated
     * @throws LocalizedException with a customer-facing message
     */
    public function create(Product $product, array $input, int $storeId, ?int $customerId): QuotationInterface
    {
        if (!$this->checker->isProductFlagged($product, $storeId)) {
            throw new LocalizedException(__('Quotes cannot be requested for this product.'));
        }
        $contact = $this->contact($input, $storeId);
        $qty = (int) ($input['qty'] ?? 1);
        if ($qty < 1 || $qty > self::MAX_QTY) {
            throw new LocalizedException(__('Please enter a quantity between 1 and %1.', self::MAX_QTY));
        }
        $options = is_array($input['options'] ?? null) ? $input['options'] : [];
        $item = $this->newItem(
            $product,
            $qty,
            (float) $product->getFinalPrice($qty),
            $this->itemOptions->normalize($product, $options, false)
        );

        $productId = (int) $product->getId();
        $quotation = $this->findOpenRequest($storeId, $contact[QuotationInterface::CUSTOMER_EMAIL], [$productId], null);
        $merged = $quotation !== null;
        if ($quotation) {
            // the product's line(s) are replaced by the new request, the other lines stay
            $items = array_filter(
                $quotation->getItems(),
                static fn(QuotationItem $existing): bool => (int) $existing->getProductId() !== $productId
            );
            $quotation->setItems(array_merge($items, [$item]));
        } else {
            $quotation = $this->newQuotation($storeId, QuotationInterface::SOURCE_PRODUCT);
            $quotation->setItems([$item]);
        }
        return $this->finish($quotation, $contact, $customerId, $merged, (string) __('product page'), null);
    }

    /**
     * Request for everything in the customer's cart (cart page / checkout). The cart itself is not changed.
     *
     * @param array<string, mixed> $input contact + destination fields as for create(), message
     * @return QuotationInterface the new or updated request; data key "merged" is true when an open request was updated
     * @throws LocalizedException with a customer-facing message
     */
    public function createFromCart(Quote $cart, array $input, int $storeId, ?int $customerId): QuotationInterface
    {
        if (!$cart->getId() || !$cart->getItemsCount()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }
        if ($this->cartResolver->isQuotationCart($cart)) {
            throw new LocalizedException(__('Your cart already holds a quotation. Please complete it first.'));
        }
        if (!$this->checker->getFlaggedItemNames($cart->getAllItems(), $storeId)) {
            throw new LocalizedException(__('Quotes can only be requested for carts with items that need a shipping quote.'));
        }
        $contact = $this->contact($input, $storeId);

        $items = [];
        foreach ($cart->getAllVisibleItems() as $cartItem) {
            /** @var QuoteItem $cartItem */
            $product = $cartItem->getProduct();
            if (!$product || !$product->getId()) {
                continue;
            }
            $buyRequest = $cartItem->getBuyRequest()->getData();
            $labels = [];
            foreach ($this->productConfiguration->getCustomOptions($cartItem) as $option) {
                $value = $option['print_value'] ?? ($option['value'] ?? '');
                $labels[] = [
                    'label' => (string) ($option['label'] ?? ''),
                    'value' => is_array($value) ? implode(', ', $value) : (string) $value,
                ];
            }
            $items[] = $this->newItem(
                $product,
                (float) $cartItem->getQty(),
                (float) ($cartItem->getCalculationPrice() ?? $cartItem->getPrice()),
                $this->itemOptions->fromBuyRequest(is_array($buyRequest) ? $buyRequest : [], $labels)
            );
        }
        if (!$items) {
            throw new LocalizedException(__('Your cart is empty.'));
        }

        $productIds = array_map(static fn(QuotationItem $i): int => (int) $i->getProductId(), $items);
        $quotation = $this->findOpenRequest(
            $storeId,
            $contact[QuotationInterface::CUSTOMER_EMAIL],
            $productIds,
            (int) $cart->getId()
        );
        $merged = $quotation !== null;
        $quotation ??= $this->newQuotation($storeId, QuotationInterface::SOURCE_CART);
        $quotation->setItems($items);                    // the cart is the complete, current picture
        $quotation->setData(QuotationInterface::SOURCE, QuotationInterface::SOURCE_CART);
        $quotation->setData(QuotationInterface::SOURCE_QUOTE_ID, (int) $cart->getId());
        return $this->finish($quotation, $contact, $customerId, $merged, (string) __('cart'), $cart);
    }

    /**
     * Most recent open request ("requested") of this e-mail in this store that came from this cart or contains one
     * of the products.
     *
     * @param int[] $productIds
     */
    private function findOpenRequest(int $storeId, string $email, array $productIds, ?int $cartId): ?Quotation
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(QuotationInterface::STATUS, QuotationInterface::STATUS_REQUESTED)
            ->addFieldToFilter(QuotationInterface::STORE_ID, $storeId);
        $conditions = [['eq' => $email]];
        $fields = [QuotationInterface::CUSTOMER_EMAIL];
        if ($cartId) {
            $fields[] = QuotationInterface::SOURCE_QUOTE_ID;
            $conditions[] = ['eq' => $cartId];
        }
        $collection->addFieldToFilter($fields, $conditions)
            ->setOrder(QuotationInterface::ENTITY_ID, 'DESC')
            ->setPageSize(self::MERGE_CANDIDATES);

        foreach ($collection as $candidate) {
            /** @var Quotation $quotation */
            $quotation = $this->repository->getById((int) $candidate->getId());
            if ($cartId && (int) $quotation->getData(QuotationInterface::SOURCE_QUOTE_ID) === $cartId) {
                return $quotation;
            }
            // e-mail match only counts with an overlapping product (a different enquiry stays separate)
            if (strcasecmp((string) $quotation->getCustomerEmail(), $email) !== 0) {
                continue;
            }
            foreach ($quotation->getItems() as $item) {
                if (in_array((int) $item->getProductId(), $productIds, true)) {
                    return $quotation;
                }
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $contact
     */
    private function finish(
        Quotation $quotation,
        array $contact,
        ?int $customerId,
        bool $merged,
        string $from,
        ?Quote $cart
    ): QuotationInterface {
        $message = (string) $contact[QuotationInterface::CUSTOMER_MESSAGE];
        unset($contact[QuotationInterface::CUSTOMER_MESSAGE]);
        $sameDestination = $quotation->getId()
            && strtoupper((string) $quotation->getData(QuotationInterface::DEST_COUNTRY_ID)) === $contact[QuotationInterface::DEST_COUNTRY_ID]
            && strtoupper((string) $quotation->getData(QuotationInterface::DEST_POSTCODE)) === $contact[QuotationInterface::DEST_POSTCODE];
        $quotation->addData($contact);                  // the newest contact and destination details win
        // street: not asked by the form; known from the checkout address or a saved customer address
        $street = $this->knownStreet->find(
            (string) $contact[QuotationInterface::DEST_COUNTRY_ID],
            (string) $contact[QuotationInterface::DEST_POSTCODE],
            $cart,
            $customerId
        );
        if ($street !== '' || !$sameDestination) {
            $quotation->setData(QuotationInterface::DEST_STREET, $street !== '' ? $street : null);
        }
        if ($customerId) {
            $quotation->setData(QuotationInterface::CUSTOMER_ID, $customerId);
        }
        if ($merged) {
            $previous = trim((string) $quotation->getData(QuotationInterface::CUSTOMER_MESSAGE));
            $separator = (string) __(
                '— Updated from the %1, %2 —',
                $from,
                $this->timezone->formatDateTime('now', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT)
            );
            $message = trim($previous . "\n\n" . $separator . ($message !== '' ? "\n" . $message : ''));
        }
        $quotation->setData(QuotationInterface::CUSTOMER_MESSAGE, $message !== '' ? mb_substr($message, 0, 10000) : null);

        $this->totals->collect($quotation);
        $this->repository->save($quotation);
        $this->emailSender->sendRequestNotifications($quotation, $merged);
        $quotation->setData('merged', $merged);
        return $quotation;
    }

    private function newQuotation(int $storeId, string $source): Quotation
    {
        /** @var Quotation $quotation */
        $quotation = $this->quotationFactory->create();
        $quotation->addData([
            QuotationInterface::STORE_ID => $storeId,
            QuotationInterface::STATUS => QuotationInterface::STATUS_REQUESTED,
            QuotationInterface::SOURCE => $source,
        ]);
        return $quotation;
    }

    /**
     * @param array<string, mixed> $buyRequest stored configuration (see ItemOptions)
     */
    private function newItem(Product $product, float $qty, float $originalPrice, array $buyRequest): QuotationItem
    {
        /** @var QuotationItem $item */
        $item = $this->itemFactory->create();
        $item->addData([
            'product_id' => (int) $product->getId(),
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'qty' => $qty,
            'original_price' => $originalPrice,
            'price' => null,
        ]);
        $item->setBuyRequestOptions($buyRequest);
        return $item;
    }

    /**
     * Validated contact + destination fields (quotation data keys) and the message.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function contact(array $input, int $storeId): array
    {
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

        return [
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
            QuotationInterface::CUSTOMER_MESSAGE => $field('message', 2000),
        ];
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
