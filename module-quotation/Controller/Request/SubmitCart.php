<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Controller\Request;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use SPS\Quotation\Model\Config;
use SPS\Quotation\Model\RateLimiter;
use SPS\Quotation\Model\RequestService;

/**
 * POST sps_quotation/request/submitCart — "Request a quote for my cart" (AJAX, JSON response). Quotes the
 * customer's current cart; the cart itself is left unchanged. Same protections as the product form: form key
 * (CSRF validation), honeypot, per-IP hourly limit (shared with the product form).
 */
class SubmitCart implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly RequestService $requestService,
        private readonly CheckoutSession $checkoutSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly RateLimiter $rateLimiter,
        private readonly RemoteAddress $remoteAddress,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isRequestFormEnabled($storeId)) {
            return $result->setHttpResponseCode(404)->setData(['success' => false, 'message' => (string) __('Not available.')]);
        }
        $post = (array) $this->request->getPostValue();
        // honeypot: the "website" field is hidden from people; bots fill it in
        if (trim((string) ($post['website'] ?? '')) !== '') {
            return $result->setData(['success' => true, 'message' => $this->successMessage($storeId)]);
        }
        $ip = (string) $this->remoteAddress->getRemoteAddress();
        if (!$this->rateLimiter->hit('request', $ip, $this->config->getRequestLimitPerHour($storeId))) {
            return $result->setHttpResponseCode(429)->setData([
                'success' => false,
                'message' => (string) __('Too many requests. Please try again later or contact us directly.'),
            ]);
        }
        try {
            $cart = $this->checkoutSession->getQuote();
            $customerId = $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : null;
            $quotation = $this->requestService->createFromCart($cart, $post, $storeId, $customerId);
            $merged = (bool) $quotation->getData('merged');
            return $result->setData([
                'success' => true,
                'message' => $merged
                    ? (string) __('Your request %1 has been updated.', $quotation->getIncrementId())
                    : $this->successMessage($storeId),
                'number' => $quotation->getIncrementId(),
                'merged' => $merged,
            ]);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('SPS_Quotation: cart request failed: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('Sorry, your request could not be sent. Please contact us directly.'),
            ]);
        }
    }

    private function successMessage(int $storeId): string
    {
        return $this->config->getRequestSuccessMessage($storeId)
            ?: (string) __('Thank you! We received your request and will e-mail your shipping quote shortly.');
    }
}
