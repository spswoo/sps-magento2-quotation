<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Model\QuoteRequired\Checker;

/** Customer-facing texts shared by the freight carrier and the checkout/cart notices. */
class Messages
{
    public function __construct(
        private readonly Destination $destination,
        private readonly Checker $checker
    ) {
    }

    public function destinationMismatch(QuotationInterface $quotation): string
    {
        return (string) __(
            'The freight price of quotation %1 applies to %2. To ship elsewhere, please contact us (%3) for an updated quotation.',
            $quotation->getIncrementId(),
            $this->destination->label($quotation),
            $this->contact($quotation->getStoreId())
        );
    }

    public function contact(?int $storeId): string
    {
        return implode(', ', array_filter([$this->checker->getPhone($storeId), $this->checker->getEmail($storeId)]));
    }
}
