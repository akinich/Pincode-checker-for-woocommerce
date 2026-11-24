<?php
/**
 * Plugin Name: Pincode Checker for WooCommerce
 * Plugin URI:  https://github.com/akinich/Pincode-checker-for-woocommerce
 * Description: Dynamically validates customer pincodes against WooCommerce Shipping Zones (exact codes, ranges, and wildcards) with live session sync, cart recalculation, native Elementor widget, auto-placement settings, mobile delivery strip, prominent 1-click geolocation, checkout protection, cross-platform GPS binding, and REST APIs for native mobile apps.
 * Version:     1.3.7
 * Author:      Akhil Nichani
 * Author URI:  https://github.com/akinich
 * Text Domain: pincode-checker-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 11.0
 * License:     GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants.
define( 'WC_PINCODE_CHECKER_VERSION', '1.3.7' );
define( 'WC_PINCODE_CHECKER_PLUGIN_FILE', __FILE__ );
define( 'WC_PINCODE_CHECKER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_PINCODE_CHECKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS) and Blocks.
 */
add_action( 'before_woocommerce_init', static function (): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

/**
 * Main WC_Pincode_Checker Class.
 */
final class WC_Pincode_Checker {

	/**
	 * Single class instance.
	 *
	 * @var WC_Pincode_Checker|null
	 */
	private static ?WC_Pincode_Checker $instance = null;

	/**
	 * Track if mobile strip has already been rendered.
	 *
	 * @var bool
	 */
	private bool $mobile_strip_rendered = false;

	/**
	 * Returns the singleton instance.
	 *
	 * @return WC_Pincode_Checker
	 */
	public static function get_instance(): WC_Pincode_Checker {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers all necessary hooks.
	 */
	private function __construct() {
		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Shortcode registration.
		add_shortcode( 'pincode_checker', array( $this, 'render_shortcode' ) );
		add_shortcode( 'header_pincode_checker', array( $this, 'render_header_shortcode' ) );

		// Register Native Elementor Widget.
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
		add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_elementor_widget_legacy' ) );

		// Header Auto-Placement: Server-Side Elementor Interceptor & Buffer Fallback.
		add_filter( 'elementor/widget/render_content', array( $this, 'intercept_elementor_widget_content' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'setup_header_buffer' ), 1 );
		add_filter( 'wp_nav_menu_items', array( $this, 'append_to_nav_menu' ), 20, 2 );

		// Native Mobile Delivery Strip below header (wp_body_open with fallback to wp_footer).
		add_action( 'wp_body_open', array( $this, 'render_mobile_delivery_strip' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_mobile_delivery_strip_fallback' ), 20 );

		// Product Page Auto-Placement.
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'render_product_page_widget' ) );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_product_page_widget' ) );

		// AJAX Handlers.
		add_action( 'wp_ajax_wc_check_pincode', array( $this, 'ajax_check_pincode' ) );
		add_action( 'wp_ajax_nopriv_wc_check_pincode', array( $this, 'ajax_check_pincode' ) );

		add_action( 'wp_ajax_wc_clear_pincode', array( $this, 'ajax_clear_pincode' ) );
		add_action( 'wp_ajax_nopriv_wc_clear_pincode', array( $this, 'ajax_clear_pincode' ) );

		// Checkout field prefill from verified session.
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'prefill_checkout_postcode' ), 20, 2 );

		// Strict checkout validation: Classic Shortcode Checkout.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout_postcode' ), 20, 2 );

		// Strict checkout validation: Modern WooCommerce Cart & Checkout Blocks (StoreAPI).
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_blocks_checkout' ), 10, 2 );

		// Cross-Platform Geolocation: Order Binding Hooks (Classic & React Blocks).
		add_action( 'woocommerce_checkout_create_order', array( $this, 'bind_coordinates_to_classic_order' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'bind_coordinates_to_blocks_order' ), 20, 2 );

		// Cross-Platform Geolocation: WP-Admin Order View Dispatch Card.
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'render_admin_order_gps_dispatch' ), 20, 1 );

		// Cross-Platform Geolocation: REST API Endpoints & Field Registration for Mobile Apps (iOS & Android).
		add_action( 'rest_api_init', array( $this, 'register_rest_api' ) );

		// Mini-Cart / Slide-Out Cart integration.
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( $this, 'render_minicart_widget' ), 5 );

		// WooCommerce Admin Settings tab for easy configuration.
		add_filter( 'woocommerce_get_sections_shipping', array( $this, 'add_shipping_section' ) );
		add_filter( 'woocommerce_get_settings_shipping', array( $this, 'get_shipping_settings' ), 10, 2 );
	}

	/**
	 * Enqueue frontend CSS and JavaScript.
	 */
	public function enqueue_assets(): void {
		if ( is_admin() || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		wp_register_style(
			'wc-pincode-checker',
			WC_PINCODE_CHECKER_PLUGIN_URL . 'assets/css/pincode-checker.css',
			array(),
			WC_PINCODE_CHECKER_VERSION
		);

		wp_register_script(
			'wc-pincode-checker',
			WC_PINCODE_CHECKER_PLUGIN_URL . 'assets/js/pincode-checker.js',
			array( 'jquery' ),
			WC_PINCODE_CHECKER_VERSION,
			false
		);

		$current_pincode = $this->get_current_pincode();
		$customer_lat    = ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get( 'wc_pincode_customer_lat', '' ) : '';
		$customer_lng    = ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get( 'wc_pincode_customer_lng', '' ) : '';
		if ( empty( $customer_lat ) && is_user_logged_in() ) {
			$customer_lat = (string) get_user_meta( get_current_user_id(), 'shipping_latitude', true );
			$customer_lng = (string) get_user_meta( get_current_user_id(), 'shipping_longitude', true );
		}

		wp_localize_script(
			'wc-pincode-checker',
			'wc_pincode_params',
			array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'wc_pincode_checker_nonce' ),
				'current_pincode'    => $current_pincode,
				'customer_lat'       => $customer_lat,
				'customer_lng'       => $customer_lng,
				'enable_geolocation' => $this->is_geolocation_enabled(),
				'i18n'               => array(
					'invalid_pincode'       => __( 'Please enter a valid 6-digit numeric pincode.', 'pincode-checker-for-woocommerce' ),
					'checking'              => __( 'Checking delivery availability...', 'pincode-checker-for-woocommerce' ),
					'delivering_to'         => __( 'Delivering to', 'pincode-checker-for-woocommerce' ),
					'check_deliverability'  => __( 'Check Deliverability', 'pincode-checker-for-woocommerce' ),
					'select_pincode'        => __( 'Select your delivery pincode', 'pincode-checker-for-woocommerce' ),
					'change'                => __( 'Change', 'pincode-checker-for-woocommerce' ),
					'location_cleared'      => __( 'Location reset. Enter a new pincode.', 'pincode-checker-for-woocommerce' ),
					'server_error'          => __( 'Unable to verify pincode at this moment. Please try again.', 'pincode-checker-for-woocommerce' ),
					'detecting_location'    => __( 'Detecting your delivery location...', 'pincode-checker-for-woocommerce' ),
					'geo_not_supported'     => __( 'Geolocation is not supported by your browser.', 'pincode-checker-for-woocommerce' ),
					'geo_permission_denied' => __( 'Location access was denied. Please enter your pincode manually.', 'pincode-checker-for-woocommerce' ),
					'geo_unavailable'       => __( 'Unable to detect your location. Please enter your pincode manually.', 'pincode-checker-for-woocommerce' ),
					'geo_postcode_failed'   => __( 'Could not detect 6-digit postal code. Please enter manually.', 'pincode-checker-for-woocommerce' ),
					'geo_insecure'          => __( 'Geolocation requires a secure connection (HTTPS). Please enter your pincode manually.', 'pincode-checker-for-woocommerce' ),
				),
			)
		);

		wp_enqueue_style( 'wc-pincode-checker' );
		wp_enqueue_script( 'wc-pincode-checker' );
	}

	/**
	 * Register native Elementor widget (Elementor 3.5.0+).
	 *
	 * @param mixed $widgets_manager Elementor widgets manager.
	 */
	public function register_elementor_widget( mixed $widgets_manager ): void {
		require_once WC_PINCODE_CHECKER_PLUGIN_PATH . 'includes/class-pincode-checker-elementor-widget.php';
		if ( class_exists( 'WC_Pincode_Checker_Elementor_Widget' ) && is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( new \WC_Pincode_Checker_Elementor_Widget() );
		}
	}

	/**
	 * Legacy fallback for older Elementor versions (< 3.5.0).
	 *
	 * @param mixed $widgets_manager Elementor widgets manager.
	 */
	public function register_elementor_widget_legacy( mixed $widgets_manager ): void {
		if ( ! did_action( 'elementor/widgets/register' ) ) {
			require_once WC_PINCODE_CHECKER_PLUGIN_PATH . 'includes/class-pincode-checker-elementor-widget.php';
			if ( class_exists( 'WC_Pincode_Checker_Elementor_Widget' ) && is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register_widget_type' ) ) {
				$widgets_manager->register_widget_type( new \WC_Pincode_Checker_Elementor_Widget() );
			}
		}
	}

	/**
	 * Helper: Retrieve current customer pincode from session or customer object.
	 *
	 * @return string
	 */
	public function get_current_pincode(): string {
		$current_pincode = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$current_pincode = (string) WC()->session->get( 'customer_pincode', '' );
		}
		if ( empty( $current_pincode ) && function_exists( 'WC' ) && WC()->customer ) {
			$current_pincode = (string) WC()->customer->get_shipping_postcode();
		}
		return trim( $current_pincode );
	}

	/**
	 * Helper: Check if 1-click geolocation feature is enabled in admin settings.
	 *
	 * @return bool
	 */
	public function is_geolocation_enabled(): bool {
		return 'yes' === get_option( 'wc_pincode_enable_geolocation', 'yes' );
	}

	/**
	 * Get prominent 1-click geolocation button and OR divider markup.
	 *
	 * @param bool $is_hidden Whether to render with initial inline hidden style.
	 * @return string HTML markup.
	 */
	public function get_geolocation_button_html( bool $is_hidden = false ): string {
		if ( ! $this->is_geolocation_enabled() ) {
			return '';
		}

		$style_attr = $is_hidden ? ' style="display: none;"' : '';

		ob_start();
		?>
		<button type="button" class="wc-detect-location-btn wc-geo-btn-prominent"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php esc_attr_e( 'Use My Current Location', 'pincode-checker-for-woocommerce' ); ?>">
			<svg class="wc-geo-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<circle cx="12" cy="12" r="3"></circle>
				<path d="M12 2v3m0 14v3M2 12h3m14 0h3"></path>
			</svg>
			<span class="wc-geo-spinner" aria-hidden="true"></span>
			<span class="wc-geo-btn-text"><?php esc_html_e( 'Use My Current Location', 'pincode-checker-for-woocommerce' ); ?></span>
		</button>
		<div class="wc-geo-divider"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-hidden="true">
			<span><?php esc_html_e( 'OR', 'pincode-checker-for-woocommerce' ); ?></span>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Intercept Elementor Widget Content server-side:
	 * Replaces any widget outputting "Find a Store" when the header auto-placement setting is enabled.
	 *
	 * @param mixed $content Widget content.
	 * @param mixed $widget  Widget instance.
	 * @return string
	 */
	public function intercept_elementor_widget_content( mixed $content, mixed $widget ): string {
		if ( ! is_string( $content ) || is_admin() || empty( $content ) ) {
			return is_string( $content ) ? $content : '';
		}

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $content;
		}

		$header_placement = get_option( 'wc_pincode_header_placement', 'replace_find_a_store' );
		if ( 'replace_find_a_store' !== $header_placement ) {
			return $content;
		}

		if ( preg_match( '/\bfind\s+a\s+store\b/i', $content ) ) {
			$header_checker = $this->get_header_delivery_checker_html();

			$anchor_pattern = '/<a\b[^>]*>(?:(?!<\/a>).)*?\bfind\s+a\s+store\b(?:(?!<\/a>).)*?<\/a>/is';
			if ( preg_match( $anchor_pattern, $content ) ) {
				$content = preg_replace_callback( $anchor_pattern, static function () use ( $header_checker ): string {
					return $header_checker;
				}, $content, 1 );
			} else {
				$text_pattern = '/>([^<]*?)\bfind\s+a\s+store\b([^<]*?)</i';
				if ( preg_match( $text_pattern, $content ) ) {
					$content = preg_replace_callback( $text_pattern, static function () use ( $header_checker ): string {
						return '>' . $header_checker . '<';
					}, $content, 1 );
				}
			}
		}

		return $content;
	}

	/**
	 * Setup output buffer on template_redirect as fallback for "Find a Store".
	 */
	public function setup_header_buffer(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		$header_placement = get_option( 'wc_pincode_header_placement', 'replace_find_a_store' );
		if ( 'replace_find_a_store' !== $header_placement ) {
			return;
		}

		ob_start( array( $this, 'filter_page_buffer' ) );
	}

	/**
	 * Buffer callback: Replaces "Find a Store" in page HTML if not already replaced.
	 *
	 * @param mixed $buffer HTML output.
	 * @return string
	 */
	public function filter_page_buffer( mixed $buffer ): string {
		if ( ! is_string( $buffer ) ) {
			return (string) $buffer;
		}

		if ( false !== stripos( $buffer, 'Find a Store' ) && false === strpos( $buffer, 'wc-header-delivery-checker' ) ) {
			$header_checker = $this->get_header_delivery_checker_html();
			$anchor_pattern = '/<a\b[^>]*>(?:(?!<\/a>).)*?\bfind\s+a\s+store\b(?:(?!<\/a>).)*?<\/a>/is';

			if ( preg_match( $anchor_pattern, $buffer ) ) {
				$buffer = preg_replace_callback( $anchor_pattern, static function () use ( $header_checker ): string {
					return $header_checker;
				}, $buffer, 1 );
			} else {
				$text_pattern = '/>([^<]*?)\bfind\s+a\s+store\b([^<]*?)</i';
				if ( preg_match( $text_pattern, $buffer ) ) {
					$buffer = preg_replace_callback( $text_pattern, static function () use ( $header_checker ): string {
						return '>' . $header_checker . '<';
					}, $buffer, 1 );
				}
			}
		}
		return $buffer;
	}

	/**
	 * Auto-Placement: Append Deliverability Checker to Primary / Main Navigation Menu.
	 *
	 * @param mixed $items The HTML list content for the menu items.
	 * @param mixed $args  An object containing wp_nav_menu() arguments.
	 * @return string
	 */
	public function append_to_nav_menu( mixed $items, mixed $args ): string {
		if ( ! is_string( $items ) ) {
			return (string) $items;
		}

		$header_placement = get_option( 'wc_pincode_header_placement', 'replace_find_a_store' );
		if ( 'append_primary_menu' !== $header_placement ) {
			return $items;
		}

		$location = is_object( $args ) ? ( $args->theme_location ?? '' ) : '';
		$is_primary_menu = empty( $location ) || in_array( $location, array( 'primary', 'main', 'main-menu', 'main_menu', 'header_menu', 'header-menu', 'topbar' ), true );

		if ( $is_primary_menu ) {
			$items .= '<li class="menu-item menu-item-wc-deliverability" style="display:inline-flex; align-items:center; list-style:none;">' . $this->get_header_delivery_checker_html() . '</li>';
		}

		return $items;
	}

	/**
	 * Render Native Mobile Delivery Strip (Below Header on Mobile & Tablet <= 991px).
	 *
	 * @param mixed $is_fallback Whether this is rendered as a footer fallback.
	 */
	public function render_mobile_delivery_strip( mixed $is_fallback = false ): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$enabled = 'yes' === get_option( 'wc_pincode_mobile_strip_enabled', 'yes' );
		if ( ! $enabled ) {
			return;
		}

		$this->mobile_strip_rendered = true;

		$current_pincode = $this->get_current_pincode();
		$is_verified     = ! empty( $current_pincode ) && $this->is_pincode_serviceable( $current_pincode );
		$fallback_class  = ( true === $is_fallback ) ? ' wc-fallback-strip' : '';
		?>
		<div class="wc-mobile-delivery-strip-wrapper wc-pincode-checker-widget<?php echo esc_attr( $fallback_class ); ?><?php echo $is_verified ? ' has-verified-pincode' : ''; ?>" data-current-pincode="<?php echo esc_attr( $current_pincode ); ?>">
			<!-- Mobile Slim Strip Bar -->
			<div class="wc-mobile-delivery-strip wc-header-pincode-trigger" role="button" tabindex="0" aria-haspopup="dialog" aria-expanded="false" aria-label="<?php esc_attr_e( 'Select delivery location', 'pincode-checker-for-woocommerce' ); ?>">
				<span class="wc-mobile-strip-icon" aria-hidden="true">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
						<circle cx="12" cy="10" r="3"></circle>
					</svg>
				</span>
				<span class="wc-mobile-strip-text wc-trigger-text">
					<?php if ( $is_verified ) : ?>
						<?php esc_html_e( 'Delivering to', 'pincode-checker-for-woocommerce' ); ?>
						<strong class="wc-verified-code"><?php echo esc_html( $current_pincode ); ?></strong>
						<span class="wc-trigger-change">(<?php esc_html_e( 'Change', 'pincode-checker-for-woocommerce' ); ?>)</span>
					<?php else : ?>
						<span class="wc-default-trigger-label"><?php esc_html_e( 'Select your delivery pincode', 'pincode-checker-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</span>
			</div>

			<!-- Dimmed Backdrop Overlay (Closes sheet on tap) -->
			<div class="wc-mobile-backdrop" style="display: none;" aria-hidden="true"></div>

			<!-- Slide-Up Bottom Sheet Card -->
			<div class="wc-header-popover" role="dialog" aria-modal="true" style="display: none;">
				<div class="wc-popover-drag-handle" aria-hidden="true"></div>
				<div class="wc-popover-header">
					<span class="wc-popover-title">
						<span class="wc-popover-icon" aria-hidden="true">📍</span>
						<?php esc_html_e( 'Check Delivery Availability', 'pincode-checker-for-woocommerce' ); ?>
					</span>
					<button type="button" class="wc-popover-close-btn" aria-label="<?php esc_attr_e( 'Close', 'pincode-checker-for-woocommerce' ); ?>">&times;</button>
				</div>
				<p class="wc-popover-subtitle">
					<?php esc_html_e( 'Enter your pincode to check delivery availability & delivery slots.', 'pincode-checker-for-woocommerce' ); ?>
				</p>
				<?php echo $this->get_geolocation_button_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<form class="wc-pincode-form wc-header-popover-form" novalidate>
					<div class="wc-input-wrapper">
						<span class="wc-input-icon" aria-hidden="true">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
								<circle cx="12" cy="10" r="3"></circle>
							</svg>
						</span>
						<input 
							type="tel" 
							name="pincode" 
							class="wc-pincode-input" 
							placeholder="<?php esc_attr_e( 'Enter 6-digit pincode', 'pincode-checker-for-woocommerce' ); ?>" 
							value="<?php echo esc_attr( $current_pincode ); ?>"
							maxlength="6" 
							inputmode="numeric" 
							pattern="[0-9]{6}" 
							autocomplete="postal-code"
							required 
						/>
						<button type="button" class="wc-input-clear-btn" aria-label="<?php esc_attr_e( 'Clear input', 'pincode-checker-for-woocommerce' ); ?>" style="<?php echo ! empty( $current_pincode ) ? 'display: flex;' : 'display: none;'; ?>">
							&times;
						</button>
					</div>
					<button type="submit" class="wc-submit-btn">
						<span class="wc-btn-text"><?php esc_html_e( 'Check', 'pincode-checker-for-woocommerce' ); ?></span>
						<span class="wc-btn-spinner" aria-hidden="true"></span>
					</button>
				</form>
				<div class="wc-result-box" role="status" aria-live="polite" style="display: none;"></div>

				<!-- One-Click Reset / Clear Saved Location Footer -->
				<div class="wc-popover-footer">
					<button type="button" class="wc-reset-pincode-btn" aria-label="<?php esc_attr_e( 'Reset saved pincode location', 'pincode-checker-for-woocommerce' ); ?>">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<polyline points="1 4 1 10 7 10"></polyline>
							<path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
						</svg>
						<span><?php esc_html_e( 'Reset / Clear Location', 'pincode-checker-for-woocommerce' ); ?></span>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Fallback render for mobile delivery strip if theme lacks wp_body_open hook.
	 */
	public function render_mobile_delivery_strip_fallback(): void {
		if ( $this->mobile_strip_rendered || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$this->render_mobile_delivery_strip( true );
	}

	/**
	 * Auto-Placement: Render widget on Product Single Page.
	 */
	public function render_product_page_widget(): void {
		$placement = get_option( 'wc_pincode_product_placement', 'none' );
		if ( 'none' === $placement ) {
			return;
		}

		$current_hook = current_action();
		if ( ( 'before_add_to_cart' === $placement && 'woocommerce_before_add_to_cart_form' === $current_hook ) ||
			( 'after_add_to_cart' === $placement && 'woocommerce_after_add_to_cart_form' === $current_hook ) ) {
			echo '<div class="wc-product-pincode-wrap" style="margin: 16px 0 20px 0;">';
			echo $this->render_shortcode(
				array(
					'layout'      => 'compact',
					'placeholder' => __( 'Enter 6-digit pincode', 'pincode-checker-for-woocommerce' ),
					'button_text' => __( 'Check', 'pincode-checker-for-woocommerce' ),
					'show_title'  => 'true',
					'title'       => __( 'Check Delivery Availability', 'pincode-checker-for-woocommerce' ),
				)
			);
			echo '</div>';
		}
	}

	/**
	 * Render Dedicated Header Delivery Checker with Popover Markup.
	 *
	 * @param string $current_pincode Optional pincode override.
	 * @return string HTML output.
	 */
	public function get_header_delivery_checker_html( string $current_pincode = '' ): string {
		if ( empty( $current_pincode ) ) {
			$current_pincode = $this->get_current_pincode();
		}

		$is_verified = ! empty( $current_pincode ) && $this->is_pincode_serviceable( $current_pincode );

		ob_start();
		?>
		<div class="wc-header-delivery-checker wc-pincode-checker-widget<?php echo $is_verified ? ' has-verified-pincode' : ''; ?>" data-current-pincode="<?php echo esc_attr( $current_pincode ); ?>">
			<!-- Header Pill Button Trigger -->
			<button type="button" class="wc-header-pincode-trigger" aria-expanded="false" aria-haspopup="dialog" aria-label="<?php esc_attr_e( 'Check Delivery Availability', 'pincode-checker-for-woocommerce' ); ?>">
				<span class="wc-trigger-icon" aria-hidden="true">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
						<circle cx="12" cy="10" r="3"></circle>
					</svg>
				</span>
				<span class="wc-trigger-text">
					<?php if ( $is_verified ) : ?>
						<?php esc_html_e( 'Delivering to', 'pincode-checker-for-woocommerce' ); ?>
						<strong class="wc-verified-code"><?php echo esc_html( $current_pincode ); ?></strong>
						<span class="wc-trigger-change">(<?php esc_html_e( 'Change', 'pincode-checker-for-woocommerce' ); ?>)</span>
					<?php else : ?>
						<span class="wc-default-trigger-label"><?php esc_html_e( 'Check Deliverability', 'pincode-checker-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</span>
			</button>

			<!-- Dropdown Popover Card -->
			<div class="wc-header-popover" role="dialog" aria-modal="false" style="display: none;">
				<div class="wc-popover-arrow" aria-hidden="true"></div>
				<div class="wc-popover-header">
					<span class="wc-popover-title">
						<span class="wc-popover-icon" aria-hidden="true">📍</span>
						<?php esc_html_e( 'Check Delivery Availability', 'pincode-checker-for-woocommerce' ); ?>
					</span>
					<button type="button" class="wc-popover-close-btn" aria-label="<?php esc_attr_e( 'Close', 'pincode-checker-for-woocommerce' ); ?>">&times;</button>
				</div>
				<p class="wc-popover-subtitle">
					<?php esc_html_e( 'Enter your pincode to check delivery availability & delivery slots.', 'pincode-checker-for-woocommerce' ); ?>
				</p>
				<?php echo $this->get_geolocation_button_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<form class="wc-pincode-form wc-header-popover-form" novalidate>
					<div class="wc-input-wrapper">
						<span class="wc-input-icon" aria-hidden="true">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
								<circle cx="12" cy="10" r="3"></circle>
							</svg>
						</span>
						<input 
							type="tel" 
							name="pincode" 
							class="wc-pincode-input" 
							placeholder="<?php esc_attr_e( 'Enter 6-digit pincode', 'pincode-checker-for-woocommerce' ); ?>" 
							value="<?php echo esc_attr( $current_pincode ); ?>"
							maxlength="6" 
							inputmode="numeric" 
							pattern="[0-9]{6}" 
							autocomplete="postal-code"
							required 
						/>
						<button type="button" class="wc-input-clear-btn" aria-label="<?php esc_attr_e( 'Clear input', 'pincode-checker-for-woocommerce' ); ?>" style="<?php echo ! empty( $current_pincode ) ? 'display: flex;' : 'display: none;'; ?>">
							&times;
						</button>
					</div>
					<button type="submit" class="wc-submit-btn">
						<span class="wc-btn-text"><?php esc_html_e( 'Check', 'pincode-checker-for-woocommerce' ); ?></span>
						<span class="wc-btn-spinner" aria-hidden="true"></span>
					</button>
				</form>
				<div class="wc-result-box" role="status" aria-live="polite" style="display: none;"></div>

				<!-- One-Click Reset / Clear Saved Location Footer -->
				<div class="wc-popover-footer">
					<button type="button" class="wc-reset-pincode-btn" aria-label="<?php esc_attr_e( 'Reset saved pincode location', 'pincode-checker-for-woocommerce' ); ?>">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<polyline points="1 4 1 10 7 10"></polyline>
							<path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
						</svg>
						<span><?php esc_html_e( 'Reset / Clear Location', 'pincode-checker-for-woocommerce' ); ?></span>
					</button>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode for Header Popover: [header_pincode_checker]
	 *
	 * @return string
	 */
	public function render_header_shortcode(): string {
		return $this->get_header_delivery_checker_html();
	}

	/**
	 * Retrieve target WooCommerce shipping zone IDs to validate against.
	 *
	 * @return int[] Array of zone IDs.
	 */
	public function get_serviceable_zone_ids(): array {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}

		$saved_zone_ids = get_option( 'wc_pincode_target_zones', array() );
		if ( ! empty( $saved_zone_ids ) && is_array( $saved_zone_ids ) ) {
			$zone_ids = array_map( 'intval', $saved_zone_ids );
			return apply_filters( 'wc_pincode_serviceable_zone_ids', $zone_ids );
		}

		$detected_ids = array();
		$zones        = \WC_Shipping_Zones::get_zones();

		// Check any active zone with postcode criteria.
		foreach ( $zones as $zone_data ) {
			$locations = is_array( $zone_data ) ? ( $zone_data['zone_locations'] ?? array() ) : ( $zone_data->zone_locations ?? array() );
			$zone_id   = is_array( $zone_data ) ? (int) ( $zone_data['zone_id'] ?? 0 ) : (int) ( $zone_data->zone_id ?? 0 );

			if ( is_array( $locations ) ) {
				foreach ( $locations as $loc ) {
					$type = is_object( $loc ) ? ( $loc->type ?? '' ) : ( is_array( $loc ) ? ( $loc['type'] ?? '' ) : '' );
					if ( 'postcode' === $type && $zone_id > 0 ) {
						$detected_ids[] = $zone_id;
						break;
					}
				}
			}
		}

		// Fallback: If no postcode-specific zone is found, check zone names.
		if ( empty( $detected_ids ) ) {
			foreach ( $zones as $zone_data ) {
				$zone_id = is_array( $zone_data ) ? (int) ( $zone_data['zone_id'] ?? 0 ) : (int) ( $zone_data->zone_id ?? 0 );
				if ( $zone_id > 0 ) {
					$detected_ids[] = $zone_id;
				}
			}
		}

		return apply_filters( 'wc_pincode_serviceable_zone_ids', array_values( array_unique( $detected_ids ) ) );
	}

	/**
	 * Check if a 6-digit pincode matches any serviceable WooCommerce shipping zone rules.
	 *
	 * @param string $pincode 6-digit numeric string.
	 * @return bool True if serviceable.
	 */
	public function is_pincode_serviceable( string $pincode ): bool {
		$pincode = trim( $pincode );

		if ( ! preg_match( '/^[0-9]{6}$/', $pincode ) ) {
			return false;
		}

		$zone_ids = $this->get_serviceable_zone_ids();
		if ( empty( $zone_ids ) ) {
			return false;
		}

		foreach ( $zone_ids as $zone_id ) {
			$zone      = new \WC_Shipping_Zone( $zone_id );
			$locations = $zone->get_zone_locations();

			if ( ! is_array( $locations ) ) {
				continue;
			}

			foreach ( $locations as $location ) {
				$type = is_object( $location ) ? ( $location->type ?? '' ) : ( is_array( $location ) ? ( $location['type'] ?? '' ) : '' );
				if ( 'postcode' !== $type ) {
					continue;
				}

				$code = is_object( $location ) ? ( $location->code ?? '' ) : ( is_array( $location ) ? ( $location['code'] ?? '' ) : '' );
				if ( $this->match_postcode_rule( $pincode, (string) $code ) ) {
					return apply_filters( 'wc_pincode_is_serviceable', true, $pincode, $zone );
				}
			}
		}

		return apply_filters( 'wc_pincode_is_serviceable', false, $pincode, null );
	}

	/**
	 * Match a single postcode against a WooCommerce postcode rule.
	 *
	 * @param string $pincode Customer pincode (e.g. 600028).
	 * @param string $raw_rule Zone postcode rule(s).
	 * @return bool
	 */
	private function match_postcode_rule( string $pincode, string $raw_rule ): bool {
		$pincode = strtoupper( trim( $pincode ) );
		if ( '' === $raw_rule || '' === $pincode ) {
			return false;
		}

		$rules = preg_split( '/[\r\n,]+/', $raw_rule );
		if ( false === $rules ) {
			$rules = array( $raw_rule );
		}

		foreach ( $rules as $rule ) {
			$rule = strtoupper( trim( (string) $rule ) );
			if ( '' === $rule ) {
				continue;
			}

			if ( $pincode === $rule ) {
				return true;
			}

			if ( str_contains( $rule, '...' ) ) {
				$parts = explode( '...', $rule );
			} elseif ( str_contains( $rule, '-' ) && ! str_starts_with( $rule, '-' ) ) {
				$parts = explode( '-', $rule );
			} else {
				$parts = array();
			}

			if ( 2 === count( $parts ) ) {
				$min = trim( $parts[0] );
				$max = trim( $parts[1] );
				if ( is_numeric( $pincode ) && is_numeric( $min ) && is_numeric( $max ) ) {
					$pin_int = (int) $pincode;
					$min_int = (int) $min;
					$max_int = (int) $max;
					if ( $pin_int >= $min_int && $pin_int <= $max_int ) {
						return true;
					}
				}
			}

			if ( str_contains( $rule, '*' ) ) {
				$pattern = '/^' . str_replace( '\*', '.*', preg_quote( $rule, '/' ) ) . '$/i';
				if ( preg_match( $pattern, $pincode ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * AJAX Handler: Validate customer pincode.
	 */
	public function ajax_check_pincode(): void {
		check_ajax_referer( 'wc_pincode_checker_nonce', 'nonce' );

		$raw_pincode = sanitize_text_field( wp_unslash( $_POST['pincode'] ?? '' ) );
		$pincode     = preg_replace( '/\D/', '', $raw_pincode );

		if ( 6 !== strlen( $pincode ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid 6-digit numeric pincode.', 'pincode-checker-for-woocommerce' ),
				)
			);
		}

		$is_serviceable = $this->is_pincode_serviceable( $pincode );

		if ( ! $is_serviceable ) {
			$fail_template = get_option(
				'wc_pincode_fail_msg',
				__( '❌ Sorry, we do not deliver to %s yet.', 'pincode-checker-for-woocommerce' )
			);
			$fail_message = sprintf( $fail_template, esc_html( $pincode ) );

			wp_send_json_error(
				array(
					'pincode' => $pincode,
					'message' => apply_filters( 'wc_pincode_failure_message', $fail_message, $pincode ),
				)
			);
		}

		// Optional GPS coordinates from 1-click geolocation.
		$raw_lat = sanitize_text_field( wp_unslash( $_POST['lat'] ?? $_POST['latitude'] ?? '' ) );
		$raw_lng = sanitize_text_field( wp_unslash( $_POST['lng'] ?? $_POST['lon'] ?? $_POST['longitude'] ?? '' ) );

		// Save to WooCommerce session and customer object.
		if ( function_exists( 'WC' ) ) {
			if ( WC()->session ) {
				if ( method_exists( WC()->session, 'has_session' ) && ! WC()->session->has_session() ) {
					if ( method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
						WC()->session->set_customer_session_cookie( true );
					}
				}
				WC()->session->set( 'customer_pincode', $pincode );
				WC()->session->set( 'wc_delivery_pincode', $pincode );

				if ( ! empty( $raw_lat ) && ! empty( $raw_lng ) ) {
					WC()->session->set( 'wc_pincode_customer_lat', $raw_lat );
					WC()->session->set( 'wc_pincode_customer_lng', $raw_lng );
				}
			}

			if ( WC()->customer ) {
				WC()->customer->set_shipping_postcode( $pincode );
				WC()->customer->set_billing_postcode( $pincode );
				WC()->customer->save();
			}

			if ( ! empty( $raw_lat ) && ! empty( $raw_lng ) && is_user_logged_in() ) {
				$user_id  = get_current_user_id();
				$maps_url = 'https://www.google.com/maps?q=' . rawurlencode( $raw_lat . ',' . $raw_lng );
				update_user_meta( $user_id, 'shipping_latitude', $raw_lat );
				update_user_meta( $user_id, 'shipping_longitude', $raw_lng );
				update_user_meta( $user_id, 'shipping_google_maps_url', esc_url_raw( $maps_url ) );
			}

			if ( WC()->cart && ! WC()->cart->is_empty() ) {
				WC()->cart->calculate_shipping();
				WC()->cart->calculate_totals();
			}
		}

		$success_template = get_option(
			'wc_pincode_success_msg',
			__( '✅ Great news! We deliver to %s.', 'pincode-checker-for-woocommerce' )
		);
		$success_message = sprintf( $success_template, esc_html( $pincode ) );
		$subtext         = (string) apply_filters( 'wc_pincode_success_subtext', '', $pincode );

		wp_send_json_success(
			array(
				'pincode'            => $pincode,
				'message'            => apply_filters( 'wc_pincode_success_message', $success_message, $pincode ),
				'subtext'            => $subtext,
				'shipping_latitude'  => $raw_lat,
				'shipping_longitude' => $raw_lng,
			)
		);
	}

	/**
	 * AJAX Handler: Clear verified pincode and reset WooCommerce session + customer location.
	 */
	public function ajax_clear_pincode(): void {
		check_ajax_referer( 'wc_pincode_checker_nonce', 'nonce' );

		if ( function_exists( 'WC' ) ) {
			if ( WC()->session ) {
				WC()->session->set( 'customer_pincode', '' );
				WC()->session->set( 'wc_delivery_pincode', '' );
				WC()->session->set( 'wc_pincode_customer_lat', '' );
				WC()->session->set( 'wc_pincode_customer_lng', '' );
				WC()->session->__unset( 'customer_pincode' );
				WC()->session->__unset( 'wc_delivery_pincode' );
				WC()->session->__unset( 'wc_pincode_customer_lat' );
				WC()->session->__unset( 'wc_pincode_customer_lng' );
			}

			if ( WC()->customer ) {
				WC()->customer->set_shipping_postcode( '' );
				WC()->customer->set_billing_postcode( '' );
				WC()->customer->save();
			}

			if ( WC()->cart && ! WC()->cart->is_empty() ) {
				WC()->cart->calculate_shipping();
				WC()->cart->calculate_totals();
			}
		}

		wp_send_json_success(
			array(
				'cleared' => true,
				'message' => __( 'Location reset. Enter a new pincode.', 'pincode-checker-for-woocommerce' ),
			)
		);
	}

	/**
	 * Pre-fill checkout billing and shipping postcode from verified session.
	 *
	 * @param mixed $value Existing checkout field value.
	 * @param mixed $input Field input name.
	 * @return mixed
	 */
	public function prefill_checkout_postcode( mixed $value, mixed $input ): mixed {
		if ( empty( $value ) && is_string( $input ) && in_array( $input, array( 'billing_postcode', 'shipping_postcode' ), true ) ) {
			if ( function_exists( 'WC' ) && WC()->session ) {
				$saved_pincode = WC()->session->get( 'customer_pincode' );
				if ( ! empty( $saved_pincode ) ) {
					return sanitize_text_field( (string) $saved_pincode );
				}
			}
		}
		return $value;
	}

	/**
	 * Strict server-side checkout validation (Classic Shortcode Checkout).
	 *
	 * @param array     $data   Posted checkout data.
	 * @param \WP_Error $errors Checkout error collection.
	 */
	public function validate_checkout_postcode( array $data, \WP_Error $errors ): void {
		$enforce_checkout = 'yes' === get_option( 'wc_pincode_enforce_checkout', 'yes' );
		if ( ! $enforce_checkout ) {
			return;
		}

		// Skip deliverability enforcement if cart contains only virtual/downloadable items not requiring physical shipping.
		if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->needs_shipping() ) {
			return;
		}

		$ship_to_different = ! empty( $data['ship_to_different_address'] );
		$target_postcode   = $ship_to_different
			? ( $data['shipping_postcode'] ?? '' )
			: ( ! empty( $data['shipping_postcode'] ) ? $data['shipping_postcode'] : ( $data['billing_postcode'] ?? '' ) );

		$pincode = preg_replace( '/\D/', '', (string) $target_postcode );

		if ( empty( $pincode ) ) {
			return;
		}

		if ( ! $this->is_pincode_serviceable( $pincode ) ) {
			$custom_notice = apply_filters(
				'wc_pincode_checkout_error_notice',
				__( 'Sorry, we do not deliver to the entered pincode. Please enter a serviceable pincode to proceed.', 'pincode-checker-for-woocommerce' ),
				$pincode
			);
			$errors->add( 'wc_unserviceable_pincode', $custom_notice );
		}
	}

	/**
	 * Strict server-side checkout validation (Modern WooCommerce Blocks / StoreAPI Checkout).
	 *
	 * @param mixed $order   Order object.
	 * @param mixed $request Store API request.
	 * @throws \Exception When postcode is unserviceable.
	 */
	public function validate_blocks_checkout( mixed $order, mixed $request ): void {
		$enforce_checkout = 'yes' === get_option( 'wc_pincode_enforce_checkout', 'yes' );
		if ( ! $enforce_checkout || ! is_object( $order ) || ! method_exists( $order, 'get_shipping_postcode' ) ) {
			return;
		}

		// Skip deliverability enforcement if order contains only digital/virtual products not requiring physical delivery.
		if ( method_exists( $order, 'needs_shipping_address' ) && ! $order->needs_shipping_address() ) {
			return;
		}

		$shipping_postcode = (string) $order->get_shipping_postcode();
		$billing_postcode  = method_exists( $order, 'get_billing_postcode' ) ? (string) $order->get_billing_postcode() : '';
		$target_postcode   = ! empty( $shipping_postcode ) ? $shipping_postcode : $billing_postcode;

		$pincode = preg_replace( '/\D/', '', $target_postcode );
		if ( empty( $pincode ) ) {
			return;
		}

		if ( ! $this->is_pincode_serviceable( $pincode ) ) {
			$custom_notice = apply_filters(
				'wc_pincode_checkout_error_notice',
				__( 'Sorry, we do not deliver to the entered pincode. Please enter a serviceable pincode to proceed.', 'pincode-checker-for-woocommerce' ),
				$pincode
			);

			if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'wc_unserviceable_pincode',
					$custom_notice,
					400
				);
			}

			throw new \Exception( $custom_notice );
		}
	}

	/**
	 * Validate latitude and longitude coordinate pair.
	 *
	 * @param mixed $lat Latitude.
	 * @param mixed $lng Longitude.
	 * @return bool True if valid geo coordinates.
	 */
	public function is_valid_coordinate( mixed $lat, mixed $lng ): bool {
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return false;
		}
		$lat_float = (float) $lat;
		$lng_float = (float) $lng;
		return ( $lat_float >= -90.0 && $lat_float <= 90.0 && $lng_float >= -180.0 && $lng_float <= 180.0 );
	}

	/**
	 * Attach GPS coordinates and Google Maps navigation URL to WooCommerce order.
	 *
	 * @param mixed  $order Order object.
	 * @param string $lat   Latitude string or float.
	 * @param string $lng   Longitude string or float.
	 */
	public function attach_coordinates_to_order( mixed $order, string $lat, string $lng ): void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$lat = sanitize_text_field( trim( $lat ) );
		$lng = sanitize_text_field( trim( $lng ) );

		if ( empty( $lat ) || empty( $lng ) || ! $this->is_valid_coordinate( $lat, $lng ) ) {
			return;
		}

		$maps_url = 'https://www.google.com/maps?q=' . rawurlencode( $lat . ',' . $lng );

		$order->update_meta_data( '_shipping_latitude', $lat );
		$order->update_meta_data( '_shipping_longitude', $lng );
		$order->update_meta_data( '_shipping_google_maps_url', esc_url_raw( $maps_url ) );
		if ( method_exists( $order, 'save_meta_data' ) ) {
			$order->save_meta_data();
		}

		// Bind to customer user profile if customer is logged in
		$customer_id = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
		if ( $customer_id > 0 ) {
			update_user_meta( $customer_id, 'shipping_latitude', $lat );
			update_user_meta( $customer_id, 'shipping_longitude', $lng );
			update_user_meta( $customer_id, 'shipping_google_maps_url', esc_url_raw( $maps_url ) );
		}
	}

	/**
	 * Hook: Bind GPS coordinates to Classic Checkout order (woocommerce_checkout_create_order).
	 *
	 * @param mixed $order Order object.
	 * @param array $data  Checkout POST data.
	 */
	public function bind_coordinates_to_classic_order( mixed $order, array $data ): void {
		$lat = ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get( 'wc_pincode_customer_lat', '' ) : '';
		$lng = ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get( 'wc_pincode_customer_lng', '' ) : '';

		// Check if passed directly in checkout POST (from custom fields or address book)
		if ( empty( $lat ) && ! empty( $_POST['shipping_latitude'] ) ) {
			$lat = sanitize_text_field( wp_unslash( $_POST['shipping_latitude'] ) );
			$lng = sanitize_text_field( wp_unslash( $_POST['shipping_longitude'] ?? '' ) );
		}

		// Fallback to customer profile if user is logged in
		if ( empty( $lat ) && is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$lat     = (string) get_user_meta( $user_id, 'shipping_latitude', true );
			$lng     = (string) get_user_meta( $user_id, 'shipping_longitude', true );
		}

		$this->attach_coordinates_to_order( $order, $lat, $lng );
	}

	/**
	 * Hook: Bind GPS coordinates to modern React Blocks order (woocommerce_store_api_checkout_update_order_from_request).
	 *
	 * @param mixed $order   Order object.
	 * @param mixed $request Store API request.
	 */
	public function bind_coordinates_to_blocks_order( mixed $order, mixed $request ): void {
		$lat = ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get( 'wc_pincode_customer_lat', '' ) : '';
		$lng = ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get( 'wc_pincode_customer_lng', '' ) : '';

		// Check if Store API request contains custom extension data
		if ( empty( $lat ) && is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$shipping_coords = $request->get_param( 'shipping_coordinates' );
			if ( is_array( $shipping_coords ) ) {
				$lat = sanitize_text_field( (string) ( $shipping_coords['latitude'] ?? '' ) );
				$lng = sanitize_text_field( (string) ( $shipping_coords['longitude'] ?? '' ) );
			}
		}

		// Fallback to customer profile if user is logged in
		if ( empty( $lat ) && is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$lat     = (string) get_user_meta( $user_id, 'shipping_latitude', true );
			$lng     = (string) get_user_meta( $user_id, 'shipping_longitude', true );
		}

		$this->attach_coordinates_to_order( $order, $lat, $lng );
	}

	/**
	 * Display exact GPS coordinates and 1-click Google Maps Navigation in WP Admin order view.
	 *
	 * @param mixed $order Order instance.
	 */
	public function render_admin_order_gps_dispatch( mixed $order ): void {
		if ( is_numeric( $order ) || ( is_object( $order ) && ! method_exists( $order, 'get_meta' ) ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}

		$lat      = (string) $order->get_meta( '_shipping_latitude' );
		$lng      = (string) $order->get_meta( '_shipping_longitude' );
		$maps_url = (string) $order->get_meta( '_shipping_google_maps_url' );

		if ( empty( $lat ) || empty( $lng ) || ! $this->is_valid_coordinate( $lat, $lng ) ) {
			return;
		}

		if ( empty( $maps_url ) ) {
			$maps_url = 'https://www.google.com/maps?q=' . rawurlencode( $lat . ',' . $lng );
		}
		?>
		<div class="wc-pincode-admin-order-gps-card" style="margin-top: 14px; padding: 12px 14px; background: #f4f9f4; border: 1px solid #c2e0c0; border-radius: 8px;">
			<p style="margin: 0 0 4px; font-weight: 700; color: #2d5a27; font-size: 13px; display: flex; align-items: center; gap: 6px;">
				<span aria-hidden="true">📍</span> <?php esc_html_e( 'Exact Delivery GPS Coordinates', 'pincode-checker-for-woocommerce' ); ?>
			</p>
			<p style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, monospace; font-size: 12px; color: #333333; letter-spacing: 0.3px;">
				<strong><?php esc_html_e( 'Latitude:', 'pincode-checker-for-woocommerce' ); ?></strong> <?php echo esc_html( $lat ); ?> &nbsp;|&nbsp;
				<strong><?php esc_html_e( 'Longitude:', 'pincode-checker-for-woocommerce' ); ?></strong> <?php echo esc_html( $lng ); ?>
			</p>
			<a href="<?php echo esc_url( $maps_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small" style="background: #2d5a27; color: #ffffff; border-color: #2d5a27; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; text-decoration: none; padding: 4px 10px; border-radius: 4px;">
				<span aria-hidden="true">🗺️</span> <?php esc_html_e( 'Navigate via Google Maps', 'pincode-checker-for-woocommerce' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Register REST API routes and fields for cross-platform mobile apps (iOS & Android).
	 */
	public function register_rest_api(): void {
		// 1. Deliverability Check Endpoints (Generic route + App-friendly alias)
		$namespaces = array( 'pincode-checker/v1', 'circlefarms/v1' );
		foreach ( $namespaces as $ns ) {
			register_rest_route(
				$ns,
				'/check-deliverability',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_check_deliverability' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'pincode'   => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'latitude'  => array(
							'required'          => false,
							'type'              => array( 'string', 'number' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'longitude' => array(
							'required'          => false,
							'type'              => array( 'string', 'number' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			);
		}

		// 2. Extend WooCommerce Orders API (/wp-json/wc/v3/orders)
		register_rest_field(
			'shop_order',
			'shipping_coordinates',
			array(
				'get_callback'    => static function ( mixed $order_data ): ?array {
					$order_id = is_array( $order_data ) ? (int) ( $order_data['id'] ?? 0 ) : ( is_object( $order_data ) ? (int) ( $order_data->ID ?? 0 ) : 0 );
					if ( $order_id <= 0 ) {
						return null;
					}
					$order = wc_get_order( $order_id );
					if ( ! $order ) {
						return null;
					}
					$lat = (string) $order->get_meta( '_shipping_latitude' );
					$lng = (string) $order->get_meta( '_shipping_longitude' );
					if ( empty( $lat ) && empty( $lng ) ) {
						return null;
					}
					return array(
						'latitude'        => $lat,
						'longitude'       => $lng,
						'google_maps_url' => (string) $order->get_meta( '_shipping_google_maps_url' ),
					);
				},
				'update_callback' => static function ( mixed $value, mixed $order_obj ): void {
					if ( is_array( $value ) ) {
						$order = is_a( $order_obj, 'WC_Order' ) ? $order_obj : wc_get_order( $order_obj );
						if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
							return;
						}
						$lat = sanitize_text_field( (string) ( $value['latitude'] ?? '' ) );
						$lng = sanitize_text_field( (string) ( $value['longitude'] ?? '' ) );
						if ( ! empty( $lat ) && ! empty( $lng ) && is_numeric( $lat ) && is_numeric( $lng ) ) {
							$maps_url = 'https://www.google.com/maps?q=' . rawurlencode( $lat . ',' . $lng );
							$order->update_meta_data( '_shipping_latitude', $lat );
							$order->update_meta_data( '_shipping_longitude', $lng );
							$order->update_meta_data( '_shipping_google_maps_url', esc_url_raw( $maps_url ) );
							if ( method_exists( $order, 'save_meta_data' ) ) {
								$order->save_meta_data();
							}
						}
					}
				},
				'schema'          => array(
					'description' => __( 'Delivery GPS coordinates and Google Maps navigation link', 'pincode-checker-for-woocommerce' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);

		// 3. Extend WooCommerce Customers API (/wp-json/wc/v3/customers)
		register_rest_field(
			'customer',
			'shipping_coordinates',
			array(
				'get_callback'    => static function ( mixed $customer_data ): ?array {
					$user_id = is_array( $customer_data ) ? (int) ( $customer_data['id'] ?? 0 ) : ( is_object( $customer_data ) ? (int) ( $customer_data->ID ?? 0 ) : 0 );
					if ( $user_id <= 0 ) {
						return null;
					}
					$lat = (string) get_user_meta( $user_id, 'shipping_latitude', true );
					$lng = (string) get_user_meta( $user_id, 'shipping_longitude', true );
					if ( empty( $lat ) && empty( $lng ) ) {
						return null;
					}
					return array(
						'latitude'        => $lat,
						'longitude'       => $lng,
						'google_maps_url' => (string) get_user_meta( $user_id, 'shipping_google_maps_url', true ),
					);
				},
				'update_callback' => static function ( mixed $value, mixed $customer_obj ): void {
					if ( is_array( $value ) ) {
						$user_id = is_object( $customer_obj ) && method_exists( $customer_obj, 'get_id' ) ? (int) $customer_obj->get_id() : ( is_object( $customer_obj ) && isset( $customer_obj->ID ) ? (int) $customer_obj->ID : ( is_numeric( $customer_obj ) ? (int) $customer_obj : 0 ) );
						if ( $user_id > 0 ) {
							$lat = sanitize_text_field( (string) ( $value['latitude'] ?? '' ) );
							$lng = sanitize_text_field( (string) ( $value['longitude'] ?? '' ) );
							if ( ! empty( $lat ) && ! empty( $lng ) && is_numeric( $lat ) && is_numeric( $lng ) ) {
								$maps_url = 'https://www.google.com/maps?q=' . rawurlencode( $lat . ',' . $lng );
								update_user_meta( $user_id, 'shipping_latitude', $lat );
								update_user_meta( $user_id, 'shipping_longitude', $lng );
								update_user_meta( $user_id, 'shipping_google_maps_url', esc_url_raw( $maps_url ) );
							}
						}
					}
				},
				'schema'          => array(
					'description' => __( 'Customer default shipping GPS rooftop coordinates', 'pincode-checker-for-woocommerce' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
	}

	/**
	 * REST API Callback: Check Deliverability.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_check_deliverability( \WP_REST_Request $request ): \WP_REST_Response {
		$raw_pincode = (string) $request->get_param( 'pincode' );
		$pincode     = preg_replace( '/\D/', '', $raw_pincode );

		if ( 6 !== strlen( $pincode ) ) {
			return new \WP_REST_Response(
				array(
					'success'     => false,
					'serviceable' => false,
					'message'     => __( 'Please enter a valid 6-digit numeric pincode.', 'pincode-checker-for-woocommerce' ),
				),
				400
			);
		}

		$is_serviceable = $this->is_pincode_serviceable( $pincode );
		$lat            = sanitize_text_field( (string) ( $request->get_param( 'latitude' ) ?? '' ) );
		$lng            = sanitize_text_field( (string) ( $request->get_param( 'longitude' ) ?? '' ) );
		$maps_url       = ( ! empty( $lat ) && ! empty( $lng ) ) ? 'https://www.google.com/maps?q=' . rawurlencode( $lat . ',' . $lng ) : '';

		if ( ! $is_serviceable ) {
			$fail_template = get_option(
				'wc_pincode_fail_msg',
				__( '❌ Sorry, we do not deliver to %s yet.', 'pincode-checker-for-woocommerce' )
			);
			return new \WP_REST_Response(
				array(
					'success'     => false,
					'serviceable' => false,
					'pincode'     => $pincode,
					'message'     => sprintf( $fail_template, $pincode ),
				),
				200
			);
		}

		$success_template = get_option(
			'wc_pincode_success_msg',
			__( '✅ Great news! We deliver to %s.', 'pincode-checker-for-woocommerce' )
		);

		return new \WP_REST_Response(
			array(
				'success'            => true,
				'serviceable'        => true,
				'pincode'            => $pincode,
				'message'            => sprintf( $success_template, $pincode ),
				'shipping_latitude'  => $lat,
				'shipping_longitude' => $lng,
				'google_maps_url'    => $maps_url,
			),
			200
		);
	}

	/**
	 * Render Shortcode: [pincode_checker]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_shortcode( array|string $atts = array() ): string {
		$attributes = shortcode_atts(
			array(
				'layout'      => 'standard',
				'placeholder' => __( 'Enter 6-digit pincode', 'pincode-checker-for-woocommerce' ),
				'button_text' => __( 'Check', 'pincode-checker-for-woocommerce' ),
				'title'       => __( 'Check Delivery to Your Area', 'pincode-checker-for-woocommerce' ),
				'show_title'  => 'false',
			),
			$atts,
			'pincode_checker'
		);

		$current_pincode = $this->get_current_pincode();
		$is_verified     = ! empty( $current_pincode ) && $this->is_pincode_serviceable( $current_pincode );

		ob_start();
		?>
		<div class="wc-pincode-checker-widget layout-<?php echo esc_attr( $attributes['layout'] ); ?><?php echo $is_verified ? ' has-verified-pincode' : ''; ?>" data-current-pincode="<?php echo esc_attr( $current_pincode ); ?>">
			
			<?php if ( 'true' === $attributes['show_title'] && ! empty( $attributes['title'] ) ) : ?>
				<h4 class="wc-widget-title">
					<span class="wc-widget-title-icon" aria-hidden="true">📍</span>
					<?php echo esc_html( $attributes['title'] ); ?>
				</h4>
			<?php endif; ?>

			<!-- Verified Pill / Badge View -->
			<div class="wc-badge-card" style="<?php echo $is_verified ? 'display: flex;' : 'display: none;'; ?>">
				<div class="wc-badge-content">
					<span class="wc-badge-icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
							<circle cx="12" cy="10" r="3"></circle>
						</svg>
					</span>
					<span class="wc-badge-label">
						<?php esc_html_e( 'Delivering to', 'pincode-checker-for-woocommerce' ); ?>
						<strong class="wc-verified-code"><?php echo esc_html( $current_pincode ); ?></strong>
					</span>
				</div>
				<button type="button" class="wc-change-pincode-btn" aria-label="<?php esc_attr_e( 'Change delivery pincode', 'pincode-checker-for-woocommerce' ); ?>">
					<?php esc_html_e( 'Change', 'pincode-checker-for-woocommerce' ); ?>
				</button>
			</div>

			<!-- Pincode Input Form -->
			<?php echo $this->get_geolocation_button_html( $is_verified ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<form class="wc-pincode-form" style="<?php echo $is_verified ? 'display: none;' : 'display: flex;'; ?>" novalidate>
				<div class="wc-input-wrapper">
					<span class="wc-input-icon" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
							<circle cx="12" cy="10" r="3"></circle>
						</svg>
					</span>
					<input 
						type="tel" 
						name="pincode" 
						class="wc-pincode-input" 
						placeholder="<?php echo esc_attr( $attributes['placeholder'] ); ?>" 
						value="<?php echo esc_attr( $current_pincode ); ?>"
						maxlength="6" 
						inputmode="numeric" 
						pattern="[0-9]{6}" 
						autocomplete="postal-code"
						aria-label="<?php esc_attr_e( 'Enter 6-digit pincode', 'pincode-checker-for-woocommerce' ); ?>"
						required 
					/>
					<button type="button" class="wc-input-clear-btn" aria-label="<?php esc_attr_e( 'Clear input', 'pincode-checker-for-woocommerce' ); ?>" style="<?php echo ! empty( $current_pincode ) ? 'display: flex;' : 'display: none;'; ?>">
						&times;
					</button>
				</div>
				<button type="submit" class="wc-submit-btn">
					<span class="wc-btn-text"><?php echo esc_html( $attributes['button_text'] ); ?></span>
					<span class="wc-btn-spinner" aria-hidden="true"></span>
				</button>
			</form>

			<!-- Instant AJAX Result Area -->
			<div class="wc-result-box" role="status" aria-live="polite" style="display: none;"></div>

		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render widget inside WooCommerce Slide-Out / Mini Cart.
	 */
	public function render_minicart_widget(): void {
		$enabled = 'yes' === get_option( 'wc_pincode_enable_minicart', 'yes' );
		if ( ! $enabled ) {
			return;
		}

		echo '<div class="wc-minicart-checker-wrap">';
		echo $this->render_shortcode(
			array(
				'layout'      => 'compact',
				'placeholder' => __( 'Check pincode', 'pincode-checker-for-woocommerce' ),
				'button_text' => __( 'Apply', 'pincode-checker-for-woocommerce' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Register WooCommerce Settings Section:
	 * WooCommerce > Settings > Shipping > Pincode Checker
	 *
	 * @param array $sections Existing sections.
	 * @return array
	 */
	public function add_shipping_section( array $sections ): array {
		$sections['pincode_checker'] = __( 'Pincode Checker', 'pincode-checker-for-woocommerce' );
		return $sections;
	}

	/**
	 * Define settings for WooCommerce > Settings > Shipping > Pincode Checker.
	 *
	 * @param array  $settings        Existing settings.
	 * @param string $current_section Current shipping section.
	 * @return array
	 */
	public function get_shipping_settings( array $settings, string $current_section ): array {
		if ( 'pincode_checker' !== $current_section ) {
			return $settings;
		}

		$zone_options = array();
		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			$zones = \WC_Shipping_Zones::get_zones();
			foreach ( $zones as $z ) {
				$zid   = is_array( $z ) ? (int) ( $z['zone_id'] ?? 0 ) : (int) ( $z->zone_id ?? 0 );
				$zname = is_array( $z ) ? (string) ( $z['zone_name'] ?? '' ) : (string) ( $z->zone_name ?? '' );
				if ( $zid > 0 ) {
					$zone_options[ (string) $zid ] = sprintf( '%s (ID: %d)', $zname, $zid );
				}
			}
		}

		return array(
			// Zone Selection Section
			array(
				'title' => __( 'Pincode Checker Settings', 'pincode-checker-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Serviceable pincodes are evaluated directly from your native WooCommerce Shipping Zones (exact codes, ranges like 600001...600120, and wildcards like 600*). Configure plugin placement and options below.', 'pincode-checker-for-woocommerce' ),
				'id'    => 'wc_pincode_options',
			),
			array(
				'title'    => __( 'Target Shipping Zones', 'pincode-checker-for-woocommerce' ),
				'desc'     => __( 'Select the WooCommerce Shipping Zone(s) to validate against. If left empty, the plugin automatically evaluates all active shipping zones configured with postcode rules.', 'pincode-checker-for-woocommerce' ),
				'id'       => 'wc_pincode_target_zones',
				'type'     => 'multiselect',
				'class'    => 'wc-enhanced-select',
				'options'  => $zone_options,
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wc_pincode_options',
			),

			// Auto-Placement Section
			array(
				'title' => __( 'Auto-Placement Settings', 'pincode-checker-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Choose automated insertion locations for 100% plug-and-play setup across sites without manual template editing.', 'pincode-checker-for-woocommerce' ),
				'id'    => 'wc_pincode_placement_options',
			),
			array(
				'title'    => __( 'Header Placement', 'pincode-checker-for-woocommerce' ),
				'desc'     => __( 'Select how the deliverability checker popover appears in your website header.', 'pincode-checker-for-woocommerce' ),
				'id'       => 'wc_pincode_header_placement',
				'default'  => 'replace_find_a_store',
				'type'     => 'select',
				'options'  => array(
					'replace_find_a_store' => __( "Replace 'Find a Store' (Server-Side Interceptor)", 'pincode-checker-for-woocommerce' ),
					'append_primary_menu'  => __( 'Append to Primary / Main Navigation Menu', 'pincode-checker-for-woocommerce' ),
					'none'                 => __( 'Off (Use Elementor Widget or Shortcode only)', 'pincode-checker-for-woocommerce' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'   => __( 'Mobile Delivery Strip', 'pincode-checker-for-woocommerce' ),
				'desc'    => __( 'Display a slim delivery location strip below the header on mobile and tablet devices (<= 991px).', 'pincode-checker-for-woocommerce' ),
				'id'      => 'wc_pincode_mobile_strip_enabled',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'   => __( '1-Click Geolocation', 'pincode-checker-for-woocommerce' ),
				'desc'    => __( 'Display a prominent "Use My Current Location" button above the pincode input to auto-detect customer location via browser GPS.', 'pincode-checker-for-woocommerce' ),
				'id'      => 'wc_pincode_enable_geolocation',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'    => __( 'Product Page Placement', 'pincode-checker-for-woocommerce' ),
				'desc'     => __( 'Automatically display a compact delivery checker on single product pages.', 'pincode-checker-for-woocommerce' ),
				'id'       => 'wc_pincode_product_placement',
				'default'  => 'none',
				'type'     => 'select',
				'options'  => array(
					'none'               => __( 'Off', 'pincode-checker-for-woocommerce' ),
					'before_add_to_cart' => __( "Above 'Add to Cart' button", 'pincode-checker-for-woocommerce' ),
					'after_add_to_cart'  => __( "Below 'Add to Cart' button", 'pincode-checker-for-woocommerce' ),
				),
				'desc_tip' => true,
			),
			array(
				'title'   => __( 'Show in Slide-Out Mini-Cart', 'pincode-checker-for-woocommerce' ),
				'desc'    => __( 'Automatically embed a compact pincode validator in AJAX mini-cart drawers.', 'pincode-checker-for-woocommerce' ),
				'id'      => 'wc_pincode_enable_minicart',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wc_pincode_placement_options',
			),

			// Checkout & Messages Section
			array(
				'title' => __( 'Checkout & Messaging Settings', 'pincode-checker-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'wc_pincode_msg_options',
			),
			array(
				'title'   => __( 'Enforce at Checkout', 'pincode-checker-for-woocommerce' ),
				'desc'    => __( 'Block checkout order placement if the customer enters an unserviceable pincode.', 'pincode-checker-for-woocommerce' ),
				'id'      => 'wc_pincode_enforce_checkout',
				'default' => 'yes',
				'type'    => 'checkbox',
			),
			array(
				'title'    => __( 'Success Message Format', 'pincode-checker-for-woocommerce' ),
				'desc'     => __( 'Use %s where the validated pincode should appear.', 'pincode-checker-for-woocommerce' ),
				'id'       => 'wc_pincode_success_msg',
				'default'  => __( '✅ Great news! We deliver to %s.', 'pincode-checker-for-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'min-width:400px; height:70px;',
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Failure Message Format', 'pincode-checker-for-woocommerce' ),
				'desc'     => __( 'Use %s where the entered pincode should appear.', 'pincode-checker-for-woocommerce' ),
				'id'       => 'wc_pincode_fail_msg',
				'default'  => __( '❌ Sorry, we do not deliver to %s yet.', 'pincode-checker-for-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'min-width:400px; height:70px;',
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wc_pincode_msg_options',
			),
		);
	}
}

// Initialize the plugin.
add_action( 'plugins_loaded', static function (): void {
	WC_Pincode_Checker::get_instance();
} );
