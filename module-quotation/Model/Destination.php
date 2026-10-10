<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use SPS\Quotation\Api\Data\QuotationInterface;

/**
 * The quoted freight applies to one destination: country + postal code. Postal codes are compared normalized
 * (case, spaces and dashes ignored; US ZIP+4 compared by the 5-digit ZIP).
 */
class Destination
{
    public function normalizePostcode(?string $country, ?string $postcode): string
    {
        $value = strtoupper((string) preg_replace('/[\s\-]+/', '', (string) $postcode));
        if (strtoupper((string) $country) === 'US') {
            $value = substr($value, 0, 5);
        }
        return $value;
    }

    public function matches(QuotationInterface $quotation, ?string $country, ?string $postcode): bool
    {
        $quotedCountry = strtoupper((string) $quotation->getData(QuotationInterface::DEST_COUNTRY_ID));
        if ($quotedCountry === '' || strtoupper((string) $country) !== $quotedCountry) {
            return false;
        }
        $quoted = $this->normalizePostcode($quotedCountry, (string) $quotation->getData(QuotationInterface::DEST_POSTCODE));
        return $quoted !== '' && $quoted === $this->normalizePostcode($quotedCountry, $postcode);
    }

    public function label(QuotationInterface $quotation): string
    {
        return trim((string) $quotation->getData(QuotationInterface::DEST_POSTCODE) . ', '
            . (string) $quotation->getData(QuotationInterface::DEST_COUNTRY_ID), ' ,');
    }
}
