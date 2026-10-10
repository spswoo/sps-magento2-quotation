<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use SPS\Quotation\Model\QuotationCartResolver;
use SPS\Quotation\Model\QuoteRequired\Checker;

/**
 * Storefront messages for "Shipping Quote Required" products (product page, cart, checkout shipping step).
 * Theme-agnostic: returns escaped HTML; the store phone and e-mail become links with the CSS class the template
 * passes in.
 */
class QuoteRequiredNotice implements ArgumentInterface
{
    public function __construct(
        private readonly Checker $checker,
        private readonly CheckoutSession $checkoutSession,
        private readonly Escaper $escaper,
        private readonly QuotationCartResolver $resolver
    ) {
    }

    /** Escaped message for a product page, '' when the product ships normally. */
    public function getProductMessageHtml(?Product $product, string $linkClass = ''): string
    {
        if (!$product || !$this->checker->isEnabled() || !$this->checker->isProductFlagged($product)) {
            return '';
        }
        return $this->linkify($this->checker->getMessage('product_message'), $linkClass);
    }

    /** Escaped message for the cart page, '' when nothing in the cart needs a quote. */
    public function getCartMessageHtml(string $linkClass = ''): string
    {
        return $this->cartMessageHtml('cart_message', $linkClass);
    }

    /** Escaped message for the checkout shipping step (for checkouts that hide unavailable shipping rates). */
    public function getCheckoutMessageHtml(string $linkClass = ''): string
    {
        return $this->cartMessageHtml('checkout_message', $linkClass);
    }

    private function cartMessageHtml(string $field, string $linkClass): string
    {
        if (!$this->checker->isEnabled()) {
            return '';
        }
        $quote = $this->checkoutSession->getQuote();
        if ($this->resolver->isQuotationCart($quote)) {
            return '';                                   // already quoted: no "contact us for a quote" message
        }
        $names = $this->checker->getFlaggedItemNames($quote->getAllItems());
        return $names ? $this->linkify($this->checker->getMessage($field, $names), $linkClass) : '';
    }

    /** Escape the message, then make the store phone and e-mail clickable. */
    private function linkify(string $message, string $linkClass): string
    {
        $html = $this->escaper->escapeHtml($message);
        $class = $linkClass !== '' ? ' class="' . $this->escaper->escapeHtmlAttr($linkClass) . '"' : '';
        $phone = $this->checker->getPhone();
        $email = $this->checker->getEmail();
        if ($phone !== '') {
            $tel = (string) preg_replace('/[^0-9+]/', '', $phone);
            $html = str_replace(
                $this->escaper->escapeHtml($phone),
                '<a' . $class . ' href="tel:' . $this->escaper->escapeHtmlAttr($tel) . '">' . $this->escaper->escapeHtml($phone) . '</a>',
                $html
            );
        }
        if ($email !== '') {
            $html = str_replace(
                $this->escaper->escapeHtml($email),
                '<a' . $class . ' href="mailto:' . $this->escaper->escapeHtmlAttr($email) . '">' . $this->escaper->escapeHtml($email) . '</a>',
                $html
            );
        }
        return $html;
    }
}
