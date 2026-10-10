<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Psr\Log\LoggerInterface;
use SPS\Quotation\Model\QuoteRequired\Checker;

/**
 * Notice carrier: when the cart contains a "Shipping Quote Required" product it returns a rate ERROR carrying the
 * configured explanation (shown by Luma checkout, the REST shipping estimate and admin order creation). It never
 * offers a price.
 */
class QuoteRequired extends AbstractCarrier implements CarrierInterface
{
    public const CODE = 'spsquote';

    /** @var string */
    protected $_code = self::CODE;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly Checker $checker,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        $names = $this->checker->getFlaggedItemNames((array) $request->getAllItems(), $request->getStoreId());
        if (!$names) {
            return false;
        }
        $error = $this->_rateErrorFactory->create();
        $error->setCarrier($this->_code);
        $error->setCarrierTitle((string) $this->getConfigData('title'));
        $error->setErrorMessage($this->checker->getMessage('checkout_message', $names, $request->getStoreId()));
        return $error;
    }

    public function getAllowedMethods(): array
    {
        return [$this->_code => (string) $this->getConfigData('title')];
    }

    public function isTrackingAvailable(): bool
    {
        return false;
    }
}
