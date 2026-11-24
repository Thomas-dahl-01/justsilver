<?php
/**
 * Plugin Name:       JustSilver
 * Description:       Sølvafdeling til WooCommerce: sølvvarer prissat efter dagens sølvkurs (automatisk via API), butikker med åbningstider og tidsbestilling ("se varen i butikken"), sølv-forside og eget sølv-design.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Thomas-dahl-01
 * Text Domain:       justsilver
 * WC requires at least: 8.5
 * Requires Plugins:  woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'NS_VERSION', '1.0.0' );
define( 'NS_FILE', __FILE__ );
define( 'NS_DIR', plugin_dir_path( __FILE__ ) );
define( 'NS_URL', plugin_dir_url( __FILE__ ) );

require_once NS_DIR . 'includes/helpers.php';
require_once NS_DIR . 'includes/class-silver-price.php';
require_once NS_DIR . 'includes/class-price-api.php';
require_once NS_DIR . 'includes/class-product-fields.php';
require_once NS_DIR . 'includes/class-stores.php';
require_once NS_DIR . 'includes/class-bookings.php';
require_once NS_DIR . 'includes/class-settings.php';
require_once NS_DIR . 'includes/class-frontend.php';
require_once NS_DIR . 'includes/class-installer.php';

// Understøt WooCommerce' nye ordretabeller (HPOS) og checkout-blokke.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NS_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', NS_FILE, true );
	}
} );

register_activation_hook( __FILE__, array( 'NS_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NS_Installer', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>JustSilver</strong> kræver, at WooCommerce er installeret og aktiveret.</p></div>';
		} );
		return;
	}
	NS_Silver_Price::init();
	NS_Price_API::init();
	NS_Product_Fields::init();
	NS_Stores::init();
	NS_Bookings::init();
	NS_Settings::init();
	NS_Frontend::init();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once NS_DIR . 'includes/class-cli.php';
		WP_CLI::add_command( 'justsilver', 'NS_CLI' );
	}
} );
