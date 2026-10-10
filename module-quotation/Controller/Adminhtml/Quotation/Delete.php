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

/** Delete (e.g. spam requests). Ordered quotations are kept for the record. */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'SPS_Quotation::delete';

    public function __construct(Action\Context $context, private readonly QuotationRepositoryInterface $repository)
    {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $quotation = $this->repository->getById($id);
            if ($quotation->getStatus() === QuotationInterface::STATUS_ORDERED) {
                $this->messageManager->addErrorMessage(__('An ordered quotation cannot be deleted.'));
                return $redirect->setPath('*/*/edit', ['id' => $id]);
            }
            $this->repository->delete($quotation);
            $this->messageManager->addSuccessMessage(__('Quotation %1 deleted.', $quotation->getIncrementId()));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('*/*/');
    }
}
