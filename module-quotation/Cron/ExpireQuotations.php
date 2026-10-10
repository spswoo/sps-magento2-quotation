<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use SPS\Quotation\Api\Data\QuotationInterface as Q;
use SPS\Quotation\Api\QuotationRepositoryInterface;
use SPS\Quotation\Model\Config;
use SPS\Quotation\Model\EmailSender;
use SPS\Quotation\Model\Quotation;
use SPS\Quotation\Model\QuotationSender;
use SPS\Quotation\Model\ResourceModel\Quotation\CollectionFactory;
use SPS\Quotation\Model\Token;
use SPS\Quotation\Model\Validity;

/**
 * Daily: sent/opened quotations past their expiry date become "expired"; quotations expiring in N days
 * (Quotations > General > Expiry Reminder) get one reminder e-mail with a fresh checkout link
 * (the previous link stops working).
 */
class ExpireQuotations
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly QuotationRepositoryInterface $repository,
        private readonly Validity $validity,
        private readonly Config $config,
        private readonly Token $token,
        private readonly QuotationSender $quotationSender,
        private readonly EmailSender $emailSender,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $open = $this->collectionFactory->create()
            ->addFieldToFilter(Q::STATUS, ['in' => [Q::STATUS_SENT, Q::STATUS_ACCEPTED]])
            ->addFieldToFilter(Q::EXPIRES_AT, ['notnull' => true]);
        foreach ($open as $row) {
            try {
                /** @var Quotation $quotation */
                $quotation = $this->repository->getById((int) $row->getId());
                if ($this->validity->isExpired($quotation)) {
                    $quotation->setStatus(Q::STATUS_EXPIRED);
                    $quotation->setData(Q::TOKEN_HASH, null);
                    $this->repository->save($quotation);
                    continue;
                }
                $this->remind($quotation);
            } catch (\Throwable $e) {
                $this->logger->error('SPS_Quotation: expiry cron failed for #' . $row->getId() . ': ' . $e->getMessage());
            }
        }
    }

    private function remind(Quotation $quotation): void
    {
        $days = $this->config->getReminderDays($quotation->getStoreId());
        if ($days <= 0 || $quotation->getData(Q::REMINDER_SENT_AT) || !$quotation->getCustomerEmail()) {
            return;
        }
        $remindOn = date('Y-m-d', strtotime((string) $quotation->getExpiresAt() . ' -' . $days . ' days'));
        if ($this->validity->today($quotation->getStoreId()) < $remindOn) {
            return;
        }
        [$plain, $hash] = $this->token->generate();
        $quotation->setData(Q::TOKEN_HASH, $hash);
        $quotation->setData(Q::REMINDER_SENT_AT, $this->dateTime->gmtDate());
        $this->repository->save($quotation);
        $this->emailSender->sendQuotation(
            $quotation,
            $this->quotationSender->checkoutUrl($quotation, $plain),
            'reminder_template'
        );
    }
}
