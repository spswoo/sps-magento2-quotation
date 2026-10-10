<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use SPS\Quotation\Model\Config;

class Mode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::MODE_OFF, 'label' => __('No')],
            ['value' => Config::MODE_ON, 'label' => __('Yes')],
            ['value' => Config::MODE_DEVELOPER_IPS, 'label' => __('Only for developer IPs (testing)')],
        ];
    }
}
