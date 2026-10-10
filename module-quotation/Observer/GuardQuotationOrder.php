<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Model\Carrier\QuotationFreight;
use SPS\Quotation\Model\Destination;
use SPS\Quotation\Model\QuotationCartResolver;
use SPS\Quotation\Model\QuotationItem;
use SPS\Quotation\Model\Validity;

/**
 * sales_model_service_quote_submit_before (before payment): an order from a quotation cart is only placed when
 * the quotation can still be paid and the cart still equals it — same products, quantities and prices, the
 * quoted freight to the quoted destination, no discount.
 */
class GuardQuotationOrder implements ObserverInterface
{
    private const TOLERANCE = 0.005;

    public function __construct(
        private readonly QuotationCartResolver $resolver,
        private readonly Destination $destination,
        private readonly Validity $validity
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        $quotation = $quote ? $this->resolver->getForCart($quote) : null;
        if (!$quotation) {
            return;
        }
        if (!$this->resolver->isPayable($quotation)) {
            throw new LocalizedException(__('Quotation %1 can no longer be ordered. Please contact us.', $quotation->getIncrementId()));
        }
        if ($this->validity->isExpired($quotation)) {
            throw new LocalizedException(__('Quotation %1 has expired. Please contact us for an updated quotation.', $quotation->getIncrementId()));
        }
        if ($this->signature($this->cartLines($quote)) !== $this->signature($this->quotedLines($quotation))) {
            throw $this->mismatch($quotation, (string) __('the items, quantities or prices were changed'));
        }
        $address = $quote->getShippingAddress();
        if ($address->getShippingMethod() !== QuotationFreight::CODE . '_' . QuotationFreight::METHOD
            || abs((float) $address->getBaseShippingAmount() - (float) $quotation->getFreightAmount()) > self::TOLERANCE
        ) {
            throw $this->mismatch($quotation, (string) __('the quoted freight is not selected'));
        }
        if (!$this->destination->matches($quotation, $address->getCountryId(), $address->getPostcode())) {
            throw $this->mismatch($quotation, (string) __('the shipping address is outside the quoted destination'));
        }
        if (abs((float) $address->getBaseDiscountAmount()) > self::TOLERANCE) {
            throw $this->mismatch($quotation, (string) __('a discount was applied'));
        }
    }

    /** @return string[] one "product|qty|price" line per cart item */
    private function cartLines(Quote $quote): array
    {
        $lines = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $price = $item->getCustomPrice() ?? $item->getOriginalCustomPrice();
            $lines[] = $this->line((int) $item->getProductId(), (float) $item->getQty(), $price === null ? null : (float) $price);
        }
        return $lines;
    }

    /** @return string[] */
    private function quotedLines(QuotationInterface $quotation): array
    {
        $lines = [];
        foreach ($quotation->getItems() as $item) {
            /** @var QuotationItem $item */
            $lines[] = $this->line((int) $item->getProductId(), $item->getQty(), $item->getPrice());
        }
        return $lines;
    }

    private function line(int $productId, float $qty, ?float $price): string
    {
        return $productId . '|' . round($qty, 4) . '|' . ($price === null ? '-' : number_format($price, 2, '.', ''));
    }

    /** @param string[] $lines */
    private function signature(array $lines): string
    {
        sort($lines);
        return implode(';', $lines);
    }

    private function mismatch(QuotationInterface $quotation, string $reason): LocalizedException
    {
        return new LocalizedException(__(
            'Your cart no longer matches quotation %1 (%2). Please open the link in your quotation e-mail again.',
            $quotation->getIncrementId(),
            $reason
        ));
    }
}
