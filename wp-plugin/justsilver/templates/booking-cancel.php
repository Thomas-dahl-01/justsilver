<?php
/**
 * Cancel a booking from the link in the confirmation / reminder mail.
 *
 * @var array $b NS_Bookings::details()
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="ns ns-confirm">
	<?php if ( 'aflyst' === $b['status'] ) : ?>
		<h2><?php esc_html_e( 'This booking is already cancelled', 'justsilver' ); ?></h2>
	<?php elseif ( $b['date'] ) : ?>
		<h2><?php esc_html_e( 'Cancel your booking?', 'justsilver' ); ?></h2>
		<p><strong><?php echo esc_html( NS_Bookings::when_text( $b ) ); ?></strong><br><?php echo esc_html( NS_Bookings::where_text( $b ) ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'ns_cancel_' . $b['id'], 'ns_cancel_nonce' ); ?>
			<input type="hidden" name="ns_cancel_id" value="<?php echo (int) $b['id']; ?>">
			<input type="hidden" name="k" value="<?php echo esc_attr( NS_Bookings::token( $b['id'], 'cancel' ) ); ?>">
			<button class="ns-btn" type="submit"><?php esc_html_e( 'Yes, cancel the booking', 'justsilver' ); ?></button>
			<a class="ns-btn ns-btn--ghost" href="<?php echo esc_url( NS_Frontend::silver_url() ); ?>"><?php esc_html_e( 'No, keep it', 'justsilver' ); ?></a>
		</form>
	<?php endif; ?>
</div>
