<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Plugin\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CouponManagementInterface;
use SPS\Quotation\Model\QuotationCartResolver;

/** Coupons cannot be used on a quotation (storefront, Hyvä Checkout and REST all go through this service). */
class BlockCouponForQuotation
{
    public function __construct(private readonly QuotationCartResolver $resolver)
    {
    }

    /**
     * @param int $cartId
     * @param string $couponCode
     * @throws LocalizedException
     */
    public function beforeSet(CouponManagementInterface $subject, $cartId, $couponCode): void
    {
        $quotation = $this->resolver->getForCart((int) $cartId);
        if ($quotation) {
            throw new LocalizedException(
                __('Coupon codes cannot be used with quotation %1 — its prices are already final.', $quotation->getIncrementId())
            );
        }
    }
}
