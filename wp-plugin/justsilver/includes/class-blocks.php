<?php
/**
 * Gutenberg blocks for every JustSilver feature, so editors can place them with the block editor.
 * Rendered on the server (same output as the shortcodes); the editor shows a live preview via ServerSideRender.
 * No build step: the editor script is plain JavaScript using the wp.* globals.
 */
defined( 'ABSPATH' ) || exit;

class NS_Blocks {

	public static function blocks(): array {
		return array(
			'forside'   => array( 'title' => __( 'Silver front page', 'justsilver' ), 'icon' => 'star-filled', 'code' => 'justsilver_forside', 'desc' => __( 'Hero with live price, categories, chart, popular items and stores.', 'justsilver' ) ),
			'booking'   => array( 'title' => __( 'Appointment booking', 'justsilver' ), 'icon' => 'calendar-alt', 'code' => 'justsilver_booking', 'desc' => __( 'Customers choose store, day and time.', 'justsilver' ) ),
			'pris'      => array( 'title' => __( 'Silver price ticker', 'justsilver' ), 'icon' => 'chart-line', 'code' => 'justsilver_pris', 'desc' => __( 'Today’s price with change since yesterday.', 'justsilver' ), 'attrs' => array( 'enhed' => array( 'type' => 'string', 'default' => 'g' ) ) ),
			'prisgraf'  => array( 'title' => __( 'Silver price chart', 'justsilver' ), 'icon' => 'chart-area', 'code' => 'justsilver_prisgraf', 'desc' => __( 'Interactive chart of the silver price.', 'justsilver' ), 'attrs' => array( 'dage' => array( 'type' => 'number', 'default' => 90 ) ) ),
			'prisalarm' => array( 'title' => __( 'Price alert', 'justsilver' ), 'icon' => 'bell', 'code' => 'justsilver_prisalarm', 'desc' => __( 'Email me when silver reaches my price.', 'justsilver' ) ),
			'saelg'     => array( 'title' => __( 'Sell your silver', 'justsilver' ), 'icon' => 'money-alt', 'code' => 'justsilver_saelg', 'desc' => __( 'Instant offer for the customer’s silver.', 'justsilver' ) ),
		);
	}

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	public static function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script( 'justsilver-blocks', NS_URL . 'assets/js/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components', 'wp-i18n' ), NS_VERSION, true );
		wp_set_script_translations( 'justsilver-blocks', 'justsilver', NS_DIR . 'languages' );
		$list = array();
		foreach ( self::blocks() as $slug => $b ) {
			$attrs = ( $b['attrs'] ?? array() ) + array(
				'align'     => array( 'type' => 'string' ),
				'className' => array( 'type' => 'string' ),
			);
			register_block_type( 'justsilver/' . $slug, array(
				'api_version'     => 3,
				'editor_script'   => 'justsilver-blocks',
				'attributes'      => $attrs,
				'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
				'render_callback' => function ( $atts ) use ( $b ) {
					$pairs = '';
					foreach ( (array) $atts as $k => $v ) {
						if ( in_array( $k, array( 'align', 'className' ), true ) || is_array( $v ) ) {
							continue;
						}
						$pairs .= ' ' . sanitize_key( $k ) . '="' . esc_attr( (string) $v ) . '"';
					}
					$class = trim( 'wp-block-justsilver ' . ( ! empty( $atts['align'] ) ? 'align' . sanitize_html_class( $atts['align'] ) : '' ) . ' ' . ( $atts['className'] ?? '' ) );
					return '<div class="' . esc_attr( $class ) . '">' . do_shortcode( '[' . $b['code'] . $pairs . ']' ) . '</div>';
				},
			) );
			$list[] = array( 'name' => 'justsilver/' . $slug, 'title' => $b['title'], 'icon' => $b['icon'], 'description' => $b['desc'], 'attributes' => $attrs );
		}
		wp_add_inline_script( 'justsilver-blocks', 'window.JustSilverBlocks = ' . wp_json_encode( $list ) . ';', 'before' );
	}
}
