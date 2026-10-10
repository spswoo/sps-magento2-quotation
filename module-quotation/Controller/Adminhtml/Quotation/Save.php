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
use Magento\Framework\Exception\LocalizedException;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;
use SPS\Quotation\Model\Adminhtml\FormHandler;
use SPS\Quotation\Model\Quotation;
use SPS\Quotation\Model\QuotationFactory;
use SPS\Quotation\Model\QuotationSender;

/**
 * Save the editor; with send=1 (and the "Send to Customers" permission) also e-mail the quotation.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'SPS_Quotation::manage';

    public function __construct(
        Action\Context $context,
        private readonly QuotationRepositoryInterface $repository,
        private readonly QuotationFactory $quotationFactory,
        private readonly FormHandler $formHandler,
        private readonly QuotationSender $sender
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('id');
        try {
            if ($id) {
                /** @var Quotation $quotation */
                $quotation = $this->repository->getById($id);
            } else {
                $quotation = $this->quotationFactory->create();
                $quotation->setStatus(QuotationInterface::STATUS_DRAFT);
            }
            $this->formHandler->apply(
                $quotation,
                (array) $this->getRequest()->getParam('quotation', []),
                (array) $this->getRequest()->getParam('items', [])
            );
            $this->repository->save($quotation);
            $this->messageManager->addSuccessMessage(__('Quotation %1 saved.', $quotation->getIncrementId()));
            if ($this->getRequest()->getParam('send')) {
                if (!$this->_authorization->isAllowed('SPS_Quotation::send')) {
                    throw new LocalizedException(__('You are not allowed to send quotations.'));
                }
                $this->sender->send($quotation);
                $this->messageManager->addSuccessMessage(
                    __('Quotation %1 was e-mailed to %2.', $quotation->getIncrementId(), $quotation->getCustomerEmail())
                );
            }
            return $redirect->setPath('*/*/edit', ['id' => $quotation->getId()]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the quotation.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the quotation: %1', $e->getMessage()));
        }
        return isset($quotation) && $quotation->getId()
            ? $redirect->setPath('*/*/edit', ['id' => $quotation->getId()])
            : $redirect->setPath('*/*/');
    }
}
