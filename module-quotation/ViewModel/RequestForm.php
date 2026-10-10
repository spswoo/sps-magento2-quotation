<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use SPS\Quotation\Model\Config;
use SPS\Quotation\Model\QuoteRequired\Checker;

/**
 * Data for the "Request a shipping quote" form (theme-agnostic). Page-cache safe: nothing customer-specific is
 * rendered; a logged-in customer is recognised when the form is submitted.
 */
class RequestForm implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Checker $checker,
        private readonly UrlInterface $url,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isAvailable(?Product $product): bool
    {
        return $product !== null && $this->config->isRequestFormEnabled() && $this->checker->isProductFlagged($product);
    }

    public function getSubmitUrl(): string
    {
        return $this->url->getUrl('sps_quotation/request/submit');
    }

    public function getIntro(): string
    {
        return $this->config->getRequestIntro();
    }

    /** @return array<int, array{value: string, label: string}> countries the store ships to */
    public function getCountries(): array
    {
        $options = $this->countryCollectionFactory->create()->loadByStore()->toOptionArray(false);
        return array_values(array_map(
            static fn(array $o): array => ['value' => (string) $o['value'], 'label' => (string) $o['label']],
            array_filter($options, static fn(array $o): bool => (string) $o['value'] !== '')
        ));
    }

    public function getDefaultCountry(): string
    {
        return (string) $this->scopeConfig->getValue('general/country/default', ScopeInterface::SCOPE_STORE);
    }
}
