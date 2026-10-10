<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\QuotationHyvaCheckout\Magewire;

use Magewirephp\Magewire\Component;

/**
 * Re-renders the quotation notice in the shipping step whenever the shipping address changes, so the
 * "outside the quoted destination" warning appears/disappears together with the freight rate.
 */
class QuotationNotice extends Component
{
    /** @var array<string, string> */
    protected $listeners = [
        'shipping_address_saved' => 'refresh',
        'shipping_address_activated' => 'refresh',
    ];
}
