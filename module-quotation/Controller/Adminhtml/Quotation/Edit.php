<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Controller\Adminhtml\Quotation;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use SPS\Quotation\Api\QuotationRepositoryInterface;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'SPS_Quotation::quotations';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $pageFactory,
        private readonly QuotationRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        $title = __('New Quotation');
        if ($id) {
            try {
                $title = __('Quotation # %1', $this->repository->getById($id)->getIncrementId());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } elseif (!$this->_authorization->isAllowed('SPS_Quotation::manage')) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $page = $this->pageFactory->create();
        $page->setActiveMenu('SPS_Quotation::quotations_menu');
        $page->getConfig()->getTitle()->prepend(__('Quotations'));
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}
