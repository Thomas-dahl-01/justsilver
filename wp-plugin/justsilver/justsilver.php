<?php
/**
 * Plugin Name:       JustSilver
 * Description:       Turns WooCommerce into a silver shop: prices follow the daily silver spot price (updated automatically), stores with opening hours, appointment booking with product reservation, price chart, price alerts, a sell-your-silver calculator and Gutenberg blocks.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Thomas-dahl-01
 * License:           GPL-2.0-or-later
 * Text Domain:       justsilver
 * Domain Path:       /languages
 * WC requires at least: 8.5
 * Requires Plugins:  woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'NS_VERSION', '1.1.0' );
define( 'NS_FILE', __FILE__ );
define( 'NS_DIR', plugin_dir_path( __FILE__ ) );
define( 'NS_URL', plugin_dir_url( __FILE__ ) );

require_once NS_DIR . 'includes/helpers.php';
require_once NS_DIR . 'includes/class-silver-price.php';
require_once NS_DIR . 'includes/class-price-api.php';
require_once NS_DIR . 'includes/class-product-fields.php';
require_once NS_DIR . 'includes/class-stores.php';
require_once NS_DIR . 'includes/class-ics.php';
require_once NS_DIR . 'includes/class-bookings.php';
require_once NS_DIR . 'includes/class-reservations.php';
require_once NS_DIR . 'includes/class-reminders.php';
require_once NS_DIR . 'includes/class-alerts.php';
require_once NS_DIR . 'includes/class-sell-calculator.php';
require_once NS_DIR . 'includes/class-chart.php';
require_once NS_DIR . 'includes/class-settings.php';
require_once NS_DIR . 'includes/class-frontend.php';
require_once NS_DIR . 'includes/class-blocks.php';
require_once NS_DIR . 'includes/class-installer.php';

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NS_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', NS_FILE, true );
	}
} );

register_activation_hook( __FILE__, array( 'NS_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NS_Installer', 'deactivate' ) );

add_action( 'init', function () {
	load_plugin_textdomain( 'justsilver', false, dirname( plugin_basename( NS_FILE ) ) . '/languages' );
}, 1 );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'JustSilver requires WooCommerce to be installed and active.', 'justsilver' ) . '</p></div>';
		} );
		return;
	}
	NS_Silver_Price::init();
	NS_Price_API::init();
	NS_Product_Fields::init();
	NS_Stores::init();
	NS_Bookings::init();
	NS_Reservations::init();
	NS_Reminders::init();
	NS_Alerts::init();
	NS_Sell_Calculator::init();
	NS_Chart::init();
	NS_Settings::init();
	NS_Frontend::init();
	NS_Blocks::init();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once NS_DIR . 'includes/class-cli.php';
		WP_CLI::add_command( 'justsilver', 'NS_CLI' );
	}
} );
