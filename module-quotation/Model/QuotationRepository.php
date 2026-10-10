<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;
use SPS\Quotation\Model\ResourceModel\Quotation as QuotationResource;
use SPS\Quotation\Model\ResourceModel\QuotationItem as ItemResource;
use SPS\Quotation\Model\ResourceModel\QuotationItem\CollectionFactory as ItemCollectionFactory;

class QuotationRepository implements QuotationRepositoryInterface
{
    public function __construct(
        private readonly QuotationFactory $quotationFactory,
        private readonly QuotationResource $resource,
        private readonly ItemResource $itemResource,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly Config $config
    ) {
    }

    public function getById(int $id): QuotationInterface
    {
        $quotation = $this->quotationFactory->create();
        $this->resource->load($quotation, $id);
        if (!$quotation->getId()) {
            throw new NoSuchEntityException(__('The quotation with ID "%1" does not exist.', $id));
        }
        $items = $this->itemCollectionFactory->create()
            ->addFieldToFilter('quotation_id', $id)
            ->setOrder('sort_order', 'ASC')
            ->setOrder('item_id', 'ASC');
        $quotation->setItems($items->getItems());
        return $quotation;
    }

    public function save(QuotationInterface $quotation): QuotationInterface
    {
        /** @var Quotation $quotation */
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $this->resource->save($quotation);
            if (!$quotation->getIncrementId()) {
                $quotation->setData(
                    QuotationInterface::INCREMENT_ID,
                    $this->config->getNumberPrefix() . str_pad((string) $quotation->getId(), 6, '0', STR_PAD_LEFT)
                );
                $connection->update(
                    $this->resource->getMainTable(),
                    [QuotationInterface::INCREMENT_ID => $quotation->getIncrementId()],
                    ['entity_id = ?' => (int) $quotation->getId()]
                );
            }
            if ($quotation->hasItemsAssigned()) {
                $connection->delete(
                    $this->itemResource->getMainTable(),
                    ['quotation_id = ?' => (int) $quotation->getId()]
                );
                foreach ($quotation->getItems() as $position => $item) {
                    /** @var QuotationItem $item */
                    $item->unsetData('item_id');
                    $item->isObjectNew(true);
                    $item->setData('quotation_id', (int) $quotation->getId());
                    $item->setData('sort_order', $position);
                    $this->itemResource->save($item);
                }
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw new CouldNotSaveException(
                __('Could not save the quotation: %1', $e->getMessage()),
                $e instanceof \Exception ? $e : new \RuntimeException($e->getMessage(), 0, $e)
            );
        }
        return $quotation;
    }

    public function delete(QuotationInterface $quotation): void
    {
        try {
            /** @var Quotation $quotation */
            $this->resource->delete($quotation);
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(
                __('Could not delete the quotation: %1', $e->getMessage()),
                $e instanceof \Exception ? $e : new \RuntimeException($e->getMessage(), 0, $e)
            );
        }
    }
}
