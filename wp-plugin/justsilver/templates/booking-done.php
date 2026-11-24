<?php
/**
 * Bekræftelse efter booking.
 *
 * @var array $b NS_Bookings::details()
 */
defined( 'ABSPATH' ) || exit;
NS_Frontend::enqueue();
?>
<div class="ns ns-confirm">
	<div class="ns-confirm__icon" aria-hidden="true">✓</div>
	<h2>Vi ses <?php echo esc_html( wp_date( 'l', $b['date']->getTimestamp() ) ); ?>!</h2>
	<p>Din tid er booket <strong><?php echo esc_html( wp_date( 'j. F Y', $b['date']->getTimestamp() ) . ' kl. ' . $b['time'] ); ?></strong>
		i <?php echo esc_html( $b['store']['name'] . ', ' . $b['store']['address'] . ', ' . $b['store']['postcode'] . ' ' . $b['store']['city'] ); ?>.</p>
	<p>Formål: <?php echo esc_html( $b['purpose'] ); ?>. En bekræftelse er sendt til <?php echo esc_html( $b['email'] ); ?>.</p>
	<?php if ( $b['products'] ) : ?>
		<p>Vi lægger disse frem: <?php echo esc_html( implode( ', ', array_map( fn( $p ) => $p->get_name(), $b['products'] ) ) ); ?>.</p>
	<?php endif; ?>
	<a class="ns-btn" href="<?php echo esc_url( NS_Frontend::silver_url() ); ?>">Kig videre i sølv-shoppen</a>
</div>
