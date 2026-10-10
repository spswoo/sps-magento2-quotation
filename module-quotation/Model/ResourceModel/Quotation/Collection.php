<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model\ResourceModel\Quotation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use SPS\Quotation\Model\Quotation;
use SPS\Quotation\Model\ResourceModel\Quotation as QuotationResource;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(Quotation::class, QuotationResource::class);
    }
}
