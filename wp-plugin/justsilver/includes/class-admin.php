<?php
/**
 * Admin experience: dashboard overview, booking calendar, branded login page and admin styles.
 * Everything uses WordPress' own building blocks (dashboard widgets, admin pages, list tables)
 * so it still feels like WordPress – just with a JustSilver touch.
 */
defined( 'ABSPATH' ) || exit;

class NS_Admin {

	public static function init(): void {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_setup' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 70 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_style' ) );
		add_filter( 'login_headerurl', fn( $url ) => 'yes' === ns_opt( 'brand_login', 'yes' ) ? home_url( '/' ) : $url );
		add_filter( 'login_headertext', fn( $text ) => 'yes' === ns_opt( 'brand_login', 'yes' ) ? get_bloginfo( 'name' ) : $text );
		add_filter( 'views_edit-' . NS_Bookings::CPT, array( __CLASS__, 'calendar_link' ) );
	}

	public static function enqueue( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$ours   = in_array( $hook, array( 'index.php', 'woocommerce_page_justsilver' ), true )
			|| ( $screen && in_array( $screen->post_type, array( NS_Bookings::CPT, NS_Stores::CPT, NS_Alerts::CPT ), true ) )
			|| str_contains( $hook, 'ns-calendar' );
		if ( $ours ) {
			wp_enqueue_style( 'justsilver-admin', NS_URL . 'assets/css/admin.css', array(), NS_VERSION . '.' . filemtime( NS_DIR . 'assets/css/admin.css' ) );
		}
	}

	/** Small inline SVG sparkline (pure markup, no JavaScript). */
	public static function sparkline( array $values, int $w = 280, int $h = 64, string $id = 'nsSpark' ): string {
		if ( count( $values ) < 2 ) {
			return '';
		}
		$g = NS_Chart::geometry( $values, $w, $h, 4 );
		return '<svg class="ns-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
			. '<defs><linearGradient id="' . esc_attr( $id ) . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#818cf8" stop-opacity=".35"/><stop offset="1" stop-color="#818cf8" stop-opacity="0"/></linearGradient></defs>'
			. '<path d="' . esc_attr( $g['area'] ) . '" fill="url(#' . esc_attr( $id ) . ')"/>'
			. '<path d="' . esc_attr( $g['line'] ) . '" fill="none" stroke="#3d6a8f" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>';
	}

	/* ================================================================ Dashboard */

	public static function dashboard_setup(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'ns_dashboard', __( 'JustSilver – overview', 'justsilver' ), array( __CLASS__, 'dashboard' ) );
		// Put our widget first in the main column.
		global $wp_meta_boxes;
		$normal = $wp_meta_boxes['dashboard']['normal']['core'] ?? array();
		if ( isset( $normal['ns_dashboard'] ) ) {
			$ours = array( 'ns_dashboard' => $normal['ns_dashboard'] );
			unset( $normal['ns_dashboard'] );
			$wp_meta_boxes['dashboard']['normal']['core'] = $ours + $normal; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}

	/** Upcoming active bookings for a date, sorted by time. */
	public static function bookings_on( string $date, int $store = 0 ): array {
		$meta = array(
			'relation' => 'AND',
			'd'        => array( 'key' => '_ns_date', 'value' => $date ),
			't'        => array( 'key' => '_ns_time', 'compare' => 'EXISTS' ),
		);
		if ( $store ) {
			$meta[] = array( 'key' => '_ns_store', 'value' => $store );
		}
		$ids = get_posts( array(
			'post_type'      => NS_Bookings::CPT,
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => $meta,
			'orderby'        => array( 't' => 'ASC' ),
		) );
		return array_map( array( 'NS_Bookings', 'details' ), $ids );
	}

	public static function low_stock( int $limit = 5 ): array {
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_key'       => '_stock',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array(
				array( 'key' => '_ns_is_silver', 'value' => 'yes' ),
				array( 'key' => '_manage_stock', 'value' => 'yes' ),
				array( 'key' => '_stock', 'value' => 3, 'type' => 'NUMERIC', 'compare' => '<=' ),
			),
		) );
		return array( 'total' => (int) $q->found_posts, 'items' => array_filter( array_map( 'wc_get_product', $q->posts ) ) );
	}

	public static function dashboard(): void {
		$kg       = NS_Silver_Price::per_kg();
		$change   = NS_Silver_Price::change();
		$series   = NS_Chart::daily( NS_Silver_Price::history(), 30, time(), wp_timezone() );
		$today    = array_filter( self::bookings_on( wp_date( 'Y-m-d' ) ), fn( $b ) => in_array( $b['status'], array( 'booket', 'bekraeftet' ), true ) );
		$tomorrow = array_filter( self::bookings_on( wp_date( 'Y-m-d', strtotime( '+1 day' ) ) ), fn( $b ) => in_array( $b['status'], array( 'booket', 'bekraeftet' ), true ) );
		$low      = self::low_stock();
		$alerts   = (int) ( wp_count_posts( NS_Alerts::CPT )->publish ?? 0 );
		$orders   = function_exists( 'wc_get_orders' ) ? count( wc_get_orders( array( 'date_created' => '>=' . strtotime( 'today' ), 'return' => 'ids', 'limit' => -1, 'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ) ) ) ) : 0;
		$cal      = admin_url( 'edit.php?post_type=' . NS_Bookings::CPT . '&page=ns-calendar' );
		?>
		<div class="ns-admin ns-dash">
			<div class="ns-dash__price">
				<div>
					<span class="ns-label"><span class="ns-dot"></span><?php esc_html_e( 'Today’s silver price', 'justsilver' ); ?></span>
					<strong><?php echo $kg ? esc_html( number_format( $kg / 1000, 2, ',', '.' ) ) : '–'; ?> <small>kr/g</small></strong>
					<?php if ( $change ) : $up = $change['pct'] >= 0; ?>
						<span class="ns-chip ns-chip--<?php echo $up ? 'up' : 'down'; ?>"><?php echo $up ? '▲' : '▼'; ?> <?php echo esc_html( number_format( abs( $change['pct'] ), 2, ',', '.' ) ); ?>%</span>
					<?php endif; ?>
					<span class="ns-sub"><?php /* translators: %s: price per kg */ echo $kg ? esc_html( sprintf( __( '%s per kg', 'justsilver' ), ns_dkk( $kg ) ) ) : ''; ?></span>
				</div>
				<?php echo self::sparkline( array_column( $series, 'v' ), 280, 64, 'nsDashSpark' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>

			<div class="ns-kpis">
				<a class="ns-kpi" href="<?php echo esc_url( $cal ); ?>"><strong><?php echo count( $today ); ?></strong><span><?php esc_html_e( 'visits today', 'justsilver' ); ?></span></a>
				<a class="ns-kpi" href="<?php echo esc_url( add_query_arg( 'ns_date', wp_date( 'Y-m-d', strtotime( '+1 day' ) ), $cal ) ); ?>"><strong><?php echo count( $tomorrow ); ?></strong><span><?php esc_html_e( 'visits tomorrow', 'justsilver' ); ?></span></a>
				<a class="ns-kpi" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>"><strong><?php echo (int) $orders; ?></strong><span><?php esc_html_e( 'orders today', 'justsilver' ); ?></span></a>
				<a class="ns-kpi <?php echo $low['total'] ? 'ns-kpi--warn' : ''; ?>" href="#ns-low"><strong><?php echo (int) $low['total']; ?></strong><span><?php esc_html_e( 'low on stock', 'justsilver' ); ?></span></a>
				<a class="ns-kpi" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . NS_Alerts::CPT ) ); ?>"><strong><?php echo (int) $alerts; ?></strong><span><?php esc_html_e( 'price alerts', 'justsilver' ); ?></span></a>
			</div>

			<div class="ns-dash__cols">
				<div>
					<h3><?php esc_html_e( 'Today’s visits', 'justsilver' ); ?></h3>
					<?php if ( $today ) : ?>
						<ul class="ns-list">
							<?php foreach ( array_slice( $today, 0, 6 ) as $b ) : ?>
								<li><a href="<?php echo esc_url( get_edit_post_link( $b['id'] ) ); ?>"><b><?php echo esc_html( $b['time'] ); ?></b> <?php echo esc_html( $b['name'] ); ?></a>
									<span><?php echo esc_html( ( $b['store']['name'] ?? '' ) . ' · ' . $b['purpose'] ); ?><?php echo $b['reserved'] ? ' · 🔒' : ''; ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="ns-empty"><?php esc_html_e( 'No visits booked today.', 'justsilver' ); ?></p>
					<?php endif; ?>
				</div>
				<div id="ns-low">
					<h3><?php esc_html_e( 'Low on stock', 'justsilver' ); ?></h3>
					<?php if ( $low['items'] ) : ?>
						<ul class="ns-list">
							<?php foreach ( $low['items'] as $p ) : ?>
								<li><a href="<?php echo esc_url( get_edit_post_link( $p->get_id() ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a>
									<span class="ns-stock ns-stock--<?php echo $p->get_stock_quantity() > 0 ? 'low' : 'out'; ?>"><?php /* translators: %d: items in stock */ echo esc_html( sprintf( _n( '%d in stock', '%d in stock', (int) $p->get_stock_quantity(), 'justsilver' ), (int) $p->get_stock_quantity() ) ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="ns-empty"><?php esc_html_e( 'All silver items are well stocked.', 'justsilver' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="ns-actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=justsilver' ) ); ?>"><?php esc_html_e( 'Set silver price', 'justsilver' ); ?></a>
				<a class="button" href="<?php echo esc_url( $cal ); ?>"><?php esc_html_e( 'Booking calendar', 'justsilver' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>"><?php esc_html_e( 'New product', 'justsilver' ); ?></a>
			</div>
		</div>
		<?php
	}

	/* ================================================================ Calendar */

	public static function menu(): void {
		add_submenu_page( 'edit.php?post_type=' . NS_Bookings::CPT, __( 'Booking calendar', 'justsilver' ), __( 'Calendar', 'justsilver' ), 'edit_pages', 'ns-calendar', array( __CLASS__, 'calendar' ), 1 );
	}

	public static function calendar_link( array $views ): array {
		$views['ns_calendar'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . NS_Bookings::CPT . '&page=ns-calendar' ) ) . '">📅 ' . esc_html__( 'Calendar view', 'justsilver' ) . '</a>';
		return $views;
	}

	/**
	 * Lay bookings out in columns so overlapping visits sit side by side. Pure, unit tested.
	 *
	 * @param array<int,array{time:string}> $bookings sorted by time
	 * @return array<int,array{lane:int,lanes:int}> keyed like $bookings
	 */
	public static function lanes( array $bookings, int $slot_minutes ): array {
		$to_min = fn( $t ) => (int) substr( $t, 0, 2 ) * 60 + (int) substr( $t, 3, 2 );
		$out    = array();
		$group  = array();
		$end    = -1;
		$flush  = function () use ( &$group, &$out ) {
			$n = count( $group );
			foreach ( $group as $k => $lane ) {
				$out[ $k ] = array( 'lane' => $lane, 'lanes' => max( 1, max( $group ) + 1 ) );
			}
			$group = array();
		};
		$lane_end = array();
		foreach ( $bookings as $k => $b ) {
			$start = $to_min( $b['time'] );
			if ( $start >= $end ) {
				$flush();
				$lane_end = array();
			}
			$lane = 0;
			while ( isset( $lane_end[ $lane ] ) && $lane_end[ $lane ] > $start ) {
				$lane++;
			}
			$lane_end[ $lane ] = $start + $slot_minutes;
			$group[ $k ]       = $lane;
			$end               = max( $end, $start + $slot_minutes );
		}
		$flush();
		return $out;
	}

	public static function calendar(): void {
		$tz = wp_timezone();
		// phpcs:disable WordPress.Security.NonceVerification -- read-only navigation
		$d     = sanitize_text_field( wp_unslash( $_GET['ns_date'] ?? '' ) );
		$sid   = absint( $_GET['ns_cal_store'] ?? 0 );
		// phpcs:enable
		$day   = ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $d, $tz ) : false ) ?: new DateTimeImmutable( 'today', $tz );
		$base  = admin_url( 'edit.php?post_type=' . NS_Bookings::CPT . '&page=ns-calendar' );
		$nav   = fn( DateTimeImmutable $x ) => add_query_arg( array_filter( array( 'ns_date' => $x->format( 'Y-m-d' ), 'ns_cal_store' => $sid ?: null ) ), $base );
		$all   = NS_Stores::all();
		$shown = array_values( array_filter( $all, fn( $p ) => ! $sid || $p->ID === $sid ) );

		// Hour range from the stores' opening hours that day.
		$open  = 24 * 60;
		$close = 0;
		$stores = array();
		foreach ( $shown as $p ) {
			$st    = NS_Stores::get( $p->ID );
			$hours = NS_Stores::hours_for( $st, $day );
			$st['open_hours'] = $hours;
			$stores[]         = $st;
			if ( $hours ) {
				$open  = min( $open, (int) substr( $hours[0], 0, 2 ) * 60 + (int) substr( $hours[0], 3, 2 ) );
				$close = max( $close, (int) substr( $hours[1], 0, 2 ) * 60 + (int) substr( $hours[1], 3, 2 ) );
			}
		}
		if ( $open >= $close ) {
			$open  = 9 * 60;
			$close = 17 * 60;
		}
		$open  = (int) floor( $open / 60 ) * 60;
		$close = (int) ceil( $close / 60 ) * 60;
		$ppm   = 1.6; // pixels per minute
		$now   = new DateTimeImmutable( 'now', $tz );
		$is_today = $now->format( 'Y-m-d' ) === $day->format( 'Y-m-d' );
		$now_min  = (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );
		?>
		<div class="wrap ns-admin ns-cal">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Booking calendar', 'justsilver' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . NS_Bookings::CPT ) ); ?>"><?php esc_html_e( 'List view', 'justsilver' ); ?></a>
			<hr class="wp-header-end">

			<div class="ns-cal__bar">
				<div class="ns-cal__nav">
					<a class="button" href="<?php echo esc_url( $nav( $day->modify( '-1 day' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Previous day', 'justsilver' ); ?>">‹</a>
					<a class="button" href="<?php echo esc_url( $nav( new DateTimeImmutable( 'today', $tz ) ) ); ?>"><?php esc_html_e( 'Today', 'justsilver' ); ?></a>
					<a class="button" href="<?php echo esc_url( $nav( $day->modify( '+1 day' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Next day', 'justsilver' ); ?>">›</a>
					<h2><?php echo esc_html( wp_date( 'l j. F Y', $day->getTimestamp() ) ); ?></h2>
				</div>
				<form method="get" class="ns-cal__filter">
					<input type="hidden" name="post_type" value="<?php echo esc_attr( NS_Bookings::CPT ); ?>">
					<input type="hidden" name="page" value="ns-calendar">
					<input type="date" name="ns_date" value="<?php echo esc_attr( $day->format( 'Y-m-d' ) ); ?>">
					<select name="ns_cal_store">
						<option value=""><?php esc_html_e( 'All stores', 'justsilver' ); ?></option>
						<?php foreach ( $all as $p ) : ?><option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $sid, $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
					</select>
					<button class="button"><?php esc_html_e( 'Show', 'justsilver' ); ?></button>
				</form>
			</div>

			<div class="ns-cal__week">
				<?php for ( $i = 0; $i < 7; $i++ ) :
					$wd    = ( new DateTimeImmutable( 'today', $tz ) )->modify( "+{$i} day" );
					$count = count( array_filter( self::bookings_on( $wd->format( 'Y-m-d' ), $sid ), fn( $b ) => 'aflyst' !== $b['status'] ) );
					?>
					<a href="<?php echo esc_url( $nav( $wd ) ); ?>" class="<?php echo $wd->format( 'Y-m-d' ) === $day->format( 'Y-m-d' ) ? 'is-active' : ''; ?>">
						<small><?php echo esc_html( wp_date( 'D', $wd->getTimestamp() ) ); ?></small>
						<strong><?php echo esc_html( $wd->format( 'j' ) ); ?></strong>
						<span class="ns-cal__count <?php echo $count ? 'has' : ''; ?>"><?php /* translators: %d: number of visits */ echo esc_html( sprintf( _n( '%d visit', '%d visits', $count, 'justsilver' ), $count ) ); ?></span>
					</a>
				<?php endfor; ?>
			</div>

			<div class="ns-cal__grid" style="--cols:<?php echo max( 1, count( $stores ) ); ?>">
				<div class="ns-cal__times">
					<div class="ns-cal__head"></div>
					<div class="ns-cal__body" style="height:<?php echo (int) ( ( $close - $open ) * $ppm ); ?>px">
						<?php for ( $m = $open; $m < $close; $m += 60 ) : ?>
							<span style="top:<?php echo (int) ( ( $m - $open ) * $ppm ); ?>px"><?php echo esc_html( sprintf( '%02d:00', $m / 60 ) ); ?></span>
						<?php endfor; ?>
					</div>
				</div>
				<?php foreach ( $stores as $st ) :
					$bookings = array_values( self::bookings_on( $day->format( 'Y-m-d' ), $st['id'] ) );
					$lanes    = self::lanes( $bookings, $st['slot'] );
					?>
					<div class="ns-cal__col">
						<div class="ns-cal__head">
							<strong><?php echo esc_html( $st['name'] ); ?></strong>
							<span><?php echo $st['open_hours'] ? esc_html( $st['open_hours'][0] . '–' . $st['open_hours'][1] ) : esc_html__( 'Closed', 'justsilver' ); ?></span>
						</div>
						<div class="ns-cal__body" style="height:<?php echo (int) ( ( $close - $open ) * $ppm ); ?>px">
							<?php for ( $m = $open; $m < $close; $m += 60 ) : ?><i class="ns-cal__line" style="top:<?php echo (int) ( ( $m - $open ) * $ppm ); ?>px"></i><?php endfor; ?>
							<?php if ( $st['open_hours'] ) :
								$o = (int) substr( $st['open_hours'][0], 0, 2 ) * 60 + (int) substr( $st['open_hours'][0], 3, 2 );
								$c = (int) substr( $st['open_hours'][1], 0, 2 ) * 60 + (int) substr( $st['open_hours'][1], 3, 2 );
								?>
								<div class="ns-cal__open" style="top:<?php echo (int) ( ( $o - $open ) * $ppm ); ?>px;height:<?php echo (int) ( ( $c - $o ) * $ppm ); ?>px"></div>
							<?php else : ?>
								<div class="ns-cal__closed"><?php esc_html_e( 'Closed', 'justsilver' ); ?></div>
							<?php endif; ?>
							<?php if ( $is_today && $now_min > $open && $now_min < $close ) : ?>
								<div class="ns-cal__now" style="top:<?php echo (int) ( ( $now_min - $open ) * $ppm ); ?>px"></div>
							<?php endif; ?>
							<?php foreach ( $bookings as $k => $b ) :
								$start = (int) substr( $b['time'], 0, 2 ) * 60 + (int) substr( $b['time'], 3, 2 );
								$l     = $lanes[ $k ] ?? array( 'lane' => 0, 'lanes' => 1 );
								$style = sprintf( 'top:%dpx;height:%dpx;left:calc(%s%% + 4px);width:calc(%s%% - 8px)', ( $start - $open ) * $ppm, max( 26, $st['slot'] * $ppm - 3 ), 100 / $l['lanes'] * $l['lane'], 100 / $l['lanes'] );
								?>
								<a class="ns-cal__ev ns-cal__ev--<?php echo esc_attr( $b['status'] ); ?>" style="<?php echo esc_attr( $style ); ?>" href="<?php echo esc_url( get_edit_post_link( $b['id'] ) ); ?>">
									<b><?php echo esc_html( $b['time'] . ' ' . $b['name'] ); ?><?php echo $b['reserved'] ? ' 🔒' : ''; ?></b>
									<span><?php echo esc_html( $b['purpose'] ); ?><?php echo $b['products'] ? ' · ' . esc_html( $b['products'][ array_key_first( $b['products'] ) ]->get_name() ) : ''; ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
				<?php if ( ! $stores ) : ?>
					<p class="ns-empty"><?php esc_html_e( 'No stores yet.', 'justsilver' ); ?></p>
				<?php endif; ?>
			</div>

			<p class="ns-cal__legend">
				<?php foreach ( NS_Bookings::statuses() as $k => $label ) : ?>
					<span class="ns-cal__ev--<?php echo esc_attr( $k ); ?>"><i></i><?php echo esc_html( $label ); ?></span>
				<?php endforeach; ?>
				<span>🔒 <?php esc_html_e( 'item reserved', 'justsilver' ); ?></span>
			</p>
		</div>
		<?php
	}

	/* ================================================================ Login */

	public static function login_style(): void {
		if ( 'yes' !== ns_opt( 'brand_login', 'yes' ) ) {
			return;
		}
		$logo = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff"/><stop offset=".5" stop-color="#a9b1bd"/><stop offset="1" stop-color="#e3e7ec"/></linearGradient></defs><path d="M32 3 58 18v28L32 61 6 46V18z" fill="url(#g)" stroke="#0f1b2d" stroke-width="2"/><path d="M38 20v16a7 7 0 0 1-14 0" fill="none" stroke="#0f1b2d" stroke-width="4" stroke-linecap="round"/></svg>' );
		$css  = <<<CSS
body.login { background: radial-gradient(900px 500px at 85% 0%, rgba(129,140,248,.35), transparent 60%), radial-gradient(700px 400px at 0% 100%, rgba(94,234,212,.22), transparent 60%), linear-gradient(160deg, #0b1424, #0f1b2d 50%, #18294a); min-height: 100vh; }
body.login::before { content: ""; position: fixed; inset: 0; pointer-events: none; background-image: radial-gradient(1.5px 1.5px at 20% 30%, rgba(255,255,255,.7), transparent), radial-gradient(1px 1px at 70% 20%, rgba(255,255,255,.6), transparent), radial-gradient(1.5px 1.5px at 85% 70%, rgba(255,255,255,.5), transparent), radial-gradient(1px 1px at 35% 80%, rgba(255,255,255,.6), transparent), radial-gradient(1px 1px at 55% 50%, rgba(255,255,255,.4), transparent); animation: ns-login-twinkle 6s ease-in-out infinite alternate; }
@keyframes ns-login-twinkle { from { opacity: .4; } to { opacity: 1; } }
#login { padding-top: 9vh; }
.login h1 a { background-image: url("{$logo}"); background-size: 72px; width: 72px; height: 72px; margin-bottom: 8px; filter: drop-shadow(0 10px 24px rgba(0,0,0,.45)); }
.login h1::after { content: "JustSilver"; display: block; text-align: center; font: 600 26px/1.2 "Iowan Old Style", "Palatino Linotype", Georgia, serif; color: #fff; letter-spacing: -.01em; margin-bottom: 18px; }
.login form { border: 1px solid rgba(255,255,255,.18); border-radius: 18px; background: rgba(255,255,255,.96); box-shadow: 0 30px 60px rgba(0,0,0,.35); padding: 28px 26px 30px; }
.login form .input, .login input[type=text], .login input[type=password] { border-radius: 10px; border-color: #c5cbd3; padding: 6px 12px; font-size: 18px; }
.login form .input:focus { border-color: #3d6a8f; box-shadow: 0 0 0 3px rgba(143,179,209,.4); }
.wp-core-ui .button-primary { background: #0f1b2d; border-color: #0f1b2d; border-radius: 999px; padding: 0 22px; transition: transform .2s, box-shadow .2s; }
.wp-core-ui .button-primary:hover, .wp-core-ui .button-primary:focus { background: #1c2b42; border-color: #1c2b42; transform: translateY(-1px); box-shadow: 0 8px 18px rgba(15,27,45,.35); }
.login #nav a, .login #backtoblog a, .login .privacy-policy-link { color: #c9d3df !important; }
.login #nav a:hover, .login #backtoblog a:hover { color: #5eead4 !important; }
.login .message, .login .notice, .login .success { border-radius: 12px; border-left-color: #3d6a8f; }
.language-switcher { opacity: .85; }
@media (prefers-reduced-motion: reduce) { body.login::before { animation: none; } }
CSS;
		wp_add_inline_style( 'login', $css );
	}
}
