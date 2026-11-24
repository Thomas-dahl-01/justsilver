<?php
/**
 * WooCommerce → JustSilver: dagspris, pris-API, booking og visning.
 */
defined( 'ABSPATH' ) || exit;

class NS_Settings {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_ns_set_price', array( __CLASS__, 'set_price' ) );
		add_action( 'admin_post_ns_fetch_price', array( __CLASS__, 'fetch_price' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NS_FILE ), fn( $l ) => array_merge( array( '<a href="' . esc_url( admin_url( 'admin.php?page=justsilver' ) ) . '">Indstillinger</a>' ), $l ) );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'JustSilver', 'JustSilver', 'manage_woocommerce', 'justsilver', array( __CLASS__, 'page' ) );
	}

	public static function register(): void {
		register_setting( 'ns_settings', 'ns_settings', array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ): array {
		$in = (array) $in;
		return array(
			'default_premium' => (string) max( 0, (float) str_replace( ',', '.', $in['default_premium'] ?? 12 ) ),
			'api_key'         => sanitize_text_field( $in['api_key'] ?? '' ),
			'api_interval'    => in_array( $in['api_interval'] ?? '', array( 'off', 'hourly', 'twicedaily', 'daily' ), true ) ? $in['api_interval'] : 'hourly',
			'booking_page'    => absint( $in['booking_page'] ?? 0 ),
			'silver_page'     => absint( $in['silver_page'] ?? 0 ),
			'silver_category' => absint( $in['silver_category'] ?? 0 ),
			'notify_email'    => sanitize_email( $in['notify_email'] ?? '' ),
			'phone'           => sanitize_text_field( $in['phone'] ?? '' ),
			'sync_pickup'     => empty( $in['sync_pickup'] ) ? 'no' : 'yes',
			'silver_design'   => empty( $in['silver_design'] ) ? 'no' : 'yes',
		);
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$spot    = NS_Silver_Price::per_kg();
		$updated = NS_Silver_Price::updated_at();
		$last    = get_option( 'ns_api_last' );
		$history = array_reverse( array_slice( (array) get_option( 'ns_price_history', array() ), -10 ) );
		$o       = fn( $k, $d = '' ) => ns_opt( $k, $d );
		$queued  = function_exists( 'as_get_scheduled_actions' ) ? count( as_get_scheduled_actions( array( 'hook' => 'ns_reprice_batch', 'status' => 'pending' ), 'ids' ) ) : 0;
		?>
		<div class="wrap">
			<h1>JustSilver</h1>
			<?php // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $_GET['ns_msg'] ) ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['ns_msg'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(320px,1fr);gap:24px;align-items:start">
				<div class="card" style="max-width:none">
					<h2>Dagens sølvpris</h2>
					<p style="font-size:28px;margin:8px 0"><strong><?php echo $spot ? esc_html( ns_dkk( $spot ) ) : '–'; ?></strong> <span class="description">pr. kg rent sølv</span></p>
					<p class="description"><?php echo $updated ? esc_html( 'Opdateret ' . wp_date( 'j. M Y H:i', $updated ) ) : 'Ikke sat endnu.'; ?>
						<?php echo $spot ? esc_html( ' · ' . number_format( $spot / 1000, 2, ',', '.' ) . ' kr/g' ) : ''; ?></p>
					<?php if ( $queued ) : ?><p><em>Priserne genberegnes i baggrunden (<?php echo (int) $queued; ?> job i kø).</em></p><?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:center">
						<?php wp_nonce_field( 'ns_set_price' ); ?>
						<input type="hidden" name="action" value="ns_set_price">
						<input type="number" step="0.01" min="1" name="price" placeholder="fx 10500" required style="width:160px"> kr/kg
						<button class="button button-primary">Sæt manuelt</button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
						<?php wp_nonce_field( 'ns_fetch_price' ); ?>
						<input type="hidden" name="action" value="ns_fetch_price">
						<button class="button" <?php disabled( ! $o( 'api_key' ) && ! has_filter( 'justsilver_fetch_spot_price' ) ); ?>>Hent kurs fra API nu</button>
					</form>
					<?php if ( $last ) : ?>
						<p class="description">Seneste API-kald (<?php echo esc_html( wp_date( 'j. M H:i', $last['t'] ) ); ?>): <?php echo $last['ok'] ? '✓ ' : '⚠ '; echo esc_html( $last['msg'] ); ?></p>
					<?php endif; ?>
					<?php if ( $history ) : ?>
						<h3>Historik</h3>
						<table class="widefat striped"><tbody>
						<?php foreach ( $history as $h ) : ?>
							<tr><td><?php echo esc_html( wp_date( 'j. M Y H:i', $h['t'] ) ); ?></td><td><?php echo esc_html( ns_dkk( $h['p'] ) ); ?></td><td><?php echo esc_html( $h['s'] ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					<?php endif; ?>
				</div>

				<form method="post" action="options.php" class="card" style="max-width:none">
					<?php settings_fields( 'ns_settings' ); ?>
					<h2>Indstillinger</h2>
					<table class="form-table" role="presentation">
						<tr><th>Standardtillæg over dagspris</th><td><input type="number" step="0.1" name="ns_settings[default_premium]" value="<?php echo esc_attr( $o( 'default_premium', 12 ) ); ?>" style="width:90px"> %
							<p class="description">Bruges når en vare ikke har sit eget tillæg.</p></td></tr>
						<tr><th>API-nøgle (metalpriceapi.com)</th><td><input type="password" class="regular-text" name="ns_settings[api_key]" value="<?php echo esc_attr( $o( 'api_key' ) ); ?>" autocomplete="off">
							<p class="description">Opret en nøgle på metalpriceapi.com. Tom = kun manuel pris.</p></td></tr>
						<tr><th>Opdater kurs</th><td><select name="ns_settings[api_interval]">
							<?php foreach ( array( 'hourly' => 'Hver time', 'twicedaily' => 'To gange dagligt', 'daily' => 'Dagligt', 'off' => 'Aldrig (kun manuelt)' ) as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o( 'api_interval', 'hourly' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?></select></td></tr>
						<tr><th>Sølv-kategori</th><td><?php wp_dropdown_categories( array( 'taxonomy' => 'product_cat', 'name' => 'ns_settings[silver_category]', 'selected' => (int) $o( 'silver_category' ), 'hide_empty' => false, 'hierarchical' => true, 'show_option_none' => '– vælg –', 'option_none_value' => 0 ) ); ?></td></tr>
						<tr><th>Sølv-forside</th><td><?php wp_dropdown_pages( array( 'name' => 'ns_settings[silver_page]', 'selected' => (int) $o( 'silver_page' ), 'show_option_none' => '– vælg –', 'option_none_value' => 0 ) ); ?>
							<p class="description">Side med <code>[justsilver_forside]</code>.</p></td></tr>
						<tr><th>Booking-side</th><td><?php wp_dropdown_pages( array( 'name' => 'ns_settings[booking_page]', 'selected' => (int) $o( 'booking_page' ), 'show_option_none' => '– vælg –', 'option_none_value' => 0 ) ); ?>
							<p class="description">Side med <code>[justsilver_booking]</code>.</p></td></tr>
						<tr><th>Bookinger sendes til</th><td><input type="email" class="regular-text" name="ns_settings[notify_email]" value="<?php echo esc_attr( $o( 'notify_email' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<p class="description">Bruges hvis butikken ikke selv har en e-mail.</p></td></tr>
						<tr><th>Telefonnummer</th><td><input class="regular-text" name="ns_settings[phone]" value="<?php echo esc_attr( $o( 'phone' ) ); ?>"></td></tr>
						<tr><th>Afhentning</th><td><label><input type="checkbox" name="ns_settings[sync_pickup]" value="1" <?php checked( $o( 'sync_pickup', 'yes' ), 'yes' ); ?>> Brug butikkerne som afhentningssteder i checkout</label></td></tr>
						<tr><th>Design</th><td><label><input type="checkbox" name="ns_settings[silver_design]" value="1" <?php checked( $o( 'silver_design', 'yes' ), 'yes' ); ?>> Brug sølv-designet på sølvkategorier og -varer</label></td></tr>
					</table>
					<?php submit_button( 'Gem indstillinger' ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	public static function set_price(): void {
		check_admin_referer( 'ns_set_price' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Ingen adgang' );
		}
		$price = (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['price'] ?? '0' ) ) );
		$ok    = NS_Silver_Price::set( $price, 'manuel (' . wp_get_current_user()->user_login . ')' );
		self::back( $ok ? 'Sølvprisen er sat til ' . ns_dkk( $price ) . '/kg. Alle sølvvarer genberegnes nu i baggrunden.' : 'Ugyldig pris.' );
	}

	public static function fetch_price(): void {
		check_admin_referer( 'ns_fetch_price' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Ingen adgang' );
		}
		$r = NS_Price_API::run();
		self::back( $r['message'] );
	}

	private static function back( string $msg ): void {
		wp_safe_redirect( add_query_arg( 'ns_msg', rawurlencode( $msg ), admin_url( 'admin.php?page=justsilver' ) ) );
		exit;
	}
}
