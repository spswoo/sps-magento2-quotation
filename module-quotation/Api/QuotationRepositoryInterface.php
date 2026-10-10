<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Api;

use SPS\Quotation\Api\Data\QuotationInterface;

interface QuotationRepositoryInterface
{
    /**
     * Load a quotation with its items.
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): QuotationInterface;

    /**
     * Save the quotation and replace its items with getItems(); assigns the quotation number on first save.
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(QuotationInterface $quotation): QuotationInterface;

    /**
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(QuotationInterface $quotation): void;
}
