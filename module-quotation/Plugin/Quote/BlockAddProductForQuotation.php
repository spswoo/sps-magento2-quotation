<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Plugin\Quote;

use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;
use SPS\Quotation\Model\CartBuilder;
use SPS\Quotation\Model\QuotationCartResolver;

/**
 * A quotation cart holds exactly the quoted items: adding other products is refused (they belong in a normal
 * order). The order-placement guard additionally refuses carts whose items were changed.
 */
class BlockAddProductForQuotation
{
    public function __construct(
        private readonly QuotationCartResolver $resolver,
        private readonly CartBuilder $cartBuilder,
        private readonly QuotationRepositoryInterface $repository
    ) {
    }

    /**
     * @param Product $product
     * @param mixed $request
     * @param string|null $processMode
     * @throws LocalizedException
     */
    public function beforeAddProduct(Quote $subject, $product, $request = null, $processMode = null): void
    {
        if ($this->cartBuilder->isBuilding() || !$subject->getId()) {
            return;
        }
        $quotation = $this->resolver->getForCart($subject);
        if ($quotation && !$subject->getItemsCount()) {
            // the customer emptied the quotation cart: release it and let it become a normal cart
            $quotation->setData(QuotationInterface::QUOTE_ID, null);
            $this->repository->save($quotation);
            $this->resolver->remember((int) $subject->getId(), null);
            return;
        }
        if ($quotation) {
            throw new LocalizedException(__(
                'Your cart holds quotation %1 with fixed prices, so other products cannot be added. '
                . 'Please complete the quotation first, then place a separate order.',
                $quotation->getIncrementId()
            ));
        }
    }
}
