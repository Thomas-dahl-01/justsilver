<?php
/**
 * WooCommerce → JustSilver: spot price, price API, booking, reservations, reminders and display.
 */
defined( 'ABSPATH' ) || exit;

class NS_Settings {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_ns_set_price', array( __CLASS__, 'set_price' ) );
		add_action( 'admin_post_ns_fetch_price', array( __CLASS__, 'fetch_price' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NS_FILE ), fn( $l ) => array_merge( array( '<a href="' . esc_url( admin_url( 'admin.php?page=justsilver' ) ) . '">' . esc_html__( 'Settings', 'justsilver' ) . '</a>' ), $l ) );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'JustSilver', 'JustSilver', 'manage_woocommerce', 'justsilver', array( __CLASS__, 'page' ) );
	}

	public static function register(): void {
		register_setting( 'ns_settings', 'ns_settings', array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ): array {
		$in  = (array) $in;
		$num = fn( $k, $d ) => (string) max( 0, (float) str_replace( ',', '.', $in[ $k ] ?? $d ) );
		return array(
			'default_premium'    => $num( 'default_premium', 12 ),
			'buyback_margin'     => (string) min( 90, (float) $num( 'buyback_margin', 15 ) ),
			'api_key'            => sanitize_text_field( $in['api_key'] ?? '' ),
			'api_interval'       => in_array( $in['api_interval'] ?? '', array( 'off', 'hourly', 'twicedaily', 'daily' ), true ) ? $in['api_interval'] : 'hourly',
			'booking_page'       => absint( $in['booking_page'] ?? 0 ),
			'silver_page'        => absint( $in['silver_page'] ?? 0 ),
			'silver_category'    => absint( $in['silver_category'] ?? 0 ),
			'notify_email'       => sanitize_email( $in['notify_email'] ?? '' ),
			'phone'              => sanitize_text_field( $in['phone'] ?? '' ),
			'sync_pickup'        => empty( $in['sync_pickup'] ) ? 'no' : 'yes',
			'silver_design'      => empty( $in['silver_design'] ) ? 'no' : 'yes',
			'reserve_items'      => empty( $in['reserve_items'] ) ? 'no' : 'yes',
			'reserve_grace_days' => (string) absint( $in['reserve_grace_days'] ?? 1 ),
			'reminders'          => empty( $in['reminders'] ) ? 'no' : 'yes',
			'reminder_hour'      => (string) min( 23, absint( $in['reminder_hour'] ?? 10 ) ),
			'animations'         => empty( $in['animations'] ) ? 'no' : 'yes',
		);
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$spot    = NS_Silver_Price::per_kg();
		$updated = NS_Silver_Price::updated_at();
		$last    = get_option( 'ns_api_last' );
		$history = array_reverse( array_slice( NS_Silver_Price::history(), -10 ) );
		$o       = fn( $k, $d = '' ) => ns_opt( $k, $d );
		$queued  = function_exists( 'as_get_scheduled_actions' ) ? count( as_get_scheduled_actions( array( 'hook' => 'ns_reprice_batch', 'status' => 'pending' ), 'ids' ) ) : 0;
		$check   = fn( $k, $label, $d = 'yes' ) => '<label><input type="checkbox" name="ns_settings[' . esc_attr( $k ) . ']" value="1" ' . checked( $o( $k, $d ), 'yes', false ) . '> ' . esc_html( $label ) . '</label>';
		?>
		<div class="wrap">
			<h1>JustSilver</h1>
			<?php // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $_GET['ns_msg'] ) ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['ns_msg'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(320px,1fr);gap:24px;align-items:start">
				<div class="card" style="max-width:none">
					<h2><?php esc_html_e( 'Today’s silver price', 'justsilver' ); ?></h2>
					<p style="font-size:28px;margin:8px 0"><strong><?php echo $spot ? esc_html( ns_dkk( $spot ) ) : '–'; ?></strong> <span class="description"><?php esc_html_e( 'per kg fine silver', 'justsilver' ); ?></span></p>
					<p class="description">
						<?php
						/* translators: %s: date and time */
						echo $updated ? esc_html( sprintf( __( 'Updated %s', 'justsilver' ), wp_date( 'j. M Y H:i', $updated ) ) ) : esc_html__( 'Not set yet.', 'justsilver' );
						echo $spot ? esc_html( ' · ' . number_format( $spot / 1000, 2, ',', '.' ) . ' kr/g' ) : '';
						?>
					</p>
					<?php if ( $queued ) : ?><p><em><?php /* translators: %d: jobs */ echo esc_html( sprintf( __( 'Prices are being recalculated in the background (%d jobs queued).', 'justsilver' ), $queued ) ); ?></em></p><?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:center">
						<?php wp_nonce_field( 'ns_set_price' ); ?>
						<input type="hidden" name="action" value="ns_set_price">
						<input type="number" step="0.01" min="1" name="price" placeholder="10500" required style="width:160px"> kr/kg
						<button class="button button-primary"><?php esc_html_e( 'Set manually', 'justsilver' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
						<?php wp_nonce_field( 'ns_fetch_price' ); ?>
						<input type="hidden" name="action" value="ns_fetch_price">
						<button class="button" <?php disabled( ! $o( 'api_key' ) && ! has_filter( 'justsilver_fetch_spot_price' ) ); ?>><?php esc_html_e( 'Fetch price from API now', 'justsilver' ); ?></button>
					</form>
					<?php if ( $last ) : ?>
						<p class="description"><?php /* translators: %s: date and time */ echo esc_html( sprintf( __( 'Last API call (%s):', 'justsilver' ), wp_date( 'j. M H:i', $last['t'] ) ) ); ?> <?php echo $last['ok'] ? '✓ ' : '⚠ '; echo esc_html( $last['msg'] ); ?></p>
					<?php endif; ?>
					<?php if ( $history ) : ?>
						<h3><?php esc_html_e( 'History', 'justsilver' ); ?></h3>
						<table class="widefat striped"><tbody>
						<?php foreach ( $history as $h ) : ?>
							<tr><td><?php echo esc_html( wp_date( 'j. M Y H:i', $h['t'] ) ); ?></td><td><?php echo esc_html( ns_dkk( $h['p'] ) ); ?></td><td><?php echo esc_html( $h['s'] ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					<?php endif; ?>
				</div>

				<form method="post" action="options.php" class="card" style="max-width:none">
					<?php settings_fields( 'ns_settings' ); ?>
					<h2><?php esc_html_e( 'Settings', 'justsilver' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'Default premium over spot', 'justsilver' ); ?></th><td><input type="number" step="0.1" name="ns_settings[default_premium]" value="<?php echo esc_attr( $o( 'default_premium', 12 ) ); ?>" style="width:90px"> %
							<p class="description"><?php esc_html_e( 'Used when a product has no premium of its own.', 'justsilver' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Buy-back margin', 'justsilver' ); ?></th><td><input type="number" step="0.1" name="ns_settings[buyback_margin]" value="<?php echo esc_attr( $o( 'buyback_margin', 15 ) ); ?>" style="width:90px"> %
							<p class="description"><?php esc_html_e( 'The sell calculator offers the spot value minus this margin.', 'justsilver' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'API key (metalpriceapi.com)', 'justsilver' ); ?></th><td><input type="password" class="regular-text" name="ns_settings[api_key]" value="<?php echo esc_attr( $o( 'api_key' ) ); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Empty = manual price only.', 'justsilver' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Update price', 'justsilver' ); ?></th><td><select name="ns_settings[api_interval]">
							<?php foreach ( array( 'hourly' => __( 'Every hour', 'justsilver' ), 'twicedaily' => __( 'Twice a day', 'justsilver' ), 'daily' => __( 'Daily', 'justsilver' ), 'off' => __( 'Never (manual only)', 'justsilver' ) ) as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o( 'api_interval', 'hourly' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?></select></td></tr>
						<tr><th><?php esc_html_e( 'Silver category', 'justsilver' ); ?></th><td><?php wp_dropdown_categories( array( 'taxonomy' => 'product_cat', 'name' => 'ns_settings[silver_category]', 'selected' => (int) $o( 'silver_category' ), 'hide_empty' => false, 'hierarchical' => true, 'show_option_none' => __( '– choose –', 'justsilver' ), 'option_none_value' => 0 ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Silver front page', 'justsilver' ); ?></th><td><?php wp_dropdown_pages( array( 'name' => 'ns_settings[silver_page]', 'selected' => (int) $o( 'silver_page' ), 'show_option_none' => __( '– choose –', 'justsilver' ), 'option_none_value' => 0 ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Booking page', 'justsilver' ); ?></th><td><?php wp_dropdown_pages( array( 'name' => 'ns_settings[booking_page]', 'selected' => (int) $o( 'booking_page' ), 'show_option_none' => __( '– choose –', 'justsilver' ), 'option_none_value' => 0 ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Send bookings to', 'justsilver' ); ?></th><td><input type="email" class="regular-text" name="ns_settings[notify_email]" value="<?php echo esc_attr( $o( 'notify_email' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Used when the store has no email of its own.', 'justsilver' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Reservations', 'justsilver' ); ?></th><td><?php echo $check( 'reserve_items', __( 'Reserve the items a customer books to see', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<p><?php esc_html_e( 'Release after', 'justsilver' ); ?> <input type="number" min="0" name="ns_settings[reserve_grace_days]" value="<?php echo esc_attr( $o( 'reserve_grace_days', 1 ) ); ?>" style="width:60px"> <?php esc_html_e( 'days if the visit is not marked completed.', 'justsilver' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Reminders', 'justsilver' ); ?></th><td><?php echo $check( 'reminders', __( 'Email customers the day before their visit', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<p><?php esc_html_e( 'Send from', 'justsilver' ); ?> <input type="number" min="0" max="23" name="ns_settings[reminder_hour]" value="<?php echo esc_attr( $o( 'reminder_hour', 10 ) ); ?>" style="width:60px"> <?php esc_html_e( 'o’clock', 'justsilver' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Pickup', 'justsilver' ); ?></th><td><?php echo $check( 'sync_pickup', __( 'Use the stores as pickup locations in checkout', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td></tr>
						<tr><th><?php esc_html_e( 'Design', 'justsilver' ); ?></th><td><?php echo $check( 'silver_design', __( 'Use the silver design on silver categories and products', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?><br>
							<?php echo $check( 'animations', __( 'Animations (respects visitors who prefer reduced motion)', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td></tr>
					</table>
					<?php submit_button( __( 'Save settings', 'justsilver' ) ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	public static function set_price(): void {
		check_admin_referer( 'ns_set_price' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No access', 'justsilver' ) );
		}
		$price = (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['price'] ?? '0' ) ) );
		$ok    = NS_Silver_Price::set( $price, 'manual (' . wp_get_current_user()->user_login . ')' );
		/* translators: %s: price per kg */
		self::back( $ok ? sprintf( __( 'The silver price is now %s/kg. All silver products are being recalculated in the background.', 'justsilver' ), ns_dkk( $price ) ) : __( 'Invalid price.', 'justsilver' ) );
	}

	public static function fetch_price(): void {
		check_admin_referer( 'ns_fetch_price' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No access', 'justsilver' ) );
		}
		self::back( NS_Price_API::run()['message'] );
	}

	private static function back( string $msg ): void {
		wp_safe_redirect( add_query_arg( 'ns_msg', rawurlencode( $msg ), admin_url( 'admin.php?page=justsilver' ) ) );
		exit;
	}
}
