<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Model\Destination;
use SPS\Quotation\Model\Messages;
use SPS\Quotation\Model\QuotationCartResolver;

/**
 * Cart page / checkout notices for a cart built from a quotation (theme-agnostic data; plain-text messages).
 */
class QuotationNotice implements ArgumentInterface
{
    private ?QuotationInterface $quotation = null;
    private bool $loaded = false;

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly QuotationCartResolver $resolver,
        private readonly Destination $destination,
        private readonly Messages $messages,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function getQuotation(): ?QuotationInterface
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $quotation = $this->resolver->getForCart($this->checkoutSession->getQuote());
            $this->quotation = $quotation && $this->resolver->isPayable($quotation) ? $quotation : null;
        }
        return $this->quotation;
    }

    public function getSummary(): string
    {
        $quotation = $this->getQuotation();
        if (!$quotation) {
            return '';
        }
        return (string) __(
            'Quotation %1: the quoted prices apply, and %2 freight to %3 (%4). Coupons and other promotions are not combined with a quotation.',
            $quotation->getIncrementId(),
            $this->priceCurrency->format((float) $quotation->getFreightAmount(), false, 2, $quotation->getStoreId()),
            $this->destination->label($quotation),
            (string) $quotation->getData(QuotationInterface::FREIGHT_LABEL)
        );
    }

    /** Explanation when the entered shipping address is outside the quoted destination ('' otherwise). */
    public function getDestinationWarning(): string
    {
        $quotation = $this->getQuotation();
        if (!$quotation) {
            return '';
        }
        $address = $this->checkoutSession->getQuote()->getShippingAddress();
        if (!$address->getCountryId() || !$address->getPostcode()
            || $this->destination->matches($quotation, $address->getCountryId(), $address->getPostcode())
        ) {
            return '';
        }
        return $this->messages->destinationMismatch($quotation);
    }
}
