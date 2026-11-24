# Handover Notes: Pincode Checker for WooCommerce (v1.3.6)

**Plugin Version:** 1.3.6 (Cross-Platform Geolocation, REST APIs & Admin GPS Dispatch Release)  
**Author:** Akhil Nichani ([@akinich](https://github.com/akinich))  
**GitHub Repository:** [akinich/Pincode-checker-for-woocommerce](https://github.com/akinich/Pincode-checker-for-woocommerce)  
**Package:** `pincode-checker-for-woocommerce-v1.3.6.zip`  
**Compatibility:** WordPress 6.7+, WooCommerce 8.0 – 11+, PHP 8.1+, Elementor 3.0+, Freshio 3.0+, WooCommerce Address Book  
**Release Date:** 2026-11-24  

---

## 🌟 1. Release Objective: Cross-Platform Geolocation & Dispatch Operations

In **Release v1.3.6**, the plugin has been upgraded according to the full cross-platform specification:
* **Automatic GPS Capture:** When a customer clicks *"Use My Current Location"*, their exact device rooftop coordinates (`latitude` and `longitude`) are captured and synchronized with their WooCommerce session and browser `localStorage`.
* **Dual-Engine Checkout Order Binding:** Both classic checkout and modern React checkout blocks store `_shipping_latitude`, `_shipping_longitude`, and `_shipping_google_maps_url` into the order metadata.
* **Customer Profile Persistence:** Coordinates are saved to user profile metadata (`shipping_latitude`, `shipping_longitude`) for seamless multi-address and repeat order retrieval.
* **1-Click Driver Dispatch Card:** A clean, branded card is rendered directly inside **WooCommerce > Orders > Order Details** (below the Shipping Address) with exact coordinates and a 1-click **"Navigate via Google Maps"** button for drivers and dispatchers.
* **Native Mobile App REST API Suite:** Standard REST API routes and extensions are exposed so future iOS (Swift) and Android (Kotlin/Flutter) mobile apps communicate with identical data schemas.

---

## 🔌 2. REST API Reference for Mobile Developers

### Endpoint 1: Deliverability & Coordinate Check
* **Route:** `POST /wp-json/pincode-checker/v1/check-deliverability` *(also aliased at `/wp-json/circlefarms/v1/check-deliverability`)*
* **Request:**
  ```json
  {
    "pincode": "600028",
    "latitude": "13.028421",
    "longitude": "80.245892"
  }
  ```
* **Response (Serviceable):**
  ```json
  {
    "success": true,
    "serviceable": true,
    "pincode": "600028",
    "message": "✅ Great news! We deliver to 600028.",
    "shipping_latitude": "13.028421",
    "shipping_longitude": "80.245892",
    "google_maps_url": "https://www.google.com/maps?q=13.028421%2C80.245892"
  }
  ```

### Endpoint 2: WooCommerce Orders Extension (`/wp-json/wc/v3/orders`)
* **Field:** `shipping_coordinates`
* **Read (`GET`):** Returns `{ "latitude": "13.028421", "longitude": "80.245892", "google_maps_url": "..." }`
* **Write (`POST`):** Accepts `{ "shipping_coordinates": { "latitude": "13.028421", "longitude": "80.245892" } }` from the mobile app during in-app checkout.

### Endpoint 3: Customer Profile Extension (`/wp-json/wc/v3/customers`)
* **Field:** `shipping_coordinates`
* Exposes default shipping rooftop coordinates to display saved address pins in native mobile apps.

---

## 📋 3. Database Schema Reference

| Scope | Key Name | Description |
|---|---|---|
| **Order Meta** | `_shipping_latitude` | Customer delivery latitude (HPOS & legacy compatible) |
| **Order Meta** | `_shipping_longitude` | Customer delivery longitude (HPOS & legacy compatible) |
| **Order Meta** | `_shipping_google_maps_url` | Direct Google Maps navigation link (`https://www.google.com/maps?q=lat,lng`) |
| **User Meta** | `shipping_latitude` | Saved primary shipping latitude in customer profile |
| **User Meta** | `shipping_longitude` | Saved primary shipping longitude in customer profile |
| **User Meta** | `shipping_google_maps_url` | Saved Google Maps navigation link |
| **Session** | `wc_pincode_customer_lat` | Active customer latitude in WooCommerce session |
| **Session** | `wc_pincode_customer_lng` | Active customer longitude in WooCommerce session |
| **Client Storage** | `wc_pincode_customer_lat` | Cached latitude in browser `localStorage` |
| **Client Storage** | `wc_pincode_customer_lng` | Cached longitude in browser `localStorage` |

---

## 📦 4. Verification & Testing Instructions

1. **Upload & Activate:** Upload `pincode-checker-for-woocommerce-v1.3.6.zip` in **Plugins > Add New > Upload Plugin**.
2. **Frontend Test:** Open any page, click *"Use My Current Location"*. Notice that your coordinates are captured, verified against your shipping zone, and stored in session.
3. **Checkout Test:** Complete a test order.
4. **Admin Verification:** Open the created order in **WooCommerce > Orders**. Right below the Shipping Address box, verify the **Exact Delivery GPS Coordinates** card and click **Navigate via Google Maps** to test driver navigation.
5. **REST API Test:** Test `POST /wp-json/pincode-checker/v1/check-deliverability` with Postman or curl to verify response.
