<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuotationItem extends AbstractDb
{
    public const TABLE = 'sps_quotation_item';

    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'item_id');
    }
}
