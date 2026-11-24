<?php
/**
 * Price alert form: [justsilver_prisalarm]
 */
defined( 'ABSPATH' ) || exit;
$per_gram = NS_Silver_Price::per_gram();
$suggest  = $per_gram ? floor( $per_gram * 0.95 * 10 ) / 10 : '';
?>
<div class="ns ns-card ns-alert" id="ns-prisalarm">
	<span class="ns-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0"/></svg></span>
	<h3><?php esc_html_e( 'Price alert', 'justsilver' ); ?></h3>
	<p class="ns-muted"><?php esc_html_e( 'Waiting for a better price? We email you as soon as silver reaches your price.', 'justsilver' ); ?></p>
	<?php NS_Frontend::print_notices(); ?>
	<form method="post" class="ns-form">
		<?php wp_nonce_field( 'ns_alert', 'ns_alert_nonce' ); ?>
		<p class="ns-hp" aria-hidden="true"><label>Website <input type="text" name="ns_website" tabindex="-1" autocomplete="off"></label></p>
		<div class="ns-row">
			<p><label for="ns_alert_target"><?php esc_html_e( 'Alert me at (kr/g)', 'justsilver' ); ?></label>
				<input id="ns_alert_target" name="ns_alert_target" type="number" step="0.01" min="0.01" required value="<?php echo esc_attr( (string) $suggest ); ?>"></p>
			<p><label for="ns_alert_email"><?php esc_html_e( 'Email', 'justsilver' ); ?></label>
				<input id="ns_alert_email" name="ns_alert_email" type="email" required autocomplete="email"></p>
		</div>
		<?php if ( $per_gram ) : ?>
			<p class="ns-small ns-muted"><?php /* translators: %s: price per gram */ echo esc_html( sprintf( __( 'Silver is %s kr/g right now.', 'justsilver' ), number_format( $per_gram, 2, ',', '.' ) ) ); ?></p>
		<?php endif; ?>
		<label class="ns-check"><input type="checkbox" name="ns_alert_consent" value="1" required> <?php esc_html_e( 'Yes, you may email me about this price alert. I can unsubscribe at any time.', 'justsilver' ); ?></label>
		<button type="submit" name="ns_alert_submit" value="1" class="ns-btn ns-btn--block"><?php esc_html_e( 'Create price alert', 'justsilver' ); ?></button>
	</form>
</div>
