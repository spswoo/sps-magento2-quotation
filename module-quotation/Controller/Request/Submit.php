<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Controller\Request;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility;
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
 * POST sps_quotation/request/submit — "Request a shipping quote" form (AJAX, JSON response).
 * Form key is checked by Magento's CSRF validation for POST requests; a honeypot field and a per-IP hourly limit
 * keep bots out.
 */
class Submit implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly RequestService $requestService,
        private readonly ProductRepositoryInterface $productRepository,
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
            $product = $this->productRepository->getById((int) ($post['product_id'] ?? 0), false, $storeId);
            // enabled + visible is enough: a quote can be requested for an item that is temporarily out of stock
            $visible = [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_IN_SEARCH, Visibility::VISIBILITY_BOTH];
            if ((int) $product->getStatus() !== ProductStatus::STATUS_ENABLED
                || !in_array((int) $product->getVisibility(), $visible, true)
            ) {
                throw new LocalizedException(__('This product is not available.'));
            }
            $customerId = $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : null;
            $quotation = $this->requestService->create($product, $post, $storeId, $customerId);
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
            $this->logger->error('SPS_Quotation: request failed: ' . $e->getMessage(), ['exception' => $e]);
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
