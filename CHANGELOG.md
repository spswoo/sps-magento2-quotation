# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- `SPS_Quotation`: "Quote required" products — a Yes/No product attribute hides all shipping methods except an
  allowed list (e.g. store pickup) when such a product is in the cart, and explains why at checkout
  (carrier `spsquote`). Ported from the store-specific `SPS_ShippingQuote` module.
- `SPS_QuotationHyva`: product-page and cart notices for Hyvä themes.
- Quotations (`sps_quotation`, `sps_quotation_item` tables; repository and data interfaces in `Api/`).
- Storefront "Request a shipping quote" form on quote-required product pages (Hyvä dialog; selected custom
  options included; honeypot + per-IP hourly limit; staff and customer e-mails).
- Admin Sales > Quotations: grid and editor (customer or guest, destination, items with custom options and quoted
  unit prices, freight charge and label, expiry date, estimated tax from the store's tax rules, internal note);
  Save, Save & Send (checkout-link e-mail; the link opens with milestone 3), Cancel, Delete; ACL resources for
  manage / send / delete.
- Settings: Stores > Configuration > Sales > Quotations, incl. an "Only for developer IPs" mode for testing on a
  live store (separate full-page-cache variant for those visitors).
- `module:uninstall --remove-data` removes the tables, settings, customised e-mail templates and the attribute.
- `SPS_QuotationHyvaCheckout`: shipping-step notice for Hyvä Checkout (it hides shipping methods that return an error).
