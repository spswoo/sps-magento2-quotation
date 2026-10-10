<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\QuotationHyva\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Registers this module in app/etc/hyva-themes.json (bin/magento hyva:config:generate), so every Hyvä theme's
 * Tailwind build scans its templates.
 * @see https://docs.hyva.io/hyva-themes/compatibility-modules/tailwind-config-merging.html
 */
class HyvaConfigGenerateBefore implements ObserverInterface
{
    public function __construct(private readonly ComponentRegistrar $componentRegistrar)
    {
    }

    public function execute(Observer $observer): void
    {
        $config = $observer->getData('config');
        $extensions = $config->hasData('extensions') ? $config->getData('extensions') : [];
        $path = (string) $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'SPS_QuotationHyva');
        // paths inside the Magento root are stored relative to it; others (e.g. symlinked path repositories) absolute
        $root = rtrim(BP, '/') . '/';
        $extensions[] = ['src' => str_starts_with($path, $root) ? substr($path, strlen($root)) : $path];
        $config->setData('extensions', $extensions);
    }
}
