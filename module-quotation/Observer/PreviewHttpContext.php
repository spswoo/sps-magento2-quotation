<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Observer;

use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SPS\Quotation\Model\Config;

/**
 * "Only for developer IPs" test mode: allow-listed visitors get their own full-page-cache variant, so a page
 * rendered with the quotation features is never served to other visitors (and vice versa).
 */
class PreviewHttpContext implements ObserverInterface
{
    public const CONTEXT_KEY = 'sps_quotation_preview';

    public function __construct(
        private readonly Config $config,
        private readonly HttpContext $httpContext
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->config->getMode() === Config::MODE_DEVELOPER_IPS && $this->config->isDeveloperIp()) {
            $this->httpContext->setValue(self::CONTEXT_KEY, 1, 0);
        }
    }
}
