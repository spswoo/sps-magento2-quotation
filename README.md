# SPS Quotation for Magento 2 / Mage-OS

Quotations for stores that sell items whose shipping (or price) has to be quoted by staff — furniture, freight,
bulky equipment.

> **Status: in development.** All v1 features are implemented; tests, CI and documentation are being completed —
> see [Roadmap](#roadmap).

## Features

**Available**
- **Shipping Quote Required products** — a Yes/No product attribute. When such a product is in the cart, all
  shipping methods except an allowed list (e.g. store pickup) are hidden and the customer is told to contact you
  for a shipping quote (checkout, cart page and product page messages with `{products}`, `{phone}`, `{email}`
  placeholders). Works for storefront, REST/GraphQL and admin-created orders.

- "Request a shipping quote" form on quote-required product pages, and "Request a quote for my cart" on the cart
  page / Hyvä Checkout for carts with a quote-required product (guests and customers). Repeated requests from the
  same customer update their open request instead of creating duplicates.

**Planned (v1)**
- Admin quotations (Sales > Quotations): create from a request or from scratch, special item prices, product
  custom options, custom freight charge, estimated tax, expiry date.
- E-mail the quotation with a secure checkout link. The link opens the normal checkout with the quoted items,
  prices and freight; the quote is final (no coupons or cart rules), freight is locked to the quoted destination.
- Guests pay without creating an account; any installed payment method works.
- Hyvä theme and Hyvä Checkout support; core module is theme-agnostic (Luma templates welcome).

## Packages

| Composer package | Magento module | Needs |
|---|---|---|
| `sps/module-quotation` | `SPS_Quotation` | Magento Open Source 2.4.6+ or Mage-OS, PHP 8.1–8.4 |
| `sps/module-quotation-hyva` | `SPS_QuotationHyva` | Hyvä theme (`hyva-themes/magento2-theme-module`) |
| `sps/module-quotation-hyva-checkout` | `SPS_QuotationHyvaCheckout` | Hyvä Checkout |

Install only the packages that match your storefront.

## Installation

```bash
composer require sps/module-quotation            # + sps/module-quotation-hyva, sps/module-quotation-hyva-checkout
bin/magento module:enable SPS_Quotation          # + SPS_QuotationHyva SPS_QuotationHyvaCheckout
bin/magento setup:upgrade
# production mode: bin/magento setup:di:compile && bin/magento setup:static-content:deploy
# Hyvä: bin/magento hyva:config:generate, then rebuild your theme's CSS
```

From a local checkout (development), add a path repository to your project's `composer.json`:

```json
{"type": "path", "url": "/path/to/sps-magento2-quotation/module-*", "options": {"symlink": false}}
```

## Configuration

Stores > Configuration > Sales > Delivery Methods > **Shipping Quote Required (SPS Quotation)**: enable, shipping
methods still offered, messages. Flag products with **Shipping Quote Required = Yes** (product form, General).

## Uninstalling

The modules are designed so that disabling or removing them never breaks the store:
- they add no columns to core tables (own tables only), no theme overrides, no CMS directives;
- the product attribute uses Magento's own Yes/No models, so it is harmless while the module is disabled;
- their shipping carriers are defined in module config only — once disabled, Magento skips them;
- orders keep their totals: freight is stored as normal shipping, quoted prices as normal item prices.

```bash
bin/magento module:disable SPS_QuotationHyvaCheckout SPS_QuotationHyva SPS_Quotation && bin/magento setup:upgrade
# remove code + data (attribute, tables, settings) for composer installs:
bin/magento module:uninstall --remove-data SPS_QuotationHyvaCheckout SPS_QuotationHyva SPS_Quotation
```

## Roadmap

1. Quote-required products (done)
2. Quotation requests, admin quotations, e-mails (done)
3. Pay-by-link checkout, freight carrier, quote-is-final rules, Hyvä Checkout notices (done)
4. Tests and CI (install → disable → uninstall on a fresh Mage-OS), translations, documentation

## Contributing

Issues and pull requests are welcome. Code follows the Magento 2 coding standard (`composer cs`).

## License

[Open Software License 3.0 (OSL-3.0)](LICENSE.txt). Copyright © SPS.
