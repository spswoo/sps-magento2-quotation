# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- `SPS_Quotation`: "Quote required" products — a Yes/No product attribute hides all shipping methods except an
  allowed list (e.g. store pickup) when such a product is in the cart, and explains why at checkout
  (carrier `spsquote`). Ported from the store-specific `SPS_ShippingQuote` module.
- `SPS_QuotationHyva`: product-page and cart notices for Hyvä themes.
- `SPS_QuotationHyvaCheckout`: shipping-step notice for Hyvä Checkout (it hides shipping methods that return an error).
