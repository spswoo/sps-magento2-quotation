<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\ViewModel;

use Magento\Catalog\Helper\Product\Configuration as ProductConfiguration;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote;
use SPS\Quotation\Model\Config;
use SPS\Quotation\Model\QuotationCartResolver;
use SPS\Quotation\Model\QuoteRequired\Checker;

/**
 * Data for "Request a quote for my cart" (cart page and checkout; neither is page-cached, so the customer's cart
 * and details can be rendered).
 */
class CartRequest implements ArgumentInterface
{
    private ?bool $available = null;

    public function __construct(
        private readonly Config $config,
        private readonly Checker $checker,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly QuotationCartResolver $resolver,
        private readonly ProductConfiguration $productConfiguration,
        private readonly UrlInterface $url
    ) {
    }

    /** Request form enabled for this visitor, and the cart holds an item that needs a shipping quote. */
    public function isAvailable(): bool
    {
        if ($this->available === null) {
            $quote = $this->getQuote();
            $this->available = $this->config->isRequestFormEnabled()
                && $this->checker->isEnabled()
                && $quote->getId()
                && $quote->getItemsCount()
                && !$this->resolver->isQuotationCart($quote)
                && $this->checker->getFlaggedItemNames($quote->getAllItems()) !== [];
        }
        return $this->available;
    }

    public function getSubmitUrl(): string
    {
        return $this->url->getUrl('sps_quotation/request/submitCart');
    }

    /**
     * Cart lines as the customer sees them.
     *
     * @return array<int, array{name: string, qty: float, options: string[]}>
     */
    public function getItems(): array
    {
        $items = [];
        foreach ($this->getQuote()->getAllVisibleItems() as $item) {
            $options = [];
            foreach ($this->productConfiguration->getCustomOptions($item) as $option) {
                $value = $option['print_value'] ?? ($option['value'] ?? '');
                $value = trim(strip_tags(is_array($value) ? implode(', ', $value) : (string) $value));
                if ($value !== '') {
                    $options[] = (string) ($option['label'] ?? '') . ': ' . $value;
                }
            }
            $items[] = ['name' => (string) $item->getName(), 'qty' => (float) $item->getQty(), 'options' => $options];
        }
        return $items;
    }

    /**
     * Known contact/destination details: the cart's shipping address (e.g. entered in checkout), else the logged-in
     * customer's default shipping address.
     *
     * @return array<string, string> form field name => value
     */
    public function getPrefill(): array
    {
        $quote = $this->getQuote();
        $address = $quote->getShippingAddress();
        $data = [
            'firstname' => (string) $address->getFirstname(),
            'lastname' => (string) $address->getLastname(),
            'email' => (string) ($quote->getCustomerEmail() ?: $address->getEmail()),
            'phone' => (string) $address->getTelephone(),
            'company' => (string) $address->getCompany(),
            'country_id' => (string) $address->getCountryId(),
            'region_id' => $address->getRegionId() ? (string) $address->getRegionId() : '',
            'region' => (string) $address->getRegion(),
            'postcode' => (string) $address->getPostcode(),
            'city' => (string) $address->getCity(),
        ];
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            $data['email'] = $data['email'] ?: (string) $customer->getEmail();
            $data['firstname'] = $data['firstname'] ?: (string) $customer->getFirstname();
            $data['lastname'] = $data['lastname'] ?: (string) $customer->getLastname();
            $default = $customer->getDefaultShippingAddress();
            if ($default && $data['postcode'] === '') {
                $data = array_merge($data, array_filter([
                    'phone' => (string) $default->getTelephone(),
                    'company' => (string) $default->getCompany(),
                    'country_id' => (string) $default->getCountryId(),
                    'region_id' => $default->getRegionId() ? (string) $default->getRegionId() : '',
                    'region' => (string) $default->getRegion(),
                    'postcode' => (string) $default->getPostcode(),
                    'city' => (string) $default->getCity(),
                ]));
            }
        }
        return array_map('trim', $data);
    }

    private function getQuote(): Quote
    {
        return $this->checkoutSession->getQuote();
    }
}
