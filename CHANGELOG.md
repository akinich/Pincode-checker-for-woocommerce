# Changelog - Pincode Checker for WooCommerce

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.3.7] - 2026-11-24

### Hardened & Polished
- **Coordinate Range & Type Validation:** Added `is_valid_coordinate()` helper to strictly validate that latitude (-90 to 90) and longitude (-180 to 180) are genuine floating-point geo coordinates before saving to order meta, user meta, or session.
- **REST API Type Safety & Immediate Meta Persistence:** Refactored `register_rest_field` for orders and customers to safely handle `mixed` objects (preventing fatal `TypeError` under PHP 8.1 strict types when called with non-array or post objects) and explicitly invoked `$order->save_meta_data()` so coordinates persist to the database immediately.
- **Stale Coordinate Pollution Prevention:** Hardened frontend JS so manual pincode typing or pasting immediately removes any cached GPS coordinate attributes, guaranteeing that manually typed pincodes never inherit mismatched coordinates from previous geolocation sessions.
- **Admin Order View Object Guard:** Added defensive resolution in `render_admin_order_gps_dispatch()` so any order ID or legacy post object is safely resolved to a `WC_Order` instance.

---

## [1.3.6] - 2026-11-24

### Added & Extended (Cross-Platform Geolocation, REST APIs & Admin GPS Dispatch)
- **Automatic GPS Coordinates Capture & Order Binding:** Automatically captures rooftop latitude & longitude when 1-click geolocation is used, persists coordinates to WooCommerce session (`wc_pincode_customer_lat`, `wc_pincode_customer_lng`) and client `localStorage`, and binds them to the order upon checkout.
- **Dual Checkout Coverage:** Implemented hooks for both Classic Checkout (`woocommerce_checkout_create_order`) and modern WooCommerce React Cart & Checkout Blocks (`woocommerce_store_api_checkout_update_order_from_request`) to store `_shipping_latitude`, `_shipping_longitude`, and `_shipping_google_maps_url`.
- **Customer Profile Address Binding:** Persists verified GPS coordinates to registered customer user meta (`shipping_latitude`, `shipping_longitude`, `shipping_google_maps_url`) for seamless multi-order and multi-address reuse.
- **WP-Admin GPS Dispatch Card:** Injected a prominent green dispatch card inside **WooCommerce > Orders > Order Details** (below the Shipping Address) displaying exact coordinates and a 1-click **"Navigate via Google Maps"** navigation button for drivers and logistics staff.
- **Cross-Platform REST APIs for Mobile Apps (iOS & Android):**
  - Registered `POST /wp-json/pincode-checker/v1/check-deliverability` (with alias `/wp-json/circlefarms/v1/check-deliverability`) accepting `{ pincode, latitude, longitude }` and returning deliverability status, city, coordinates, and navigation URL.
  - Extended standard WooCommerce Orders API (`/wp-json/wc/v3/orders`) via `register_rest_field` to support reading and writing `shipping_coordinates`.
  - Extended standard WooCommerce Customers API (`/wp-json/wc/v3/customers`) to expose customer profile GPS coordinates.
- **Clean Invalidation:** Updated `wc_clear_pincode` to cleanly flush coordinates from session and `localStorage` when "Reset / Clear Location" is clicked.

---

## [1.3.5] - 2026-11-24

### Hardened & Improved
- **Virtual / Downloadable Products Exemption at Checkout:** Hardened checkout protection across both Classic Checkout (`woocommerce_after_checkout_validation`) and Cart & Checkout Blocks (`woocommerce_store_api_checkout_update_order_from_request`) to automatically skip delivery postcode checks when the cart/order contains only virtual or downloadable products that do not require physical shipping.
- **Safe HTML String Replacement Callbacks:** Refactored string replacements in `intercept_elementor_widget_content()` and `filter_page_buffer()` to use `preg_replace_callback()`, eliminating potential backreference expansion risks (`$0`, `$1`, `\\`) if replacement HTML includes currency symbols or formatting characters.
- **Session Cache Invalidation Hardening:** Strengthened `ajax_clear_pincode()` by explicitly setting `customer_pincode` to an empty string in addition to unsetting the session key, ensuring immediate reactive cache busting across persistent Redis/Memcached object cache backends.
- **Shipping Address Postcode Fallback:** Hardened classic checkout address resolution so `$target_postcode` reliably falls back to customer shipping postcode even if `ship_to_different_address` is not toggled in custom checkout layouts.

---

## [1.3.4] - 2026-11-24

### Changed & Rebranded (White-Label Approach B)
- **Complete Rebranding to "Pincode Checker for WooCommerce":** Renamed the plugin, Elementor widget, and all user-facing settings to standard generic names.
- **Complete Codebase Scrub:** Removed all proprietary brand prefixes, class names, constants, and options across the entire codebase.
  - PHP class renamed to `WC_Pincode_Checker`.
  - Constants renamed to `WC_PINCODE_CHECKER_*`.
  - Database options renamed to `wc_pincode_*` under WooCommerce > Settings > Shipping > `Pincode Checker`.
  - Shortcodes standardized to `[pincode_checker]` and `[header_pincode_checker]`.
  - CSS classes standardized to `.wc-pincode-checker-widget`, `.wc-header-popover`, `.wc-mobile-delivery-strip-wrapper`, etc.
  - JavaScript variables, storage keys, and AJAX actions standardized to `wc_check_pincode`, `wc_clear_pincode`, `wc_verified_pincode`.
- **Clean Generic Defaults:** Refactored default copy, placeholders, and notices to work universally on any WooCommerce store worldwide while preserving 100% of all feature capabilities.

---

## [1.3.3] - 2026-11-24

### Added & Improved (UX Redesign: Dedicated Top Location Button)
- **Prominent Geolocation Button (Option 1):** Upgraded 1-Click Geolocation from a small in-field icon to a full-width, dedicated primary action button (`.wc-geo-btn-prominent`) positioned directly above the manual pincode input. Features a soft mint background, GPS crosshair icon, and unmistakable label: **"Use My Current Location"**.
- **Visual "OR" Divider:** Added an elegant centered horizontal divider (`.wc-geo-divider`) separating 1-click geolocation from manual pincode entry.
- **Fixed Placeholder Clipping Bug:** Completely removed the cramped in-input crosshair icon from inside the input wrapper. The text placeholder `"Enter 6-digit pincode"` now has 100% horizontal breathing room and is never truncated.
- **Large Mobile Touch Target:** Increased the geolocation tap target to 42px in height, allowing mobile shoppers to tap with their thumb effortlessly without accidentally focusing the text input or opening the mobile keyboard.
- **Dynamic Button State & Spinner:** Displays `"Detecting your delivery location..."` directly on the button face during GPS lookup and reverse geocoding, cleanly resetting back to `"Use My Current Location"` upon completion or cancellation.

---

## [1.3.2] - 2026-11-24

### Hardened & Fixed
- **HTTPS Secure Context Guard:** Added `window.isSecureContext === false` check to prevent silent geolocation failures on non-SSL environments, immediately rendering an actionable notice explaining that browser geolocation requires HTTPS.
- **Deep BigDataCloud Locality Candidate Extraction:** Hardened reverse geocoding parsing to comprehensively traverse both `informative` and `administrative` locality hierarchy arrays if top-level `postcode` is absent or empty.
- **Snappy 5-Second Provider Timeout:** Reduced individual geocoding request timeouts from 8s to 5s, guaranteeing rapid failover from BigDataCloud to OpenStreetMap Nominatim without stalling the UI.

---

## [1.3.1] - 2026-11-24

### Added
- **1-Click Browser Geolocation ("Use Current Location"):** Added browser HTML5 Geolocation API with BigDataCloud (primary) and OpenStreetMap Nominatim (fallback).
- **Auto-Fill & Auto-Validation:** Upon detecting coordinates, automatically extracts the 6-digit Indian postal code, populates the input field, and submits the form immediately for instant validation.
- **Admin Settings Toggle:** Added **1-Click Geolocation** checkbox under WooCommerce > Settings > Shipping > Pincode Checker.

---

## [1.3.0] - 2026-11-24

### Added
- **Native Mobile Delivery Strip:** Automatically renders a slim, elegant location strip below the header on mobile and tablet screens (`<= 991px`).
- **Dimmed Backdrop Overlay:** Added a semi-transparent dimmed backdrop overlay (`rgba(0, 0, 0, 0.45)`, `backdrop-filter: blur(2px)`) that dims background contents when the mobile delivery modal opens.
- **Slide-Up Bottom Sheet Modal:** On mobile devices, clicking the strip triggers a smooth slide-up bottom sheet card styled with top rounded corners (`18px`) and iOS-style drag handle bar.
- **PHP 8.1 Strict Types Immunity on Hook Callbacks:** Defended hook and filter callbacks against `TypeError` crashes.
- **Guest Session Cookie Initialization:** Automatically issues a customer session cookie for first-time guest visitors without cart items.

---

## [1.2.3] - 2026-11-24

### Fixed
- **Theme Icon Box Ghost Pin Bug:** Resolved an issue where an extra "ghost" pin icon remained visible to the left of the deliverability trigger button when using the *Replace 'Find a Store'* auto-placement option in themes like Freshio.

---

## [1.2.0] - 2026-11-24

### Added
- **Header Popover Dropdown UI:** Dedicated header trigger button (`Check Deliverability` or `Delivering to [Pincode] (Change)`) with an elegant popover card.
- **Native Elementor Widget:** Appears automatically in Elementor's widget panel with drag-and-drop support.

---

## [1.1.0] - 2026-11-24

### Added
- **Double-Layer Checkout Protection:** Protects classic shortcode checkout and modern WooCommerce Cart & Checkout Blocks.
- **Range & Wildcard Matching:** Evaluates exact postcodes, numeric ranges (`600001...600120` and `600001-600120`), and wildcards (`600*`).
