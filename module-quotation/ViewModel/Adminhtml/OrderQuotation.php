<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\ViewModel\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Model\ResourceModel\Quotation\CollectionFactory;

/** Admin order view: the quotation the order was placed from, if any. */
class OrderQuotation implements ArgumentInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CollectionFactory $collectionFactory,
        private readonly UrlInterface $url
    ) {
    }

    public function getQuotation(): ?QuotationInterface
    {
        $orderId = (int) $this->request->getParam('order_id');
        if (!$orderId) {
            return null;
        }
        $quotation = $this->collectionFactory->create()
            ->addFieldToFilter(QuotationInterface::ORDER_ID, $orderId)
            ->setPageSize(1)
            ->getFirstItem();
        return $quotation->getId() ? $quotation : null;
    }

    public function getEditUrl(QuotationInterface $quotation): string
    {
        return $this->url->getUrl('sps_quotation/quotation/edit', ['id' => $quotation->getId()]);
    }
}
