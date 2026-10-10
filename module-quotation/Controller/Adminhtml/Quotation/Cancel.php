<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Controller\Adminhtml\Quotation;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;

/** Cancel: the checkout link stops working. */
class Cancel extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'SPS_Quotation::manage';

    public function __construct(Action\Context $context, private readonly QuotationRepositoryInterface $repository)
    {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $quotation = $this->repository->getById($id);
            if ($quotation->getStatus() === QuotationInterface::STATUS_ORDERED) {
                $this->messageManager->addErrorMessage(__('An ordered quotation cannot be cancelled.'));
            } else {
                $quotation->setStatus(QuotationInterface::STATUS_CANCELLED);
                $quotation->setData(QuotationInterface::TOKEN_HASH, null);
                $this->repository->save($quotation);
                $this->messageManager->addSuccessMessage(__('Quotation %1 cancelled.', $quotation->getIncrementId()));
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/edit', ['id' => $id]);
    }
}
