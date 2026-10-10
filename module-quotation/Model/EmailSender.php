<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use SPS\Quotation\Api\Data\QuotationInterface;

/**
 * Quotation e-mails (Stores > Configuration > Sales > Quotations > E-mails). Templates get:
 * quotation, store, customer_name, quotation_number, totals (formatted), expires (formatted date), checkout_url,
 * admin_url, and render the item table with {{layout handle="sps_quotation_email_items" quotation=$quotation}}.
 */
class EmailSender
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone,
        private readonly BackendUrl $backendUrl,
        private readonly LoggerInterface $logger
    ) {
    }

    /** New request from the storefront: notify staff and confirm to the customer. Never throws. */
    public function sendRequestNotifications(QuotationInterface $quotation): void
    {
        $storeId = (int) $quotation->getStoreId();
        $vars = $this->vars($quotation);
        foreach ($this->config->getStaffRecipients($storeId) as $to) {
            $this->send('request_staff_template', $storeId, $to, '', $vars, [], (string) $quotation->getCustomerEmail());
        }
        if ($quotation->getCustomerEmail()) {
            $this->send(
                'request_customer_template',
                $storeId,
                (string) $quotation->getCustomerEmail(),
                $quotation->getCustomerName(),
                $vars,
                []
            );
        }
    }

    /**
     * The priced quotation with its checkout link, to the customer (+ configured BCC).
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendQuotation(QuotationInterface $quotation, string $checkoutUrl): void
    {
        $storeId = (int) $quotation->getStoreId();
        $vars = $this->vars($quotation) + ['checkout_url' => $checkoutUrl];
        if (!$this->send(
            'quotation_template',
            $storeId,
            (string) $quotation->getCustomerEmail(),
            $quotation->getCustomerName(),
            $vars,
            $this->config->getCopyTo($storeId)
        )) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('The quotation e-mail could not be sent. Please check the mail settings and the log.')
            );
        }
    }

    /**
     * @param array<string, mixed> $vars
     * @param string[] $bcc
     */
    private function send(
        string $templateField,
        int $storeId,
        string $to,
        string $toName,
        array $vars,
        array $bcc,
        string $replyTo = ''
    ): bool {
        try {
            $builder = $this->transportBuilder
                ->setTemplateIdentifier($this->config->getTemplate($templateField, $storeId))
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($vars)
                ->setFromByScope($this->config->getEmailIdentity($storeId), $storeId)
                ->addTo($to, $toName);
            foreach ($bcc as $address) {
                $builder->addBcc($address);
            }
            if ($replyTo !== '') {
                $builder->setReplyTo($replyTo);
            }
            $builder->getTransport()->sendMessage();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('SPS_Quotation: e-mail "%s" to %s failed: %s', $templateField, $to, $e->getMessage()));
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function vars(QuotationInterface $quotation): array
    {
        /** @var Quotation $quotation */
        $store = $this->storeManager->getStore((int) $quotation->getStoreId());
        $currency = (string) ($quotation->getData(QuotationInterface::CURRENCY_CODE) ?: $store->getBaseCurrencyCode());
        $format = fn($amount): string => (string) $this->priceCurrency->format((float) $amount, false, 2, $store, $currency);
        $expires = $quotation->getExpiresAt();
        return [
            'quotation' => $quotation,
            'store' => $store,
            'quotation_number' => (string) $quotation->getIncrementId(),
            'customer_name' => $quotation->getCustomerName(),
            'customer_message' => (string) $quotation->getData(QuotationInterface::CUSTOMER_MESSAGE),
            'destination' => $this->destination($quotation),
            'subtotal' => $format($quotation->getData(QuotationInterface::SUBTOTAL)),
            'freight' => $quotation->getFreightAmount() === null ? '' : $format($quotation->getFreightAmount()),
            'freight_label' => (string) $quotation->getData(QuotationInterface::FREIGHT_LABEL),
            'tax' => $format($quotation->getData(QuotationInterface::TAX_AMOUNT)),
            'grand_total' => $format($quotation->getData(QuotationInterface::GRAND_TOTAL)),
            'expires' => $expires ? $this->timezone->formatDate($expires, \IntlDateFormatter::LONG) : '',
            'admin_url' => $this->backendUrl->getUrl('sps_quotation/quotation/edit', ['id' => $quotation->getId(), '_nosecret' => true]),
        ];
    }

    private function destination(Quotation $quotation): string
    {
        return implode(', ', array_filter([
            (string) $quotation->getData(QuotationInterface::DEST_CITY),
            (string) $quotation->getData(QuotationInterface::DEST_REGION),
            (string) $quotation->getData(QuotationInterface::DEST_POSTCODE),
            (string) $quotation->getData(QuotationInterface::DEST_COUNTRY_ID),
        ]));
    }
}
