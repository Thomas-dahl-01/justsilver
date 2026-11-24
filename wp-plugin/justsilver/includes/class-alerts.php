<?php
/**
 * Price alerts: the customer gets an email when the silver price drops to (or below) a chosen price per gram.
 * Shortcode / block: [justsilver_prisalarm]
 */
defined( 'ABSPATH' ) || exit;

class NS_Alerts {

	const CPT = 'ns_alert';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_shortcode( 'justsilver_prisalarm', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
		add_action( 'justsilver_price_updated', array( __CLASS__, 'check' ), 20, 1 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	public static function register(): void {
		register_post_type( self::CPT, array(
			'labels'          => array(
				'name'          => __( 'Price alerts', 'justsilver' ),
				'singular_name' => __( 'Price alert', 'justsilver' ),
				'menu_name'     => __( 'Price alerts', 'justsilver' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=ns_booking',
			'supports'        => array( 'title' ),
			'capability_type' => 'page',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		) );
	}

	public static function token( int $id ): string {
		return substr( wp_hash( 'ns_alert' . $id ), 0, 16 );
	}

	/** Which alerts should fire at this price per gram (pure, unit tested). */
	public static function due( array $alerts, float $per_gram ): array {
		return array_values( array_filter( $alerts, fn( $a ) => $per_gram > 0 && $per_gram <= (float) $a['target'] ) );
	}

	public static function handle(): void {
		// Unsubscribe link (idempotent).
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['ns_alarm_stop'], $_GET['k'] ) ) {
			$id = absint( $_GET['ns_alarm_stop'] );
			if ( self::CPT === get_post_type( $id ) && hash_equals( self::token( $id ), sanitize_text_field( wp_unslash( $_GET['k'] ) ) ) ) {
				wp_delete_post( $id, true );
				wc_add_notice( __( 'Your price alert has been removed.', 'justsilver' ), 'success' );
			}
			wp_safe_redirect( remove_query_arg( array( 'ns_alarm_stop', 'k' ) ) );
			exit;
		}
		// phpcs:enable
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['ns_alert_submit'] ) ) {
			return;
		}
		if ( ! isset( $_POST['ns_alert_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ns_alert_nonce'] ), 'ns_alert' ) || ! empty( $_POST['ns_website'] ) ) {
			return;
		}
		$email   = sanitize_email( wp_unslash( $_POST['ns_alert_email'] ?? '' ) );
		$target  = (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['ns_alert_target'] ?? '0' ) ) );
		$consent = ! empty( $_POST['ns_alert_consent'] );
		$back    = wp_get_referer() ?: home_url( '/' );
		if ( ! is_email( $email ) || $target <= 0 || ! $consent ) {
			wc_add_notice( __( 'Please enter a valid email and price, and accept that we may email you.', 'justsilver' ), 'error' );
			wp_safe_redirect( $back . '#ns-prisalarm' );
			exit;
		}
		$existing = get_posts( array( 'post_type' => self::CPT, 'fields' => 'ids', 'posts_per_page' => 10, 'meta_query' => array( array( 'key' => '_ns_email', 'value' => $email ) ) ) );
		if ( count( $existing ) >= 5 ) {
			wc_add_notice( __( 'You already have 5 price alerts. Remove one before adding another.', 'justsilver' ), 'error' );
			wp_safe_redirect( $back . '#ns-prisalarm' );
			exit;
		}
		wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			/* translators: 1: email, 2: price per gram */
			'post_title'  => sprintf( __( '%1$s – %2$s kr/g', 'justsilver' ), $email, number_format( $target, 2, ',', '.' ) ),
			'meta_input'  => array( '_ns_email' => $email, '_ns_target' => $target ),
		) );
		/* translators: %s: price per gram */
		wc_add_notice( sprintf( __( 'Done! We will email you when silver is %s kr/g or lower.', 'justsilver' ), number_format( $target, 2, ',', '.' ) ), 'success' );
		wp_safe_redirect( $back . '#ns-prisalarm' );
		exit;
	}

	/** After every price update: send due alerts and remove them (one-shot). */
	public static function check( float $per_kg ): int {
		$per_gram = $per_kg / 1000;
		$posts    = get_posts( array( 'post_type' => self::CPT, 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_ns_target', 'value' => $per_gram, 'type' => 'DECIMAL(10,2)', 'compare' => '>=' ) ) ) );
		$alerts   = array_map( fn( $id ) => array( 'id' => $id, 'email' => get_post_meta( $id, '_ns_email', true ), 'target' => (float) get_post_meta( $id, '_ns_target', true ) ), $posts );
		$sent     = 0;
		foreach ( self::due( $alerts, $per_gram ) as $a ) {
			self::send( $a, $per_gram );
			wp_delete_post( $a['id'], true );
			$sent++;
		}
		return $sent;
	}

	private static function send( array $a, float $per_gram ): void {
		$mailer = WC()->mailer();
		$shop   = NS_Frontend::silver_url();
		/* translators: 1: current price per gram, 2: target price per gram */
		$body = '<p>' . esc_html( sprintf( __( 'Silver is now %1$s kr/g – at or below your alert of %2$s kr/g.', 'justsilver' ), number_format( $per_gram, 2, ',', '.' ), number_format( $a['target'], 2, ',', '.' ) ) ) . '</p>'
			. '<p><a href="' . esc_url( $shop ) . '">' . esc_html__( 'See silver at today’s price', 'justsilver' ) . '</a></p>'
			. '<p style="font-size:12px;color:#777">' . esc_html__( 'This alert has now been used and removed. You can set a new one at any time.', 'justsilver' ) . '</p>';
		$mailer->send( $a['email'], __( 'Price alert: silver has reached your price', 'justsilver' ), $mailer->wrap_message( __( 'Your silver price alert', 'justsilver' ), $body ) );
	}

	public static function shortcode(): string {
		NS_Frontend::enqueue();
		ob_start();
		include ns_template( 'price-alert.php' );
		return ob_get_clean();
	}

	public static function columns( array $cols ): array {
		return array( 'cb' => $cols['cb'], 'title' => __( 'Alert', 'justsilver' ), 'ns_target' => __( 'Target price', 'justsilver' ), 'date' => $cols['date'] );
	}

	public static function column( string $col, int $id ): void {
		if ( 'ns_target' === $col ) {
			echo esc_html( number_format( (float) get_post_meta( $id, '_ns_target', true ), 2, ',', '.' ) . ' kr/g' );
		}
	}
}
