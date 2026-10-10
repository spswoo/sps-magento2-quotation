<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;
use SPS\Quotation\Model\QuotationCartResolver;

/**
 * sales_model_service_quote_submit_success: the quotation becomes "ordered" and remembers the order.
 * Never throws (the order is already placed and paid).
 */
class MarkQuotationOrdered implements ObserverInterface
{
    public function __construct(
        private readonly QuotationCartResolver $resolver,
        private readonly QuotationRepositoryInterface $repository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        $order = $observer->getEvent()->getData('order');
        if (!$quote || !$order) {
            return;
        }
        try {
            $quotation = $this->resolver->getForCart($quote);
            if (!$quotation) {
                return;
            }
            $quotation->setStatus(QuotationInterface::STATUS_ORDERED);
            $quotation->setData(QuotationInterface::ORDER_ID, (int) $order->getId());
            $quotation->setData(QuotationInterface::TOKEN_HASH, null);
            $this->repository->save($quotation);
        } catch (\Throwable $e) {
            $this->logger->error('SPS_Quotation: could not mark quotation as ordered: ' . $e->getMessage());
        }
    }
}
