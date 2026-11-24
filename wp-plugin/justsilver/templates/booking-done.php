<?php
/**
 * Confirmation after booking.
 *
 * @var array $b NS_Bookings::details()
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="ns ns-confirm">
	<div class="ns-confetti" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
	<div class="ns-confirm__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></div>
	<h2><?php /* translators: %s: weekday */ echo esc_html( sprintf( __( 'See you on %s!', 'justsilver' ), wp_date( 'l', $b['date']->getTimestamp() ) ) ); ?></h2>
	<p><?php esc_html_e( 'Your visit is booked:', 'justsilver' ); ?> <strong><?php echo esc_html( NS_Bookings::when_text( $b ) ); ?></strong><br><?php echo esc_html( NS_Bookings::where_text( $b ) ); ?></p>
	<p><?php /* translators: 1: purpose, 2: email */ echo esc_html( sprintf( __( 'Purpose: %1$s. A confirmation with a calendar file has been sent to %2$s.', 'justsilver' ), $b['purpose'], $b['email'] ) ); ?></p>
	<?php if ( $b['products'] ) : ?>
		<div class="ns-confirm__items">
			<?php foreach ( $b['products'] as $p ) : $locked = in_array( $p->get_id(), $b['reserved'], true ); ?>
				<span class="ns-chip <?php echo $locked ? 'is-locked' : ''; ?>"><?php echo $locked ? '🔒 ' : ''; ?><?php echo esc_html( $p->get_name() ); ?><?php echo $locked ? ' – ' . esc_html__( 'reserved for you', 'justsilver' ) : ''; ?></span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<a class="ns-btn" href="<?php echo esc_url( NS_Frontend::silver_url() ); ?>"><?php esc_html_e( 'Keep browsing silver', 'justsilver' ); ?></a>
</div>
