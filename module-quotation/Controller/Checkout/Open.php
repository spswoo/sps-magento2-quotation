<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use SPS\Quotation\Api\Data\QuotationInterface as Q;
use SPS\Quotation\Api\QuotationRepositoryInterface;
use SPS\Quotation\Model\CartBuilder;
use SPS\Quotation\Model\Config;
use SPS\Quotation\Model\Quotation;
use SPS\Quotation\Model\QuotationCartResolver;
use SPS\Quotation\Model\Token;
use SPS\Quotation\Model\Validity;

/**
 * GET sps_quotation/checkout/open/id/<id>/token/<token> — the "Review and pay" link from the quotation e-mail.
 * Builds (or re-uses) the quotation cart and opens the checkout; unusable links get an explanation page.
 * No login needed: the token authorises the visitor.
 */
class Open implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly QuotationRepositoryInterface $repository,
        private readonly Token $token,
        private readonly Validity $validity,
        private readonly QuotationCartResolver $resolver,
        private readonly CartBuilder $cartBuilder,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        try {
            /** @var Quotation $quotation */
            $quotation = $this->repository->getById((int) $this->request->getParam('id'));
        } catch (NoSuchEntityException) {
            return $this->unavailable('invalid');
        }
        if (!$this->token->verify((string) $this->request->getParam('token'), $quotation->getData(Q::TOKEN_HASH))) {
            return $this->unavailable($quotation->getStatus() === Q::STATUS_ORDERED ? 'ordered' : 'invalid', $quotation);
        }
        if ($quotation->getStatus() === Q::STATUS_ORDERED) {
            return $this->unavailable('ordered', $quotation);
        }
        if (!$this->resolver->isPayable($quotation)) {
            return $this->unavailable('cancelled', $quotation);
        }
        if ($this->validity->isExpired($quotation)) {
            return $this->unavailable('expired', $quotation);
        }

        try {
            $quote = $this->reusableCart($quotation) ?? $this->cartBuilder->build($quotation, $this->customerId());
        } catch (LocalizedException $e) {
            return $this->unavailable('item', $quotation, $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('SPS_Quotation: checkout link failed: ' . $e->getMessage(), ['exception' => $e]);
            return $this->unavailable('error', $quotation);
        }
        if ($quotation->getStatus() === Q::STATUS_SENT) {
            $quotation->setStatus(Q::STATUS_ACCEPTED);
            $this->repository->save($quotation);
        }
        $this->checkoutSession->replaceQuote($quote);
        return $this->redirectFactory->create()->setPath('checkout');
    }

    /** The cart built on an earlier visit, if it is still open and belongs to the same visitor. */
    private function reusableCart(Quotation $quotation): ?\Magento\Quote\Model\Quote
    {
        $quoteId = (int) $quotation->getData(Q::QUOTE_ID);
        if (!$quoteId) {
            return null;
        }
        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->cartRepository->getActive($quoteId);
        } catch (NoSuchEntityException) {
            return null;
        }
        return (int) $quote->getCustomerId() === (int) $this->customerId() ? $quote : null;
    }

    private function customerId(): ?int
    {
        return $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : null;
    }

    private function unavailable(string $reason, ?Quotation $quotation = null, string $detail = ''): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->addHandle('sps_quotation_checkout_unavailable');
        $page->getConfig()->getTitle()->set(__('Quotation'));
        $block = $page->getLayout()->getBlock('sps.quotation.unavailable');
        if ($block) {
            $block->setData('reason', $reason);
            $block->setData('quotation_number', $quotation?->getIncrementId());
            $block->setData('detail', $detail);
        }
        if ($reason === 'invalid') {
            $page->setHttpResponseCode(404);
        }
        return $page;
    }
}
