<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlFactory;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;

/**
 * Sends a priced quotation to the customer: validates it, issues a new checkout-link token (an earlier link stops
 * working), sets the expiry date if empty, marks it "sent" and e-mails the link.
 */
class QuotationSender
{
    public function __construct(
        private readonly QuotationRepositoryInterface $repository,
        private readonly TotalsCalculator $totals,
        private readonly ItemOptions $itemOptions,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EmailSender $emailSender,
        private readonly Token $token,
        private readonly Config $config,
        private readonly DateTime $dateTime,
        private readonly UrlFactory $urlFactory
    ) {
    }

    /**
     * @throws LocalizedException listing what is missing
     */
    public function send(QuotationInterface $quotation): void
    {
        /** @var Quotation $quotation */
        $this->validate($quotation);
        $storeId = (int) $quotation->getStoreId();
        if (!$quotation->getExpiresAt() || $quotation->getExpiresAt() < $this->dateTime->gmtDate('Y-m-d')) {
            $quotation->setData(
                QuotationInterface::EXPIRES_AT,
                $this->dateTime->gmtDate('Y-m-d', '+' . $this->config->getValidDays($storeId) . ' days')
            );
        }
        [$plain, $hash] = $this->token->generate();
        $quotation->setData(QuotationInterface::TOKEN_HASH, $hash);
        $quotation->setStatus(QuotationInterface::STATUS_SENT);
        $quotation->setData(QuotationInterface::SENT_AT, $this->dateTime->gmtDate());
        $this->totals->collect($quotation);
        $this->repository->save($quotation);
        $this->emailSender->sendQuotation($quotation, $this->checkoutUrl($quotation, $plain));
    }

    public function checkoutUrl(QuotationInterface $quotation, string $token): string
    {
        return $this->urlFactory->create()->setScope((int) $quotation->getStoreId())->getUrl(
            'sps_quotation/checkout/open',
            ['id' => (int) $quotation->getId(), 'token' => $token, '_nosid' => true]
        );
    }

    /**
     * @throws LocalizedException
     */
    private function validate(Quotation $quotation): void
    {
        $errors = [];
        if (in_array($quotation->getStatus(), [QuotationInterface::STATUS_ORDERED, QuotationInterface::STATUS_CANCELLED], true)) {
            $errors[] = __('An ordered or cancelled quotation cannot be sent.');
        }
        if (!filter_var((string) $quotation->getCustomerEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('Enter the customer e-mail address.');
        }
        if (!$quotation->getData(QuotationInterface::DEST_COUNTRY_ID) || !$quotation->getData(QuotationInterface::DEST_POSTCODE)) {
            $errors[] = __('Enter the destination country and postal code (the freight price applies to them).');
        }
        if ($quotation->getFreightAmount() === null) {
            $errors[] = __('Enter the freight charge (0 for free shipping).');
        }
        if (!$quotation->getItems()) {
            $errors[] = __('Add at least one product.');
        }
        foreach ($quotation->getItems() as $item) {
            /** @var QuotationItem $item */
            if ($item->getPrice() === null) {
                $errors[] = __('Enter a price for %1.', $item->getName());
            }
            if (!$item->getProductId()) {
                $errors[] = __('%1 is no longer in the catalog. Remove it or choose another product.', $item->getName());
                continue;
            }
            try {
                /** @var Product $product */
                $product = $this->productRepository->getById((int) $item->getProductId(), false, $quotation->getStoreId());
                $this->itemOptions->normalize($product, $item->getBuyRequestOptions()['options'] ?? [], true);
            } catch (LocalizedException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            throw new LocalizedException(__('The quotation was not sent: %1', implode(' ', array_map('strval', $errors))));
        }
    }
}
