<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Store\Model\ScopeInterface;

/**
 * Settings: Stores > Configuration > Sales > Quotations.
 */
class Config
{
    public const MODE_OFF = 0;
    public const MODE_ON = 1;
    /** Storefront features only for the IPs in Advanced > Developer > Developer Client Restrictions (testing). */
    public const MODE_DEVELOPER_IPS = 2;

    private const XML = 'sps_quotation/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RemoteAddress $remoteAddress
    ) {
    }

    /** Storefront features (request form, checkout link) available for the current visitor? */
    public function isEnabled($store = null): bool
    {
        $mode = $this->getMode($store);
        return $mode === self::MODE_ON || ($mode === self::MODE_DEVELOPER_IPS && $this->isDeveloperIp($store));
    }

    public function getMode($store = null): int
    {
        return (int) $this->value('general/enabled', $store);
    }

    /** Visitor IP listed in Advanced > Developer > Developer Client Restrictions > Allowed IPs. */
    public function isDeveloperIp($store = null): bool
    {
        $allowed = array_filter(array_map('trim', explode(',', (string) $this->scopeConfig->getValue(
            'dev/restrict/allow_ips',
            ScopeInterface::SCOPE_STORE,
            $store
        ))));
        $ip = (string) $this->remoteAddress->getRemoteAddress();
        return $ip !== '' && in_array($ip, $allowed, true);
    }

    public function isRequestFormEnabled($store = null): bool
    {
        return $this->isEnabled($store) && $this->flag('request/enabled', $store);
    }

    public function getRequestIntro($store = null): string
    {
        return (string) $this->value('request/intro', $store);
    }

    public function getRequestSuccessMessage($store = null): string
    {
        return (string) $this->value('request/success_message', $store);
    }

    /** Maximum quotation requests per visitor IP and hour (0 = unlimited). */
    public function getRequestLimitPerHour($store = null): int
    {
        return max(0, (int) $this->value('request/limit_per_hour', $store));
    }

    public function getValidDays($store = null): int
    {
        return max(1, (int) $this->value('general/valid_days', $store));
    }

    public function getNumberPrefix(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML . 'general/number_prefix');
    }

    public function getDefaultFreightLabel($store = null): string
    {
        return (string) $this->value('general/freight_label', $store);
    }

    public function getEmailIdentity($store = null): string
    {
        return (string) ($this->value('email/identity', $store) ?: 'sales');
    }

    /**
     * Staff recipients for new requests; falls back to the sender identity's address.
     *
     * @return string[]
     */
    public function getStaffRecipients($store = null): array
    {
        $list = array_filter(array_map('trim', explode(',', (string) $this->value('email/staff_recipient', $store))));
        if (!$list) {
            $identity = $this->getEmailIdentity($store);
            $list = [(string) $this->scopeConfig->getValue(
                'trans_email/ident_' . $identity . '/email',
                ScopeInterface::SCOPE_STORE,
                $store
            )];
        }
        return array_values(array_filter($list));
    }

    /** @return string[] BCC addresses for e-mails to customers */
    public function getCopyTo($store = null): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->value('email/copy_to', $store)))));
    }

    public function getTemplate(string $field, $store = null): string
    {
        return (string) $this->value('email/' . $field, $store);
    }

    private function value(string $path, $store)
    {
        return $this->scopeConfig->getValue(self::XML . $path, ScopeInterface::SCOPE_STORE, $store);
    }

    private function flag(string $path, $store): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML . $path, ScopeInterface::SCOPE_STORE, $store);
    }
}
