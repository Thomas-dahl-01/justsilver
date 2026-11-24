<?php
/**
 * Unit-test bootstrap: minimal stand-ins for the WordPress/WooCommerce functions the pure
 * plugin logic touches, so the tests run fast without a database or a WordPress install.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'NS_DIR', dirname( __DIR__ ) . '/justsilver/' );

$GLOBALS['ns_test_options'] = array();
$GLOBALS['ns_test_filters'] = array();

$GLOBALS['ns_test_translations'] = 0; // counts translation calls (see BootTest)
function __( $text, $domain = null ) { $GLOBALS['ns_test_translations']++; return $text; }
function _x( $text, $context, $domain = null ) { $GLOBALS['ns_test_translations']++; return $text; }
function _n( $single, $plural, $n, $domain = null ) { $GLOBALS['ns_test_translations']++; return 1 === $n ? $single : $plural; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { return true; }
function add_shortcode( $tag, $cb ) { return true; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function get_option( $key, $default = false ) { return $GLOBALS['ns_test_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['ns_test_options'][ $key ] = $value; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['ns_test_filters'][ $hook ][] = $cb; return true; }
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['ns_test_filters'][ $hook ] ?? array() as $cb ) {
		$value = $cb( $value, ...$args );
	}
	return $value;
}
function wp_hash( $data ) { return hash( 'sha256', 'test-salt' . $data ); }

class WP_Error {
	private string $msg;
	public function __construct( $code = '', $message = '' ) { $this->msg = $message; }
	public function get_error_message() { return $this->msg; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

/** Tiny stand-in for WC_Product: just meta storage. */
class WC_Product {
	private array $meta = array();
	public function __construct( array $meta = array() ) { $this->meta = $meta; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function all_meta() { return $this->meta; }
}

require NS_DIR . 'includes/helpers.php';
require NS_DIR . 'includes/class-silver-price.php';
require NS_DIR . 'includes/class-price-api.php';
require NS_DIR . 'includes/class-product-fields.php';
require NS_DIR . 'includes/class-stores.php';
require NS_DIR . 'includes/class-ics.php';
require NS_DIR . 'includes/class-chart.php';
require NS_DIR . 'includes/class-alerts.php';
