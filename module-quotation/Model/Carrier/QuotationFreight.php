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
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;
use SPS\Quotation\Api\Data\QuotationInterface;
use SPS\Quotation\Model\Destination;
use SPS\Quotation\Model\Messages;
use SPS\Quotation\Model\QuotationCartResolver;

/**
 * The quoted freight: one rate (quotation freight label + amount) for carts built from a quotation, only for the
 * quoted country + postal code. Other destinations get an error explaining the price only applies to the quoted
 * one. Free-shipping flags are ignored on purpose — the quote is final.
 */
class QuotationFreight extends AbstractCarrier implements CarrierInterface
{
    public const CODE = 'spsquotation';
    public const METHOD = 'freight';

    /** @var string */
    protected $_code = self::CODE;

    /** @var bool */
    protected $_isFixed = true;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly QuotationCartResolver $resolver,
        private readonly Destination $destination,
        private readonly Messages $messages,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        $quotation = $this->quotationFor($request);
        if (!$quotation || !$this->resolver->isPayable($quotation)) {
            return false;
        }
        if (!$this->destination->matches($quotation, $request->getDestCountryId(), $request->getDestPostcode())) {
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle((string) $this->getConfigData('title'));
            $error->setErrorMessage($this->messages->destinationMismatch($quotation));
            return $error;
        }
        $amount = (float) $quotation->getFreightAmount();
        $method = $this->rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle((string) $this->getConfigData('title'));
        $method->setMethod(self::METHOD);
        $method->setMethodTitle((string) ($quotation->getData(QuotationInterface::FREIGHT_LABEL) ?: $this->getConfigData('name')));
        $method->setPrice($amount);
        $method->setCost($amount);
        $result = $this->rateResultFactory->create();
        $result->append($method);
        return $result;
    }

    public function getAllowedMethods(): array
    {
        return [self::METHOD => (string) $this->getConfigData('name')];
    }

    public function isTrackingAvailable(): bool
    {
        return false;
    }

    private function quotationFor(RateRequest $request): ?QuotationInterface
    {
        foreach ((array) $request->getAllItems() as $item) {
            $quote = $item->getQuote();
            if ($quote) {
                return $this->resolver->getForCart($quote);
            }
        }
        return null;
    }
}
