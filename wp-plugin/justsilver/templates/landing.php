<?php
/**
 * Silver front page: [justsilver_forside] / block justsilver/forside
 * Themes may override: {theme}/justsilver/landing.php
 */
defined( 'ABSPATH' ) || exit;

$kg       = NS_Silver_Price::per_kg();
$change   = NS_Silver_Price::change();
$cat_id   = (int) ns_opt( 'silver_category' );
$cats     = $cat_id ? get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $cat_id, 'hide_empty' => false, 'orderby' => 'meta_value_num', 'meta_key' => 'order' ) ) : array();
$cat      = $cat_id ? get_term( $cat_id, 'product_cat' ) : null;
$shop_url = NS_Frontend::silver_url();
if ( $cat && ! is_wp_error( $cat ) ) {
	$link     = get_term_link( $cat );
	$shop_url = is_wp_error( $link ) ? $shop_url : $link;
}
$book_url = NS_Bookings::url();
$stores   = NS_Stores::all();
$count    = $cat && ! is_wp_error( $cat ) ? (int) ( new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'terms' => $cat_id, 'include_children' => true ) ) ) ) )->found_posts : 0;
// Small line icons in category order: bar, coin, granules, ring, spoon, goblet.
$icons = array(
	'<path pathLength="1" d="M5 17 8 7h8l3 10z"/>',
	'<circle pathLength="1" cx="12" cy="12" r="8"/><circle pathLength="1" cx="12" cy="12" r="5"/>',
	'<circle pathLength="1" cx="8" cy="15" r="3"/><circle pathLength="1" cx="15" cy="15" r="3"/><circle pathLength="1" cx="11.5" cy="9" r="3"/>',
	'<circle pathLength="1" cx="12" cy="14" r="6"/><path pathLength="1" d="m10 5 2-2 2 2-2 2z"/>',
	'<ellipse pathLength="1" cx="12" cy="6.5" rx="3" ry="4"/><path pathLength="1" d="M12 10.5V21"/>',
	'<path pathLength="1" d="M7 3h10c0 6-2 9-5 10-3-1-5-4-5-10zM12 13v5M8 21h8"/>',
);
?>
<div class="ns ns-landing">
	<section class="ns-hero">
		<div class="ns-hero__sparkles" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
		<div class="ns-hero__text ns-reveal" data-ns-depth="-0.25">
			<p class="ns-eyebrow"><?php esc_html_e( 'Physical silver at today’s price', 'justsilver' ); ?></p>
			<h1><?php esc_html_e( 'Silver you can', 'justsilver' ); ?><br><em class="ns-shimmer"><?php esc_html_e( 'feel in your hand', 'justsilver' ); ?></em></h1>
			<p class="ns-lead"><?php esc_html_e( 'Bars, coins and Danish silverware – traded at the daily price. Buy online with insured delivery, or book a visit and see the silver before you decide.', 'justsilver' ); ?></p>
			<div class="ns-hero__cta">
				<a class="ns-btn ns-btn--light ns-btn--shine" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Shop all silver', 'justsilver' ); ?> →</a>
				<a class="ns-btn ns-btn--outline" href="<?php echo esc_url( $book_url ); ?>"><?php esc_html_e( 'Book a visit', 'justsilver' ); ?></a>
			</div>
			<ul class="ns-stats">
				<?php if ( $count ) : ?><li><strong data-ns-count="<?php echo (int) $count; ?>"><?php echo (int) $count; ?></strong><span><?php esc_html_e( 'silver items', 'justsilver' ); ?></span></li><?php endif; ?>
				<?php if ( $stores ) : ?><li><strong data-ns-count="<?php echo count( $stores ); ?>"><?php echo count( $stores ); ?></strong><span><?php esc_html_e( 'stores', 'justsilver' ); ?></span></li><?php endif; ?>
				<li><strong>24/7</strong><span><?php esc_html_e( 'live prices', 'justsilver' ); ?></span></li>
			</ul>
		</div>
		<div class="ns-hero__art" aria-hidden="true">
			<div class="ns-ingot ns-ingot--1" data-ns-depth="1.2"><span>1 KG · 999</span></div>
			<div class="ns-ingot ns-ingot--2" data-ns-depth="0.6"><span>500 G · 999</span></div>
			<div class="ns-coin" data-ns-depth="1.8"><span>1 OZ</span></div>
			<?php if ( $kg ) : ?>
			<div class="ns-hero__price" data-ns-depth="-0.5">
				<span><span class="ns-ticker__dot"></span><?php esc_html_e( 'Today’s silver price', 'justsilver' ); ?></span>
				<strong><span data-ns-live><?php echo esc_html( number_format( $kg / 1000, 2, ',', '.' ) ); ?></span> kr/g</strong>
				<?php if ( $change ) : $up = $change['pct'] >= 0; ?>
					<small class="ns-delta ns-delta--<?php echo $up ? 'up' : 'down'; ?>"><?php echo $up ? '▲' : '▼'; ?> <?php echo esc_html( number_format( abs( $change['pct'] ), 2, ',', '.' ) ); ?>% <?php esc_html_e( 'since yesterday', 'justsilver' ); ?></small>
				<?php else : ?>
					<small><?php /* translators: %s: price per kg */ echo esc_html( sprintf( __( '%s per kg fine silver', 'justsilver' ), ns_dkk( $kg ) ) ); ?></small>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</section>

	<div class="ns-marquee" aria-hidden="true"><div class="ns-marquee__track">
		<?php for ( $i = 0; $i < 2; $i++ ) : ?>
			<span>✦ <?php esc_html_e( 'Prices from the live silver spot price', 'justsilver' ); ?></span>
			<span>✦ <?php esc_html_e( 'Insured delivery', 'justsilver' ); ?></span>
			<span>✦ <?php esc_html_e( 'LBMA-approved bars', 'justsilver' ); ?></span>
			<span>✦ <?php esc_html_e( 'Free valuation in store', 'justsilver' ); ?></span>
			<span>✦ <?php esc_html_e( 'We reserve items for your visit', 'justsilver' ); ?></span>
			<span>✦ <?php esc_html_e( 'Personal advice', 'justsilver' ); ?></span>
		<?php endfor; ?>
	</div></div>

	<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
	<section class="ns-section">
		<div class="ns-section__head ns-reveal"><h2><?php esc_html_e( 'Find your silver', 'justsilver' ); ?></h2><a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'See the full range', 'justsilver' ); ?> →</a></div>
		<div class="ns-cats">
			<?php foreach ( array_values( $cats ) as $i => $c ) : ?>
				<a class="ns-cat ns-reveal" style="--ns-delay:<?php echo (int) $i * 70; ?>ms" href="<?php echo esc_url( get_term_link( $c ) ); ?>">
					<span class="ns-cat__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?php echo $icons[ $i % count( $icons ) ]; // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></svg></span>
					<span class="ns-cat__name"><?php echo esc_html( $c->name ); ?></span>
					<span class="ns-cat__desc"><?php echo esc_html( wp_trim_words( $c->description, 14 ) ); ?></span>
					<span class="ns-cat__count"><?php /* translators: %d: number of items */ echo esc_html( sprintf( _n( '%d item', '%d items', $c->count, 'justsilver' ), $c->count ) ); ?> →</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php $chart = NS_Chart::shortcode( array( 'dage' => 90 ) ); if ( $chart ) : ?>
	<section class="ns-section ns-reveal"><?php echo $chart; // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
	<?php endif; ?>

	<?php if ( $cat && ! is_wp_error( $cat ) ) : ?>
	<section class="ns-section">
		<div class="ns-section__head ns-reveal"><h2><?php esc_html_e( 'Popular right now', 'justsilver' ); ?></h2></div>
		<div class="ns-reveal"><?php echo do_shortcode( '[products limit="8" columns="4" visibility="featured" category="' . esc_attr( $cat->slug ) . '" cat_operator="IN"]' ); ?></div>
	</section>
	<?php endif; ?>

	<section class="ns-duo">
		<div class="ns-reveal"><?php echo do_shortcode( '[justsilver_saelg]' ); ?></div>
		<div class="ns-reveal" style="--ns-delay:120ms"><?php echo do_shortcode( '[justsilver_prisalarm]' ); ?></div>
	</section>

	<section class="ns-band">
		<div class="ns-step-card ns-reveal"><span>1</span><h3><?php esc_html_e( 'Choose online', 'justsilver' ); ?></h3><p><?php esc_html_e( 'Filter by weight, purity and type – prices follow the silver price automatically.', 'justsilver' ); ?></p></div>
		<div class="ns-step-card ns-reveal" style="--ns-delay:100ms"><span>2</span><h3><?php esc_html_e( 'Buy or book', 'justsilver' ); ?></h3><p><?php esc_html_e( 'Pay and get insured delivery – or book a visit and we reserve the item for you.', 'justsilver' ); ?></p></div>
		<div class="ns-step-card ns-reveal" style="--ns-delay:200ms"><span>3</span><h3><?php esc_html_e( 'Collect or receive', 'justsilver' ); ?></h3><p><?php esc_html_e( 'Pick up in one of our stores, or have it delivered discreetly to your door.', 'justsilver' ); ?></p></div>
	</section>

	<?php if ( $stores ) : ?>
	<section class="ns-section">
		<div class="ns-section__head ns-reveal"><h2><?php esc_html_e( 'Visit us', 'justsilver' ); ?></h2><a href="<?php echo esc_url( $book_url ); ?>"><?php esc_html_e( 'Book a visit', 'justsilver' ); ?> →</a></div>
		<div class="ns-stores">
			<?php foreach ( $stores as $i => $p ) : $st = NS_Stores::get( $p->ID ); $today = $st['hours'][ (int) wp_date( 'N' ) ] ?? array(); ?>
				<div class="ns-store ns-reveal" style="--ns-delay:<?php echo (int) $i * 90; ?>ms">
					<h3><?php echo esc_html( $st['name'] ); ?></h3>
					<p class="ns-open <?php echo ! empty( $today['open'] ) ? 'is-open' : ''; ?>">
						<?php
						echo ! empty( $today['open'] )
							/* translators: 1: opens, 2: closes */
							? esc_html( sprintf( __( 'Open today %1$s–%2$s', 'justsilver' ), $today['open'], $today['close'] ) )
							: esc_html__( 'Closed today', 'justsilver' );
						?>
					</p>
					<p><?php echo esc_html( $st['address'] ); ?><br><?php echo esc_html( $st['postcode'] . ' ' . $st['city'] ); ?>
						<?php if ( $st['phone'] ) : ?><br><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $st['phone'] ) ); ?>"><?php echo esc_html( $st['phone'] ); ?></a><?php endif; ?></p>
					<details class="ns-hours-toggle"><summary><?php esc_html_e( 'Opening hours', 'justsilver' ); ?></summary>
						<ul class="ns-hours">
							<?php foreach ( NS_Stores::weekdays() as $n => $label ) : $h = $st['hours'][ $n ] ?? array(); ?>
								<li><span><?php echo esc_html( $label ); ?></span><span><?php echo ! empty( $h['open'] ) ? esc_html( $h['open'] . '–' . $h['close'] ) : esc_html__( 'Closed', 'justsilver' ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</details>
					<a class="ns-btn ns-btn--sm" href="<?php echo esc_url( NS_Bookings::url( array( 'ns_butik' => $p->ID ) ) ); ?>"><?php esc_html_e( 'Book here', 'justsilver' ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>
</div>
