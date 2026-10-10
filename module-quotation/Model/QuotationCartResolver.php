<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Api\Data\CartInterface;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Api\QuotationRepositoryInterface;

/**
 * Which quotation (if any) a cart was built from (sps_quotation.quote_id). Public service: other modules can ask
 * isQuotationCart() to exclude quotation carts from their own price/fee logic. Cached per request.
 */
class QuotationCartResolver
{
    /** @var array<int, QuotationInterface|false> */
    private array $cache = [];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly QuotationRepositoryInterface $repository
    ) {
    }

    /**
     * Quotation linked to the cart, in any status (callers check whether it can still be paid).
     *
     * @param CartInterface|int|null $cart
     */
    public function getForCart($cart): ?QuotationInterface
    {
        $quoteId = $cart instanceof CartInterface ? (int) $cart->getId() : (int) $cart;
        if (!$quoteId) {
            return null;
        }
        if (!array_key_exists($quoteId, $this->cache)) {
            $connection = $this->resource->getConnection();
            $id = (int) $connection->fetchOne(
                $connection->select()
                    ->from($this->resource->getTableName('sps_quotation'), ['entity_id'])
                    ->where('quote_id = ?', $quoteId)
                    ->order('entity_id DESC')
                    ->limit(1)
            );
            $this->cache[$quoteId] = $id ? $this->repository->getById($id) : false;
        }
        return $this->cache[$quoteId] ?: null;
    }

    /** @param CartInterface|int|null $cart */
    public function isQuotationCart($cart): bool
    {
        return $this->getForCart($cart) !== null;
    }

    /** Called when a cart is (re)linked, so the same request sees the new link. */
    public function remember(int $quoteId, ?QuotationInterface $quotation): void
    {
        $this->cache[$quoteId] = $quotation ?? false;
    }

    /** Statuses in which a quotation can still be checked out. */
    public function isPayable(QuotationInterface $quotation): bool
    {
        return in_array(
            $quotation->getStatus(),
            [QuotationInterface::STATUS_SENT, QuotationInterface::STATUS_ACCEPTED],
            true
        );
    }
}
