# Handover Notes: Pincode Checker for WooCommerce (v1.3.4)

**Plugin Version:** 1.3.4 (Complete White-Label Rebranding Release)  
**Package:** `pincode-checker-for-woocommerce-v1.3.4.zip`  
**Compatibility:** WordPress 6.7+, WooCommerce 8.0 – 11+, PHP 8.1+, Elementor 3.0+, Freshio 3.0+  
**Release Date:** 2026-11-24  

---

## 🌟 1. Release Objective: 100% White-Label Rebranding (Approach B)

In **Release v1.3.4**, the plugin has undergone a complete, white-label transformation:
* Renamed officially to **"Pincode Checker for WooCommerce"**.
* Every single trace of the former brand name has been scrubbed from:
  * Plugin titles, descriptions, and author credentials
  * PHP classes, methods, and constants
  * Database settings and options (`wp_options`)
  * AJAX actions, nonces, and session keys
  * Elementor widget names, keywords, and panels
  * CSS classes, animations, and variable names
  * JavaScript storage keys, event handlers, and triggers
  * All documentation, changelogs, and README files

---

## 📋 2. Complete Mapping of Rebranded Identifiers

| Component | Previous Identifier | New Standard Identifier (v1.3.4) |
|---|---|---|
| **Plugin Name** | Previous Branded Name | **Pincode Checker for WooCommerce** |
| **Plugin Folder** | `pincode-checker-legacy` | **`pincode-checker-for-woocommerce`** |
| **Main PHP File** | `pincode-checker-legacy.php` | **`pincode-checker-for-woocommerce.php`** |
| **PHP Class** | `Legacy_Pincode_Checker` | **`WC_Pincode_Checker`** |
| **Elementor Widget** | `Legacy_Elementor_Pincode_Widget` | **`WC_Pincode_Checker_Elementor_Widget`** |
| **Elementor Title** | Legacy Deliverability Checker | **Pincode Deliverability Checker** |
| **Settings Tab** | Shipping > `Legacy Pincode Validator` | Shipping > **`Pincode Checker`** |
| **Database Options** | `legacy_pincode_*` | **`wc_pincode_*`** |
| **Shortcodes** | `[legacy_pincode_checker]` | **`[pincode_checker]`** |
| **Header Shortcode** | `[legacy_header_pincode_checker]` | **`[header_pincode_checker]`** |
| **AJAX Check Action** | `legacy_check_pincode` | **`wc_check_pincode`** |
| **AJAX Clear Action** | `legacy_clear_pincode` | **`wc_clear_pincode`** |
| **Security Nonce** | `legacy_pincode_nonce` | **`wc_pincode_checker_nonce`** |
| **JS Global Object** | `window.legacy_pincode_params` | **`window.wc_pincode_params`** |
| **LocalStorage Key** | `legacy_verified_pincode` | **`wc_verified_pincode`** |
| **JS Custom Events** | `legacy_pincode_verified` | **`wc_pincode_verified`** |
| **CSS Main Class** | `.legacy-pincode-checker-widget` | **`.wc-pincode-checker-widget`** |
| **CSS Prefixes** | `.legacy-*` | **`.wc-*`** |
| **CSS Variables** | `--legacy-*` | **`--wc-pc-*`** |

---

## 🚀 3. Features Included in v1.3.4 (100% Parity with v1.3.3)

* **Single Source of Truth:** Validates directly against native WooCommerce Shipping Zones (`WC_Shipping_Zones`) with support for exact codes, ranges (`600001...600120` & `600001-600120`), and wildcards (`600*`).
* **Prominent 1-Click Geolocation (Option 1):** Unmissable full-width button labeled **"Use My Current Location"** with an "OR" divider. Tested and free of any placeholder text clipping.
* **Dual Reverse Geocoding Providers:** High-speed BigDataCloud client API (primary) with automatic failover to OpenStreetMap Nominatim.
* **HTTPS Secure Context Guard:** Prevents silent failures on non-SSL origins (`window.isSecureContext === false`).
* **Native Mobile Delivery Strip & Bottom Sheet:** Slim strip on mobile screens (`<= 991px`) with an iOS-style slide-up card and a **semi-transparent dimmed backdrop overlay** (`rgba(0, 0, 0, 0.45)`).
* **Double-Layer Checkout Gating:** Protects both classic checkout and modern WooCommerce React Cart & Checkout Blocks.
* **Auto-Placement Settings:** Configurable under **WooCommerce > Settings > Shipping > Pincode Checker** (Header replacement, Mobile strip, 1-Click GPS, Product page, Mini-cart).

---

## 📦 4. Installation & Setup Instructions for Website Implementation Team

Because this is a clean white-label release (without migration bridges):
1. **Deactivate and Delete** the old plugin in your WordPress Admin: **Plugins > Installed Plugins**.
2. Download [pincode-checker-for-woocommerce-v1.3.4.zip](file:///Users/akhil/AI%20Coding/Pincode%20Checker%20plugin/pincode-checker-for-woocommerce-v1.3.4.zip).
3. Go to **Plugins > Add New > Upload Plugin**, select `pincode-checker-for-woocommerce-v1.3.4.zip`, and click **Install Now**.
4. Click **Activate Plugin**.
5. Navigate to **WooCommerce > Settings > Shipping > Pincode Checker**:
   * Verify the settings (Target Shipping Zones, Header Placement, Mobile Strip, 1-Click GPS).
   * Click **Save Changes** (takes ~30 seconds).
6. Clear site caches (e.g. WP Rocket, LiteSpeed, Cloudflare) and verify the frontend.
