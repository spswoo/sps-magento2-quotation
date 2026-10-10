<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Controller\Adminhtml\Quotation;

use Magento\Backend\App\Action;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use SPS\Quotation\Model\ItemOptions;

/**
 * Product lookup for the editor (SKU or name). v1 supports simple and virtual products incl. custom options.
 */
class ProductSearch extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'SPS_Quotation::manage';

    private const LIMIT = 15;

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ItemOptions $itemOptions
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $term = trim((string) $this->getRequest()->getParam('q'));
        $storeId = (int) $this->getRequest()->getParam('store_id');
        $result = $this->jsonFactory->create();
        if (mb_strlen($term) < 2) {
            return $result->setData(['items' => []]);
        }
        $like = '%' . addcslashes($term, '%_\\') . '%';
        $collection = $this->collectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'price', 'special_price', 'special_from_date', 'special_to_date'])
            ->addAttributeToFilter('type_id', ['in' => [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL]])
            ->addAttributeToFilter([
                ['attribute' => 'sku', 'like' => $like],
                ['attribute' => 'name', 'like' => $like],
            ])
            ->setPageSize(self::LIMIT)
            ->addOptionsToResult();
        $items = [];
        foreach ($collection as $product) {
            /** @var Product $product */
            $items[] = [
                'product_id' => (int) $product->getId(),
                'sku' => (string) $product->getSku(),
                'name' => (string) $product->getName(),
                'original_price' => round((float) $product->getFinalPrice(), 2),
                'definitions' => $this->itemOptions->getDefinitions($product),
            ];
        }
        return $result->setData(['items' => $items]);
    }
}
