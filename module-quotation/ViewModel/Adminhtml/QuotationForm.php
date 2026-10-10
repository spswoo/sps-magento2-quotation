<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\ViewModel\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;
use SPS\Quotation\Model\Config;
use SPS\Quotation\Model\ItemOptions;
use SPS\Quotation\Model\Source\RequestSource;
use SPS\Quotation\Model\Quotation;
use SPS\Quotation\Model\QuotationFactory;
use SPS\Quotation\Model\QuotationItem;
use SPS\Quotation\Model\Source\Status;

/**
 * Data for the admin quotation editor (Sales > Quotations > Open / Add New).
 */
class QuotationForm implements ArgumentInterface
{
    private ?Quotation $quotation = null;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly QuotationRepositoryInterface $repository,
        private readonly QuotationFactory $quotationFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly RegionCollectionFactory $regionCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ItemOptions $itemOptions,
        private readonly Status $status,
        private readonly Config $config,
        private readonly UrlInterface $url,
        private readonly AuthorizationInterface $authorization,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function getQuotation(): Quotation
    {
        if ($this->quotation === null) {
            $id = (int) $this->request->getParam('id');
            if ($id) {
                /** @var Quotation $quotation */
                $quotation = $this->repository->getById($id);
            } else {
                $quotation = $this->quotationFactory->create();
                $quotation->setData(QuotationInterface::STORE_ID, (int) $this->storeManager->getDefaultStoreView()?->getId());
                $quotation->setData(QuotationInterface::FREIGHT_LABEL, $this->config->getDefaultFreightLabel());
                $quotation->setItems([]);
            }
            $this->quotation = $quotation;
        }
        return $this->quotation;
    }

    public function get(string $field): string
    {
        return (string) $this->getQuotation()->getData($field);
    }

    public function getStatusLabel(): string
    {
        return $this->getQuotation()->getId() ? $this->status->getLabel($this->getQuotation()->getStatus()) : (string) __('New');
    }

    /** "Cart (3 items)" / "Product page" / "Admin" — '' for quotations created before sources were recorded. */
    public function getSourceLabel(): string
    {
        $quotation = $this->getQuotation();
        $label = (new RequestSource())->getLabel($quotation->getData(QuotationInterface::SOURCE));
        if ($label !== '' && $quotation->getData(QuotationInterface::SOURCE) === QuotationInterface::SOURCE_CART) {
            $label = (string) __('%1 (%2 items)', $label, count($quotation->getItems()));
        }
        return $label;
    }

    public function isLocked(): bool
    {
        return in_array($this->getQuotation()->getStatus(), [QuotationInterface::STATUS_ORDERED], true);
    }

    public function isAllowed(string $resource): bool
    {
        return $this->authorization->isAllowed($resource);
    }

    /** @return array<int, string> store id => "Website / Store / Store view" */
    public function getStores(): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[(int) $store->getId()] = $store->getWebsite()->getName() . ' / ' . $store->getName();
        }
        return $stores;
    }

    /** @return array<int, array{value: string, label: string}> */
    public function getCountries(): array
    {
        return array_values(array_filter(
            $this->countryCollectionFactory->create()->loadByStore()->toOptionArray(false),
            static fn(array $o): bool => (string) $o['value'] !== ''
        ));
    }

    public function formatPrice(?float $amount): string
    {
        if ($amount === null) {
            return '';
        }
        $quotation = $this->getQuotation();
        return (string) $this->priceCurrency->format(
            $amount,
            false,
            2,
            $quotation->getStoreId(),
            $quotation->getData(QuotationInterface::CURRENCY_CODE) ?: null
        );
    }

    public function url(string $route, array $params = []): string
    {
        return $this->url->getUrl($route, $params);
    }

    /** Configuration for the editor script. */
    public function getJsConfig(): string
    {
        $regions = [];
        $countryIds = array_column($this->getCountries(), 'value');
        $collection = $this->regionCollectionFactory->create()->addCountryFilter($countryIds ?: ['__none__']);
        foreach ($collection as $region) {
            $regions[(string) $region->getCountryId()][] = [
                'id' => (int) $region->getRegionId(),
                'name' => (string) ($region->getName() ?: $region->getDefaultName()),
            ];
        }
        // embedded in an inline <script>: escape < > & ' " so data can never close the tag
        return (string) json_encode([
            'searchUrl' => $this->url->getUrl('sps_quotation/quotation/productSearch'),
            'regions' => $regions,
            'items' => $this->getItemsData(),
            'locked' => $this->isLocked(),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItemsData(): array
    {
        $quotation = $this->getQuotation();
        $rows = [];
        foreach ($quotation->getItems() as $item) {
            /** @var QuotationItem $item */
            $definitions = [];
            if ($item->getProductId()) {
                try {
                    /** @var Product $product */
                    $product = $this->productRepository->getById((int) $item->getProductId(), false, $quotation->getStoreId());
                    $definitions = $this->itemOptions->getDefinitions($product);
                } catch (\Throwable) {
                    $definitions = [];
                }
            }
            $rows[] = [
                'product_id' => $item->getProductId(),
                'sku' => (string) $item->getSku(),
                'name' => (string) $item->getName(),
                'qty' => $item->getQty(),
                'price' => $item->getPrice(),
                'original_price' => $item->getOriginalPrice(),
                'options' => $item->getBuyRequestOptions()['options'] ?? new \stdClass(),
                'definitions' => $definitions,
                // passed back unchanged on save; details = what the customer's cart showed (read-only)
                'extra' => $this->itemOptions->getExtra($item->getBuyRequestOptions()) ?: new \stdClass(),
                'details' => $this->extraLabels($item->getBuyRequestOptions()),
            ];
        }
        return $rows;
    }

    /**
     * Labels of the cart snapshot that the editable custom options don't cover.
     *
     * @param array<string, mixed> $stored
     * @return array<int, array{label: string, value: string}>
     */
    private function extraLabels(array $stored): array
    {
        $labels = [];
        foreach ((array) ($stored[ItemOptions::DISPLAY_KEY] ?? []) as $entry) {
            if (is_array($entry)) {
                $labels[] = ['label' => (string) ($entry['label'] ?? ''), 'value' => (string) ($entry['value'] ?? '')];
            }
        }
        return $labels;
    }
}
