# Pincode Checker for WooCommerce

**Author:** Akhil Nichani ([@akinich](https://github.com/akinich))  
**GitHub Repository:** [akinich/Pincode-checker-for-woocommerce](https://github.com/akinich/Pincode-checker-for-woocommerce)  
**Version:** 1.3.7  
**Requires WordPress:** 6.7+  
**Requires WooCommerce:** 8.0+ (Tested up to WooCommerce 11+)  
**Requires PHP:** 8.1+  
**Elementor Compatible:** Yes (Elementor 3.0+)  
**Theme Compatibility:** Compatible with Freshio, Astra, WoodMart, Storefront & Standard WordPress Themes  
**Cart & Checkout Blocks Compatible:** Yes (Full StoreAPI RouteException & Order Hook integration)  
**License:** GPL-2.0-or-later  

---

## 🌟 Overview

**Pincode Checker for WooCommerce** is an enterprise-grade, strictly typed WooCommerce deliverability plugin. It delivers instant, real-time AJAX deliverability verification directly against **native WooCommerce Shipping Zones** — guaranteeing **one single source of truth** without maintaining duplicate pincode databases or spreadsheets.

### Key Highlights:
* 🎯 **Single Source of Truth:** Reads directly from WooCommerce Shipping Zones (`WC_Shipping_Zones`). Store managers only maintain delivery postcodes in standard WooCommerce Shipping settings.
* 📍 **Prominent 1-Click Browser Geolocation ("Use My Current Location"):** Features a dedicated, full-width primary action button with an "OR" divider positioned right above the manual pincode input. Features a clean mint background, GPS crosshair icon, and unmistakable button label: **"Use My Current Location"**.
* 🌐 **Cross-Platform GPS Coordinates & Rooftop Precision:** Automatically captures device latitude & longitude when geolocation is used. Persists coordinates across browser session, local storage, and binds them to the order upon checkout.
* 🗺️ **1-Click Google Maps Dispatch Link:** Automatically generates a direct navigation URL (`https://www.google.com/maps?q=lat,lng`) stored in order metadata (`_shipping_google_maps_url`) for driver navigation.
* 🚚 **WP-Admin Dispatch Dashboard:** Displays an unmistakable green delivery dispatch card inside **WooCommerce > Orders > Order Details** (below the Shipping Address) featuring the exact latitude/longitude and a 1-click **"Navigate via Google Maps"** button.
* 🔌 **REST API Architecture for Native Mobile Apps (iOS & Android):**
  * `POST /wp-json/pincode-checker/v1/check-deliverability` (and alias `/wp-json/circlefarms/v1/check-deliverability`): Accepts `{ pincode, latitude, longitude }` and returns real-time deliverability status, city, and Google Maps URL.
  * Extends standard `/wp-json/wc/v3/orders` via `register_rest_field` to read and write `shipping_coordinates`.
  * Extends standard `/wp-json/wc/v3/customers` to persist rooftop coordinates to customer address profiles.
* 🛡️ **Dual-Engine Checkout Binding:** Automatically binds GPS coordinates to orders on **both** classic shortcode checkout (`woocommerce_checkout_create_order`) and modern WooCommerce React Cart & Checkout Blocks (`woocommerce_store_api_checkout_update_order_from_request`).
* 📱 **Native Mobile Delivery Strip & Slide-Up Bottom Sheet:** Automatically displays a slim, elegant location strip (`📍 Delivering to 600031 · (Change)` or `Select your delivery pincode`) below the header on mobile/tablet devices (`<= 991px`). Tapping opens a modern slide-up bottom sheet with an iOS-style drag handle bar and a **semi-transparent dimmed backdrop overlay** (`rgba(0,0,0,0.45)`).
* ⚡ **Advanced Postcode Rule Matching:** Evaluates exact postcodes (`600028`), numeric ranges (`600001...600120` and `600001-600120`), comma-separated entries, and wildcards (`600*`).
* 🧩 **Native Elementor Widget:** Appears automatically in Elementor's widget panel as **"Pincode Deliverability Checker"** with drag-and-drop support, style controls (Popover, Inline Standard, Compact), and live preview.
* 🚀 **100% Plug-and-Play Auto-Placement Settings:** Configurable from **WooCommerce > Settings > Shipping > Pincode Checker**:
  * **Header Placement:** Replace 'Find a Store' (server-side interceptor) | Append to Primary Menu (`wp_nav_menu_items`) | Off (Elementor widget/shortcode only).
  * **Mobile Delivery Strip:** Enable slim location strip below header on mobile/tablet devices (Yes/No).
  * **1-Click Geolocation:** Enable/disable browser GPS location detection (Yes/No).
  * **Product Page Placement:** Above 'Add to Cart' | Below 'Add to Cart' | Off.
  * **Mini-Cart Placement:** Enable in slide-out cart drawer (Yes/No).
* 🎨 **Theme Interceptor Polish:** Built-in suppression for theme-prefixed Icon Box classes (`.elementor-widget-freshio2-icon-box` and `.elementor-icon-box-wrapper`), eliminating ghost/redundant pin icons in the header.
* 🔄 **Seamless Location Reset & Text Selection:** Clicking the header trigger or mobile strip automatically focuses and selects all text in the input box so typing immediately overwrites the existing pincode. A dedicated **"Reset / Clear Location"** action completely resets sessions, customer location, and cached coordinates across the cart and checkout.
* 🛒 **Real-Time Cart & Shipping Sync:** Automatically sets `WC()->customer` shipping/billing postcode upon verification, enabling AJAX slide-out carts to update shipping totals and free-delivery thresholds instantly.
* 📍 **Header Popover Dropdown UI:** Dedicated header trigger button (`Check Deliverability` or `Delivering to [Pincode] (Change)`) with an elegant, non-colliding popover card floating above hero sliders (`z-index: 9999999`).
* 🔒 **Double-Layer Checkout Protection & Digital Goods Exemption:** Protects **both** classic shortcode checkout and modern WooCommerce Cart & Checkout Blocks, with automatic bypass for virtual/downloadable products that do not require physical shipping.

---

## 📦 1-Click Plug-and-Play Installation

1. Download [pincode-checker-for-woocommerce-v1.3.7.zip](file:///Users/akhil/AI%20Coding/Pincode%20Checker%20plugin/pincode-checker-for-woocommerce-v1.3.7.zip).
2. Go to your WordPress Admin dashboard: **Plugins > Add New > Upload Plugin**.
3. Choose `pincode-checker-for-woocommerce-v1.3.7.zip` and click **Install Now**.
4. Click **Activate Plugin**.

---

## ⚙️ Configuration & Auto-Placement (No Code Required)

### 1. Configure Your Serviceable Shipping Zone
1. In your WordPress Admin, go to **WooCommerce > Settings > Shipping > Shipping Zones**.
2. Create or edit your local shipping zone.
3. Under **Zone regions**, add your serviceable postcodes (supports exact codes like `600028`, ranges like `600001...600120` or `600001-600120`, and wildcards like `600*`).
4. Add your preferred shipping methods (e.g., Flat Rate, Free Shipping).

### 2. Configure Pincode Checker Options
Navigate to **WooCommerce > Settings > Shipping > Pincode Checker**:
* **Target Shipping Zones:** Select the specific zone(s) you wish to evaluate, or leave blank to automatically check all active zones with postcode rules.
* **Header Placement:** Choose *Replace 'Find a Store'* to automatically intercept demo theme store locators, or *Append to Primary Menu*.
* **Mobile Delivery Strip:** Enable/disable the slim delivery strip below the header on mobile screens.
* **1-Click Geolocation:** Enable/disable the prominent GPS location button.
* **Product Page Placement:** Choose whether to automatically display the checker above or below the "Add to Cart" button.
* **Show in Slide-Out Mini-Cart:** Automatically embed the checker in slide-out cart drawers.
* **Enforce at Checkout:** Block orders to unserviceable areas during checkout.
* **Custom Messages:** Customize your success and error messages.

---

## 🧩 Elementor Widget & Shortcodes

### Elementor Widget:
* Search for **"Pincode Deliverability Checker"** in Elementor's widget panel.
* Drag and drop anywhere onto your page or header template.
* Choose between **Header Popover Dropdown**, **Standard Inline Form**, or **Compact Inline Form**.

### Shortcodes:
* Standard Inline Checker: `[pincode_checker]`
* Header Trigger & Popover: `[header_pincode_checker]`
* Optional Attributes: `layout="standard|compact"`, `placeholder="..."`, `button_text="..."`, `show_title="true|false"`, `title="..."`
