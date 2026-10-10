# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed
- Admin editor: quoted unit prices are pre-filled with the item's catalog price (the price the customer saw, incl.
  special prices), so a freight-only quotation needs no price entry; an empty price saves as the catalog price.

### Added (cart quotes)
- "Request a quote for my cart" on the cart page and in the Hyvä Checkout shipping step when the cart holds a
  quote-required product: one request for everything in the cart (quantities, custom options and other product
  configuration from the cart line's buy request, plus the option labels the cart showed). The cart is not changed;
  in the checkout the dialog is pre-filled from the address already entered.
- One customer, one open request: a new request from the same cart, or from the same e-mail for an overlapping
  product, updates the open request (status "requested") instead of creating another one; staff get an
  "Updated shipping quote request" e-mail, the customer message history is kept. Requests staff are working on are
  never changed.
- Product page hint "You have N other items in your cart — request a quote for your whole cart instead" (from the
  visitor's cart section data, page-cache safe).
- `sps_quotation.source` (product / cart / admin) and `source_quote_id`; admin grid column and editor line
  "Requested from"; the editor shows cart option details read-only and keeps non-editable configuration on save.

### Fixed
- `SPS_QuotationHyvaCheckout`: the quotation notice in the shipping step is now a Magewire component that refreshes when
  the shipping address is saved, so the "outside the quoted destination" warning appears as soon as the postcode
  changes (Hyvä Checkout hides the unavailable freight rate without a message).

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
- Pay-by-link checkout: "Review and pay" link (`sps_quotation/checkout/open`) builds a cart with the quoted items,
  custom options and prices, destination pre-filled and the quoted freight selected (guest or logged-in customer;
  re-opening re-uses the cart); explanation page for invalid / expired / cancelled / ordered links.
- Freight carrier `spsquotation`: one rate (quoted label + amount) only for quotation carts and only for the quoted
  country + postal code; other carriers are not offered for quotation carts.
- "The quote is final": no cart price rules, free shipping or coupons on quotation carts; adding other products
  is refused; an order-placement guard (before payment) refuses carts that no longer match the quotation.
- Order placed → quotation "ordered" + linked; admin order view shows the quotation.
- Daily cron: expired quotations; optional expiry reminder with a fresh link (default 3 days before).
- State / province on the request form (required where the country requires one) → better tax estimate.
- `QuotationCartResolver::isQuotationCart()` public service for other modules.
- `SPS_QuotationHyvaCheckout`: shipping-step notice for Hyvä Checkout (it hides shipping methods that return an error).
