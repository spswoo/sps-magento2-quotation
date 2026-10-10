<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Model\Quote;

/**
 * The request forms only ask for what the freight depends on (country, province, postal code, city). When the store
 * already knows a street for that destination — the address typed into the checkout, or a saved address of the
 * customer with the same country + postal code — it is kept, so the customer doesn't have to type it again.
 */
class KnownStreet
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Destination $destination
    ) {
    }

    /**
     * Street lines ("\n"-separated) for the destination, '' when none is known.
     */
    public function find(string $country, string $postcode, ?Quote $cart = null, ?int $customerId = null): string
    {
        if ($country === '' || $postcode === '') {
            return '';
        }
        if ($cart) {
            $address = $cart->getShippingAddress();
            if ($this->same($country, $postcode, (string) $address->getCountryId(), (string) $address->getPostcode())) {
                $street = $this->join((array) $address->getStreet());
                if ($street !== '') {
                    return $street;
                }
            }
        }
        if (!$customerId) {
            return '';
        }
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (\Throwable) {
            return '';
        }
        $defaultId = (string) $customer->getDefaultShipping();
        $addresses = $customer->getAddresses() ?: [];
        // default shipping address first
        usort($addresses, static fn($a, $b): int => ((string) $b->getId() === $defaultId) <=> ((string) $a->getId() === $defaultId));
        foreach ($addresses as $address) {
            if ($this->same($country, $postcode, (string) $address->getCountryId(), (string) $address->getPostcode())) {
                $street = $this->join((array) $address->getStreet());
                if ($street !== '') {
                    return $street;
                }
            }
        }
        return '';
    }

    private function same(string $country, string $postcode, string $otherCountry, string $otherPostcode): bool
    {
        return strtoupper($country) === strtoupper($otherCountry)
            && $this->destination->normalizePostcode($country, $postcode) !== ''
            && $this->destination->normalizePostcode($country, $postcode)
                === $this->destination->normalizePostcode($country, $otherPostcode);
    }

    /** @param array<int, mixed> $lines */
    private function join(array $lines): string
    {
        return mb_substr(implode("\n", array_filter(array_map(
            static fn($line): string => trim(strip_tags((string) $line)),
            $lines
        ))), 0, 1000);
    }
}
