<?php
/**
 * Automatic silver spot price (XAG/DKK) via WP-Cron.
 *
 * Built-in provider: metalpriceapi.com (needs an API key).
 * Other providers: filter "justsilver_fetch_spot_price" returning DKK per troy ounce (float) or null.
 */
defined( 'ABSPATH' ) || exit;

class NS_Price_API {

	const HOOK      = 'ns_fetch_silver_price';
	const OZ_PER_KG = 32.1507466;
	const MAX_JUMP  = 0.20; // reject prices that move more than 20 % at once (probably bad data)

	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'update_option_ns_settings', array( __CLASS__, 'reschedule' ) );
		add_action( 'add_option_ns_settings', array( __CLASS__, 'reschedule' ) );
	}

	public static function reschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		$interval = ns_opt( 'api_interval', 'hourly' );
		if ( 'off' !== $interval && ( ns_opt( 'api_key' ) || has_filter( 'justsilver_fetch_spot_price' ) ) ) {
			wp_schedule_event( time() + 60, $interval, self::HOOK );
		}
	}

	/** @return array{ok:bool,message:string,price?:float} */
	public static function run( bool $force = false ): array {
		$per_oz = apply_filters( 'justsilver_fetch_spot_price', null );
		if ( null === $per_oz ) {
			$per_oz = self::fetch_metalpriceapi();
		}
		if ( is_wp_error( $per_oz ) ) {
			return self::log( false, $per_oz->get_error_message() );
		}
		$per_kg = (float) $per_oz * self::OZ_PER_KG;
		if ( $per_kg <= 0 ) {
			return self::log( false, __( 'Invalid price received.', 'justsilver' ) );
		}
		$old = NS_Silver_Price::per_kg();
		if ( ! $force && $old > 0 && abs( $per_kg - $old ) / $old > self::MAX_JUMP ) {
			/* translators: 1: new price per kg, 2: current price per kg */
			return self::log( false, sprintf( __( 'The price %1$s/kg differed more than 20%% from %2$s/kg and was not used. Check it and set it manually.', 'justsilver' ), ns_dkk( $per_kg ), ns_dkk( $old ) ) );
		}
		NS_Silver_Price::set( $per_kg, 'api' );
		/* translators: %s: price per kg */
		return self::log( true, sprintf( __( 'Silver price updated: %s per kg', 'justsilver' ), ns_dkk( $per_kg ) ), $per_kg );
	}

	/** @return float|WP_Error DKK per troy ounce */
	public static function fetch_metalpriceapi() {
		$key = ns_opt( 'api_key' );
		if ( ! $key ) {
			return new WP_Error( 'ns_no_key', __( 'No API key has been set up.', 'justsilver' ) );
		}
		$url = add_query_arg( array( 'api_key' => $key, 'base' => 'DKK', 'currencies' => 'XAG' ), 'https://api.metalpriceapi.com/v1/latest' );
		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $res ) || empty( $body['success'] ) ) {
			$msg = $body['error']['info'] ?? $body['error']['message'] ?? ( 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
			/* translators: %s: error from the price API */
			return new WP_Error( 'ns_api', sprintf( __( 'Price API failed: %s', 'justsilver' ), $msg ) );
		}
		return self::parse_rates( (array) ( $body['rates'] ?? array() ) );
	}

	/** @return float|WP_Error DKK per troy ounce from a metalpriceapi "rates" object. */
	public static function parse_rates( array $rates ) {
		if ( ! empty( $rates['DKKXAG'] ) ) {
			return (float) $rates['DKKXAG'];
		}
		if ( ! empty( $rates['XAG'] ) ) {
			return 1 / (float) $rates['XAG'];
		}
		return new WP_Error( 'ns_api', __( 'The price API answered without an XAG rate.', 'justsilver' ) );
	}

	private static function log( bool $ok, string $message, float $price = 0 ): array {
		update_option( 'ns_api_last', array( 't' => time(), 'ok' => $ok, 'msg' => $message ), false );
		if ( ! $ok && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( $message, array( 'source' => 'justsilver' ) );
		}
		return array( 'ok' => $ok, 'message' => $message, 'price' => $price );
	}
}
