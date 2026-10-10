<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Block\Email;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Model\ItemOptions;
use SPS\Quotation\Model\QuotationItem;

/**
 * Item table in quotation e-mails: {{layout handle="sps_quotation_email_items" quotation=$quotation}}.
 */
class Items extends Template
{
    /** @var string */
    protected $_template = 'SPS_Quotation::email/items.phtml';

    public function __construct(
        Template\Context $context,
        private readonly ItemOptions $itemOptions,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array{name: string, sku: string, qty: float, options: array, price: string, row_total: string}>
     */
    public function getRows(): array
    {
        $quotation = $this->getData('quotation');
        if (!$quotation instanceof QuotationInterface) {
            return [];
        }
        $rows = [];
        foreach ($quotation->getItems() as $item) {
            /** @var QuotationItem $item */
            $priced = $item->getPrice() !== null;
            $rows[] = [
                'name' => (string) $item->getName(),
                'sku' => (string) $item->getSku(),
                'qty' => $item->getQty(),
                'options' => $this->itemOptions->getLabels(
                    $item->getProductId(),
                    $item->getBuyRequestOptions(),
                    $quotation->getStoreId()
                ),
                'price' => $priced ? $this->format($quotation, (float) $item->getPrice()) : '',
                'row_total' => $priced ? $this->format($quotation, $item->getRowTotal()) : '',
            ];
        }
        return $rows;
    }

    public function hasPrices(): bool
    {
        foreach ($this->getRows() as $row) {
            if ($row['price'] !== '') {
                return true;
            }
        }
        return false;
    }

    private function format(QuotationInterface $quotation, float $amount): string
    {
        return (string) $this->priceCurrency->format(
            $amount,
            false,
            2,
            $quotation->getStoreId(),
            $quotation->getData(QuotationInterface::CURRENCY_CODE) ?: null
        );
    }
}
