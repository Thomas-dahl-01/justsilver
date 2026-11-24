<?php
/**
 * Sell-your-silver calculator: [justsilver_saelg]
 * Variables: $weight, $purity, $spot, $offer
 */
defined( 'ABSPATH' ) || exit;
$book = NS_Bookings::url( array( 'ns_formaal' => 'vurdering' ) );
?>
<div class="ns ns-card ns-sell" data-ns-sell>
	<span class="ns-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 12h4l3-3 4 4 3-3h4M4 19h16"/></svg></span>
	<h3><?php esc_html_e( 'What is your silver worth?', 'justsilver' ); ?></h3>
	<p class="ns-muted"><?php esc_html_e( 'Enter weight and purity – see an instant offer based on today’s price.', 'justsilver' ); ?></p>
	<form method="get" class="ns-form">
		<div class="ns-row">
			<p><label for="ns_s_vaegt"><?php esc_html_e( 'Weight (grams)', 'justsilver' ); ?></label>
				<input id="ns_s_vaegt" name="ns_s_vaegt" type="number" min="0" step="0.1" inputmode="decimal" placeholder="250" value="<?php echo $weight ? esc_attr( (string) $weight ) : ''; ?>"></p>
			<p><label for="ns_s_finhed"><?php esc_html_e( 'Purity', 'justsilver' ); ?></label>
				<select id="ns_s_finhed" name="ns_s_finhed">
					<?php foreach ( ns_purities() as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $purity, $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></p>
		</div>
		<div class="ns-sell__result" aria-live="polite">
			<span><?php esc_html_e( 'Our offer', 'justsilver' ); ?></span>
			<strong data-ns-sell-offer><?php echo $offer ? esc_html( ns_dkk( $offer ) ) : '– kr.'; ?></strong>
			<small data-ns-sell-fine><?php echo $weight ? esc_html( sprintf( /* translators: %s: grams of fine silver */ __( '%s fine silver', 'justsilver' ), ns_grams( round( $weight * (float) $purity, 2 ) ) ) ) : esc_html__( 'Final price after valuation in store.', 'justsilver' ); ?></small>
		</div>
		<noscript><button class="ns-btn ns-btn--ghost ns-btn--block" type="submit"><?php esc_html_e( 'Calculate', 'justsilver' ); ?></button></noscript>
		<a class="ns-btn ns-btn--block ns-btn--shine" href="<?php echo esc_url( $book ); ?>"><?php esc_html_e( 'Book a free valuation', 'justsilver' ); ?></a>
	</form>
</div>
