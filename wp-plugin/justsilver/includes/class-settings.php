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
			'brand_login'        => empty( $in['brand_login'] ) ? 'no' : 'yes',
		);
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$spot    = NS_Silver_Price::per_kg();
		$updated = NS_Silver_Price::updated_at();
		$change  = NS_Silver_Price::change();
		$last    = get_option( 'ns_api_last' );
		$history = array_reverse( array_slice( NS_Silver_Price::history(), -8 ) );
		$series  = NS_Chart::daily( NS_Silver_Price::history(), 90, time(), wp_timezone() );
		$o       = fn( $k, $d = '' ) => ns_opt( $k, $d );
		$queued  = function_exists( 'as_get_scheduled_actions' ) ? count( as_get_scheduled_actions( array( 'hook' => 'ns_reprice_batch', 'status' => 'pending' ), 'ids' ) ) : 0;
		$toggle  = fn( $k, $label, $desc = '', $d = 'yes' ) => '<label class="ns-toggle"><input type="checkbox" name="ns_settings[' . esc_attr( $k ) . ']" value="1" ' . checked( $o( $k, $d ), 'yes', false ) . '><span class="ns-toggle__ui" aria-hidden="true"></span><span class="ns-toggle__text"><b>' . esc_html( $label ) . '</b>' . ( $desc ? '<small>' . esc_html( $desc ) . '</small>' : '' ) . '</span></label>';
		$icon    = fn( $path ) => '<span class="ns-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24">' . $path . '</svg></span>';
		?>
		<div class="wrap ns-admin ns-settings">
			<h1 class="ns-settings__title"><span class="ns-logo" aria-hidden="true"></span>JustSilver <small><?php esc_html_e( 'Settings', 'justsilver' ); ?></small></h1>
			<?php // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $_GET['ns_msg'] ) ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['ns_msg'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
			<?php endif; ?>

			<section class="ns-hero-card">
				<div class="ns-hero-card__price">
					<span class="ns-label"><span class="ns-dot"></span><?php esc_html_e( 'Today’s silver price', 'justsilver' ); ?></span>
					<strong><?php echo $spot ? esc_html( number_format( $spot / 1000, 2, ',', '.' ) ) : '–'; ?> <small>kr/g</small></strong>
					<div class="ns-hero-card__meta">
						<?php if ( $change ) : $up = $change['pct'] >= 0; ?>
							<span class="ns-chip ns-chip--<?php echo $up ? 'up' : 'down'; ?>"><?php echo $up ? '▲' : '▼'; ?> <?php echo esc_html( number_format( abs( $change['pct'] ), 2, ',', '.' ) ); ?>% <?php esc_html_e( 'since yesterday', 'justsilver' ); ?></span>
						<?php endif; ?>
						<span><?php echo $spot ? esc_html( ns_dkk( $spot ) . ' / kg' ) : ''; ?></span>
						<span>
							<?php
							/* translators: %s: date and time */
							echo $updated ? esc_html( sprintf( __( 'Updated %s', 'justsilver' ), wp_date( 'j. M Y H:i', $updated ) ) ) : esc_html__( 'Not set yet.', 'justsilver' );
							?>
						</span>
					</div>
					<?php if ( $queued ) : ?><p class="ns-queued"><span class="spinner is-active"></span><?php /* translators: %d: jobs */ echo esc_html( sprintf( __( 'Prices are being recalculated in the background (%d jobs queued).', 'justsilver' ), $queued ) ); ?></p><?php endif; ?>
				</div>
				<div class="ns-hero-card__chart">
					<?php echo NS_Admin::sparkline( array_column( $series, 'v' ), 520, 110, 'nsSetSpark' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="ns-sub"><?php /* translators: %d: days */ echo esc_html( sprintf( __( 'Last %d days', 'justsilver' ), 90 ) ); ?></span>
				</div>
				<div class="ns-hero-card__actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ns-inline">
						<?php wp_nonce_field( 'ns_set_price' ); ?>
						<input type="hidden" name="action" value="ns_set_price">
						<label class="screen-reader-text" for="ns_price"><?php esc_html_e( 'Set manually', 'justsilver' ); ?></label>
						<input id="ns_price" type="number" step="0.01" min="1" name="price" placeholder="10500" required> <span>kr/kg</span>
						<button class="button button-primary"><?php esc_html_e( 'Set manually', 'justsilver' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'ns_fetch_price' ); ?>
						<input type="hidden" name="action" value="ns_fetch_price">
						<button class="button" <?php disabled( ! $o( 'api_key' ) && ! has_filter( 'justsilver_fetch_spot_price' ) ); ?>>↻ <?php esc_html_e( 'Fetch price from API now', 'justsilver' ); ?></button>
					</form>
					<?php if ( $last ) : ?>
						<p class="ns-sub"><?php /* translators: %s: date and time */ echo esc_html( sprintf( __( 'Last API call (%s):', 'justsilver' ), wp_date( 'j. M H:i', $last['t'] ) ) ); ?> <?php echo $last['ok'] ? '✓ ' : '⚠ '; echo esc_html( $last['msg'] ); ?></p>
					<?php endif; ?>
					<?php if ( $history ) : ?>
						<details class="ns-history"><summary><?php esc_html_e( 'History', 'justsilver' ); ?></summary>
							<table class="widefat striped"><tbody>
							<?php foreach ( $history as $h ) : ?>
								<tr><td><?php echo esc_html( wp_date( 'j. M Y H:i', $h['t'] ) ); ?></td><td><?php echo esc_html( ns_dkk( $h['p'] ) ); ?></td><td><?php echo esc_html( $h['s'] ); ?></td></tr>
							<?php endforeach; ?>
							</tbody></table>
						</details>
					<?php endif; ?>
				</div>
			</section>

			<form method="post" action="options.php" class="ns-cards">
				<?php settings_fields( 'ns_settings' ); ?>

				<section class="ns-card">
					<?php echo $icon( '<path d="M12 3v18M17 7H9.5a3 3 0 0 0 0 6h5a3 3 0 0 1 0 6H6"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2><?php esc_html_e( 'Prices', 'justsilver' ); ?></h2>
					<p class="ns-sub"><?php esc_html_e( 'How products and buy-back offers are priced from the spot price.', 'justsilver' ); ?></p>
					<div class="ns-field"><label for="ns_premium"><?php esc_html_e( 'Default premium over spot', 'justsilver' ); ?></label>
						<span class="ns-affix"><input id="ns_premium" type="number" step="0.1" name="ns_settings[default_premium]" value="<?php echo esc_attr( $o( 'default_premium', 12 ) ); ?>"><i>%</i></span>
						<small><?php esc_html_e( 'Used when a product has no premium of its own.', 'justsilver' ); ?></small></div>
					<div class="ns-field"><label for="ns_margin"><?php esc_html_e( 'Buy-back margin', 'justsilver' ); ?></label>
						<span class="ns-affix"><input id="ns_margin" type="number" step="0.1" name="ns_settings[buyback_margin]" value="<?php echo esc_attr( $o( 'buyback_margin', 15 ) ); ?>"><i>%</i></span>
						<small><?php esc_html_e( 'The sell calculator offers the spot value minus this margin.', 'justsilver' ); ?></small></div>
				</section>

				<section class="ns-card">
					<?php echo $icon( '<path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2><?php esc_html_e( 'Automatic price', 'justsilver' ); ?></h2>
					<p class="ns-sub"><?php esc_html_e( 'Fetch the silver price from metalpriceapi.com on a schedule.', 'justsilver' ); ?></p>
					<div class="ns-field"><label for="ns_api"><?php esc_html_e( 'API key (metalpriceapi.com)', 'justsilver' ); ?></label>
						<input id="ns_api" type="password" class="regular-text" name="ns_settings[api_key]" value="<?php echo esc_attr( $o( 'api_key' ) ); ?>" autocomplete="off">
						<small><?php esc_html_e( 'Empty = manual price only.', 'justsilver' ); ?></small></div>
					<div class="ns-field"><label for="ns_interval"><?php esc_html_e( 'Update price', 'justsilver' ); ?></label>
						<select id="ns_interval" name="ns_settings[api_interval]">
							<?php foreach ( array( 'hourly' => __( 'Every hour', 'justsilver' ), 'twicedaily' => __( 'Twice a day', 'justsilver' ), 'daily' => __( 'Daily', 'justsilver' ), 'off' => __( 'Never (manual only)', 'justsilver' ) ) as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o( 'api_interval', 'hourly' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?></select></div>
				</section>

				<section class="ns-card">
					<?php echo $icon( '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2><?php esc_html_e( 'Booking', 'justsilver' ); ?></h2>
					<p class="ns-sub"><?php esc_html_e( 'Reservations, reminders and who receives new bookings.', 'justsilver' ); ?></p>
					<?php echo $toggle( 'reserve_items', __( 'Reserve the items a customer books to see', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div class="ns-field ns-field--inline"><label for="ns_grace"><?php esc_html_e( 'Release after', 'justsilver' ); ?></label>
						<input id="ns_grace" type="number" min="0" name="ns_settings[reserve_grace_days]" value="<?php echo esc_attr( $o( 'reserve_grace_days', 1 ) ); ?>"> <small><?php esc_html_e( 'days if the visit is not marked completed.', 'justsilver' ); ?></small></div>
					<?php echo $toggle( 'reminders', __( 'Email customers the day before their visit', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div class="ns-field ns-field--inline"><label for="ns_hour"><?php esc_html_e( 'Send from', 'justsilver' ); ?></label>
						<input id="ns_hour" type="number" min="0" max="23" name="ns_settings[reminder_hour]" value="<?php echo esc_attr( $o( 'reminder_hour', 10 ) ); ?>"> <small><?php esc_html_e( 'o’clock', 'justsilver' ); ?></small></div>
					<div class="ns-field"><label for="ns_notify"><?php esc_html_e( 'Send bookings to', 'justsilver' ); ?></label>
						<input id="ns_notify" type="email" class="regular-text" name="ns_settings[notify_email]" value="<?php echo esc_attr( $o( 'notify_email' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<small><?php esc_html_e( 'Used when the store has no email of its own.', 'justsilver' ); ?></small></div>
				</section>

				<section class="ns-card">
					<?php echo $icon( '<path d="M3 9 12 3l9 6v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-7h6v7"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2><?php esc_html_e( 'Pages & shop', 'justsilver' ); ?></h2>
					<p class="ns-sub"><?php esc_html_e( 'Where the silver shop lives on your site.', 'justsilver' ); ?></p>
					<div class="ns-field"><label><?php esc_html_e( 'Silver category', 'justsilver' ); ?></label><?php wp_dropdown_categories( array( 'taxonomy' => 'product_cat', 'name' => 'ns_settings[silver_category]', 'selected' => (int) $o( 'silver_category' ), 'hide_empty' => false, 'hierarchical' => true, 'show_option_none' => __( '– choose –', 'justsilver' ), 'option_none_value' => 0 ) ); ?></div>
					<div class="ns-field"><label><?php esc_html_e( 'Silver front page', 'justsilver' ); ?></label><?php wp_dropdown_pages( array( 'name' => 'ns_settings[silver_page]', 'selected' => (int) $o( 'silver_page' ), 'show_option_none' => __( '– choose –', 'justsilver' ), 'option_none_value' => 0 ) ); ?></div>
					<div class="ns-field"><label><?php esc_html_e( 'Booking page', 'justsilver' ); ?></label><?php wp_dropdown_pages( array( 'name' => 'ns_settings[booking_page]', 'selected' => (int) $o( 'booking_page' ), 'show_option_none' => __( '– choose –', 'justsilver' ), 'option_none_value' => 0 ) ); ?></div>
					<?php echo $toggle( 'sync_pickup', __( 'Use the stores as pickup locations in checkout', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</section>

				<section class="ns-card">
					<?php echo $icon( '<path d="M12 3l2.4 5.6L20 9.3l-4.3 3.9 1.2 5.8L12 16l-4.9 3 1.2-5.8L4 9.3l5.6-.7z"/>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2><?php esc_html_e( 'Design', 'justsilver' ); ?></h2>
					<p class="ns-sub"><?php esc_html_e( 'Look and feel for customers and staff.', 'justsilver' ); ?></p>
					<?php echo $toggle( 'silver_design', __( 'Use the silver design on silver categories and products', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php echo $toggle( 'animations', __( 'Animations (respects visitors who prefer reduced motion)', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php echo $toggle( 'brand_login', __( 'JustSilver design on the login page', 'justsilver' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</section>

				<div class="ns-cards__save"><?php submit_button( __( 'Save settings', 'justsilver' ), 'primary large', 'submit', false ); ?></div>
			</form>
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
