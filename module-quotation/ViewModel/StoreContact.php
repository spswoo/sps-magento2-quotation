<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use SPS\Quotation\Model\QuoteRequired\Checker;

/** Store phone and contact e-mail (Store Information / General Contact) for customer messages. */
class StoreContact implements ArgumentInterface
{
    public function __construct(private readonly Checker $checker)
    {
    }

    public function getPhone(): string
    {
        return $this->checker->getPhone();
    }

    public function getEmail(): string
    {
        return $this->checker->getEmail();
    }
}
