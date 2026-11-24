<?php
/**
 * Native Elementor Widget for Pincode Checker for WooCommerce
 *
 * @package WC_Pincode_Checker
 * @author  Akhil Nichani (https://github.com/akinich)
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

/**
 * Class WC_Pincode_Checker_Elementor_Widget
 */
class WC_Pincode_Checker_Elementor_Widget extends Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'wc_pincode_checker';
	}

	/**
	 * Get widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return __( 'Pincode Deliverability Checker', 'pincode-checker-for-woocommerce' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string Widget icon class.
	 */
	public function get_icon(): string {
		return 'eicon-map-pin';
	}

	/**
	 * Get widget categories.
	 *
	 * @return array Widget categories.
	 */
	public function get_categories(): array {
		return array( 'general', 'woocommerce-elements' );
	}

	/**
	 * Get widget keywords.
	 *
	 * @return array Widget keywords.
	 */
	public function get_keywords(): array {
		return array( 'pincode', 'delivery', 'deliverability', 'shipping', 'postal', 'zipcode' );
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Content & Display', 'pincode-checker-for-woocommerce' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'widget_style',
			array(
				'label'   => __( 'Display Style', 'pincode-checker-for-woocommerce' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'header_popover',
				'options' => array(
					'header_popover' => __( 'Header Trigger with Popover Dropdown', 'pincode-checker-for-woocommerce' ),
					'standard'       => __( 'Standard Inline Form & Badge', 'pincode-checker-for-woocommerce' ),
					'compact'        => __( 'Compact Inline Form (Sidebars / Cards)', 'pincode-checker-for-woocommerce' ),
				),
			)
		);

		$this->add_control(
			'placeholder',
			array(
				'label'       => __( 'Input Placeholder', 'pincode-checker-for-woocommerce' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Enter 6-digit pincode', 'pincode-checker-for-woocommerce' ),
				'placeholder' => __( 'Enter placeholder text', 'pincode-checker-for-woocommerce' ),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => __( 'Button Text', 'pincode-checker-for-woocommerce' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Check', 'pincode-checker-for-woocommerce' ),
				'placeholder' => __( 'Enter button label', 'pincode-checker-for-woocommerce' ),
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => __( 'Show Title', 'pincode-checker-for-woocommerce' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'pincode-checker-for-woocommerce' ),
				'label_off'    => __( 'No', 'pincode-checker-for-woocommerce' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'    => array(
					'widget_style' => array( 'standard', 'compact' ),
				),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Title Text', 'pincode-checker-for-woocommerce' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Check Delivery to Your Area', 'pincode-checker-for-woocommerce' ),
				'placeholder' => __( 'Enter title', 'pincode-checker-for-woocommerce' ),
				'condition'   => array(
					'show_title'   => 'yes',
					'widget_style' => array( 'standard', 'compact' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend.
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$style    = $settings['widget_style'] ?? 'header_popover';
		$instance = \WC_Pincode_Checker::get_instance();

		if ( 'header_popover' === $style ) {
			echo $instance->get_header_delivery_checker_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$show_title = ( 'yes' === ( $settings['show_title'] ?? '' ) ) ? 'true' : 'false';

		echo $instance->render_shortcode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'layout'      => $style,
				'placeholder' => $settings['placeholder'] ?? __( 'Enter 6-digit pincode', 'pincode-checker-for-woocommerce' ),
				'button_text' => $settings['button_text'] ?? __( 'Check', 'pincode-checker-for-woocommerce' ),
				'show_title'  => $show_title,
				'title'       => $settings['title'] ?? __( 'Check Delivery to Your Area', 'pincode-checker-for-woocommerce' ),
			)
		);
	}
}
