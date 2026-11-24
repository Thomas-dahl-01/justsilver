<?php
/**
 * Sølv-forside: [justsilver_forside]
 * Kan overskrives af temaet: {tema}/justsilver/landing.php
 */
defined( 'ABSPATH' ) || exit;

$kg       = NS_Silver_Price::per_kg();
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
?>
<div class="ns ns-landing">
	<section class="ns-hero">
		<div class="ns-hero__text">
			<p class="ns-eyebrow">Fysisk sølv til dagspris</p>
			<h1>Sølv, der kan<br><em>mærkes i hånden</em></h1>
			<p class="ns-lead">Barrer, mønter og dansk sølvtøj – handlet til dagspris. Køb online med forsikret levering, eller book en tid og se sølvet, før du beslutter dig.</p>
			<div class="ns-hero__cta">
				<a class="ns-btn ns-btn--light" href="<?php echo esc_url( $shop_url ); ?>">Se alt sølv</a>
				<a class="ns-btn ns-btn--outline" href="<?php echo esc_url( $book_url ); ?>">Book tid i butikken</a>
			</div>
			<ul class="ns-usp"><li>Priser fra dagens sølvkurs</li><li>Forsikret forsendelse</li><li>Personlig rådgivning</li></ul>
		</div>
		<div class="ns-hero__art" aria-hidden="true">
			<div class="ns-ingot ns-ingot--1"></div><div class="ns-ingot ns-ingot--2"></div><div class="ns-coin"></div>
			<?php if ( $kg ) : ?>
			<div class="ns-hero__price">
				<span>Dagens sølvpris</span>
				<strong><?php echo esc_html( number_format( $kg / 1000, 2, ',', '.' ) ); ?> kr/g</strong>
				<small><?php echo esc_html( ns_dkk( $kg ) ); ?> pr. kg rent sølv</small>
			</div>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
	<section class="ns-section">
		<div class="ns-section__head"><h2>Find dit sølv</h2><a href="<?php echo esc_url( $shop_url ); ?>">Se hele sortimentet →</a></div>
		<div class="ns-cats">
			<?php foreach ( $cats as $c ) : ?>
				<a class="ns-cat" href="<?php echo esc_url( get_term_link( $c ) ); ?>">
					<span class="ns-cat__name"><?php echo esc_html( $c->name ); ?></span>
					<span class="ns-cat__desc"><?php echo esc_html( wp_trim_words( $c->description, 14 ) ); ?></span>
					<span class="ns-cat__count"><?php echo esc_html( $c->count . ' varer' ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $cat && ! is_wp_error( $cat ) ) : ?>
	<section class="ns-section">
		<div class="ns-section__head"><h2>Populært lige nu</h2></div>
		<?php echo do_shortcode( '[products limit="8" columns="4" visibility="featured" category="' . esc_attr( $cat->slug ) . '" cat_operator="IN"]' ); ?>
	</section>
	<?php endif; ?>

	<section class="ns-band">
		<div class="ns-step-card"><span>1</span><h3>Vælg online</h3><p>Filtrér på vægt, finhed og type – priserne følger sølvkursen automatisk.</p></div>
		<div class="ns-step-card"><span>2</span><h3>Køb eller book</h3><p>Betal og få det leveret forsikret, eller book en tid og se det i hånden først.</p></div>
		<div class="ns-step-card"><span>3</span><h3>Hent eller modtag</h3><p>Hent i en af vores butikker, eller få det leveret diskret til døren.</p></div>
	</section>

	<?php if ( $stores ) : ?>
	<section class="ns-section">
		<div class="ns-section__head"><h2>Besøg os</h2><a href="<?php echo esc_url( $book_url ); ?>">Book en tid →</a></div>
		<div class="ns-stores">
			<?php foreach ( $stores as $p ) : $st = NS_Stores::get( $p->ID ); ?>
				<div class="ns-store">
					<h3><?php echo esc_html( $st['name'] ); ?></h3>
					<p><?php echo esc_html( $st['address'] ); ?><br><?php echo esc_html( $st['postcode'] . ' ' . $st['city'] ); ?>
						<?php if ( $st['phone'] ) : ?><br><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $st['phone'] ) ); ?>"><?php echo esc_html( $st['phone'] ); ?></a><?php endif; ?></p>
					<ul class="ns-hours">
						<?php foreach ( NS_Stores::WEEKDAYS as $n => $label ) : $h = $st['hours'][ $n ] ?? array(); ?>
							<li><span><?php echo esc_html( $label ); ?></span><span><?php echo ! empty( $h['open'] ) ? esc_html( $h['open'] . '–' . $h['close'] ) : 'Lukket'; ?></span></li>
						<?php endforeach; ?>
					</ul>
					<a class="ns-btn ns-btn--sm" href="<?php echo esc_url( NS_Bookings::url( array( 'ns_butik' => $p->ID ) ) ); ?>">Book tid her</a>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>
</div>
