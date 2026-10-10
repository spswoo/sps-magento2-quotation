<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use SPS\Quotation\Api\Data\QuotationInterface as Q;
use SPS\Quotation\Model\Carrier\QuotationFreight;

/**
 * Builds the checkout cart for a quotation: quoted items (with their custom options) at the quoted unit prices
 * (custom price), destination address pre-filled on shipping + billing, the quoted freight method selected.
 * Guest cart unless a customer is given. The cart is linked to the quotation (quote_id).
 */
class CartBuilder
{
    private bool $building = false;

    public function __construct(
        private readonly QuoteFactory $quoteFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly QuotationCartResolver $resolver,
        private readonly \SPS\Quotation\Api\QuotationRepositoryInterface $repository
    ) {
    }

    /** True while build() adds the quoted items (other code blocks adding products to quotation carts). */
    public function isBuilding(): bool
    {
        return $this->building;
    }

    /**
     * @throws LocalizedException with a customer-facing message (e.g. an item is no longer available)
     */
    public function build(Quotation $quotation, ?int $customerId): Quote
    {
        $store = $this->storeManager->getStore((int) $quotation->getStoreId());
        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setIsActive(true);
        if ($customerId) {
            $quote->assignCustomer($this->customerRepository->getById($customerId));
        } else {
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(0);
            $quote->setCustomerEmail((string) $quotation->getCustomerEmail());
            $quote->setCustomerFirstname((string) $quotation->getData(Q::CUSTOMER_FIRSTNAME));
            $quote->setCustomerLastname((string) $quotation->getData(Q::CUSTOMER_LASTNAME));
        }

        $this->building = true;
        try {
            foreach ($quotation->getItems() as $item) {
                /** @var QuotationItem $item */
                $product = $this->productRepository->getById((int) $item->getProductId(), false, (int) $store->getId(), true);
                $request = new DataObject(['qty' => $item->getQty()] + $item->getBuyRequestOptions());
                $result = $quote->addProduct($product, $request);
                if (is_string($result)) {
                    throw new LocalizedException(__('%1 cannot be added to the cart: %2', $item->getName(), $result));
                }
                $price = (float) $item->getPrice();
                $result->setCustomPrice($price);
                $result->setOriginalCustomPrice($price);
                $result->getProduct()->setIsSuperMode(true);
            }
        } finally {
            $this->building = false;
        }

        $address = $this->addressData($quotation);
        $quote->getBillingAddress()->addData($address);
        $quote->getShippingAddress()->addData($address);
        $this->cartRepository->save($quote);

        // link first, so the freight carrier recognises the cart when rates are collected
        $quotation->setData(Q::QUOTE_ID, (int) $quote->getId());
        $this->repository->save($quotation);
        $this->resolver->remember((int) $quote->getId(), $quotation);

        $shipping = $quote->getShippingAddress();
        $shipping->setCollectShippingRates(true);
        $shipping->setShippingMethod(QuotationFreight::CODE . '_' . QuotationFreight::METHOD);
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
        return $quote;
    }

    /** @return array<string, mixed> */
    private function addressData(Quotation $quotation): array
    {
        $street = array_values(array_filter(array_map('trim', explode("\n", (string) $quotation->getData(Q::DEST_STREET)))));
        return [
            'customer_address_id' => null,
            'save_in_address_book' => 0,
            'same_as_billing' => 1,
            'firstname' => (string) $quotation->getData(Q::CUSTOMER_FIRSTNAME),
            'lastname' => (string) $quotation->getData(Q::CUSTOMER_LASTNAME),
            'company' => (string) $quotation->getData(Q::CUSTOMER_COMPANY),
            'email' => (string) $quotation->getCustomerEmail(),
            'telephone' => (string) $quotation->getData(Q::CUSTOMER_PHONE),
            'country_id' => (string) $quotation->getData(Q::DEST_COUNTRY_ID),
            'region_id' => $quotation->getData(Q::DEST_REGION_ID) ? (int) $quotation->getData(Q::DEST_REGION_ID) : null,
            'region' => (string) $quotation->getData(Q::DEST_REGION),
            'postcode' => (string) $quotation->getData(Q::DEST_POSTCODE),
            'city' => (string) $quotation->getData(Q::DEST_CITY),
            'street' => $street ?: [''],
        ];
    }
}
