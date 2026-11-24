<?php
/**
 * Booking-formular. Kan overskrives af temaet: kopier til {tema}/justsilver/booking-form.php
 *
 * @var array $s Tilstand fra NS_Bookings::state()
 */
defined( 'ABSPATH' ) || exit;

$vare = implode( ',', $s['products'] );
$base = array( 'ns_vare' => $vare, 'ns_formaal' => 'koeb' !== $s['purpose'] ? $s['purpose'] : '' );
// phpcs:disable WordPress.Security.NonceVerification -- genudfyld felter efter fejl
$old = fn( $k ) => isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
// phpcs:enable
?>
<div class="ns ns-booking">
	<div class="ns-booking__steps">

		<section class="ns-step <?php echo $s['store'] ? 'is-done' : ''; ?>">
			<h2><span>1</span> Vælg butik</h2>
			<?php if ( ! $s['stores'] ) : ?>
				<p class="ns-muted">Der er endnu ingen butikker sat op til booking.</p>
			<?php endif; ?>
			<div class="ns-choices">
				<?php foreach ( $s['stores'] as $p ) : $st = NS_Stores::get( $p->ID ); ?>
					<a class="ns-choice <?php echo ( $s['store']['id'] ?? 0 ) === $p->ID ? 'is-active' : ''; ?>" href="<?php echo esc_url( NS_Bookings::url( $base + array( 'ns_butik' => $p->ID ) ) ); ?>">
						<strong><?php echo esc_html( $st['name'] ); ?></strong>
						<span><?php echo esc_html( trim( $st['address'] . ', ' . $st['city'], ', ' ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<?php if ( $s['store'] ) : ?>
		<section class="ns-step <?php echo $s['day'] ? 'is-done' : ''; ?>">
			<h2><span>2</span> Vælg dag</h2>
			<?php if ( $s['days'] ) : ?>
				<div class="ns-days">
					<?php foreach ( $s['days'] as $d ) : ?>
						<a class="ns-day <?php echo ( $s['day'] && $s['day']->format( 'Y-m-d' ) === $d->format( 'Y-m-d' ) ) ? 'is-active' : ''; ?>"
						   href="<?php echo esc_url( NS_Bookings::url( $base + array( 'ns_butik' => $s['store']['id'], 'ns_dato' => $d->format( 'Y-m-d' ) ) ) ); ?>">
							<small><?php echo esc_html( wp_date( 'D', $d->getTimestamp() ) ); ?></small>
							<strong><?php echo esc_html( $d->format( 'j' ) ); ?></strong>
							<small><?php echo esc_html( wp_date( 'M', $d->getTimestamp() ) ); ?></small>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="ns-muted">Ingen ledige tider lige nu<?php echo $s['store']['phone'] ? ' – ring på ' . esc_html( $s['store']['phone'] ) : ''; ?>.</p>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<?php if ( $s['day'] ) : ?>
		<section class="ns-step <?php echo $s['time'] ? 'is-done' : ''; ?>">
			<h2><span>3</span> Vælg tidspunkt – <?php echo esc_html( wp_date( 'l j. F', $s['day']->getTimestamp() ) ); ?></h2>
			<div class="ns-slots">
				<?php foreach ( $s['slots'] as $t => $free ) : ?>
					<a class="ns-slot <?php echo $t === $s['time'] ? 'is-active' : ''; ?>"
					   href="<?php echo esc_url( NS_Bookings::url( $base + array( 'ns_butik' => $s['store']['id'], 'ns_dato' => $s['day']->format( 'Y-m-d' ), 'ns_tid' => $t ) ) . '#ns-oplysninger' ); ?>"><?php echo esc_html( $t ); ?></a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php if ( $s['time'] ) : ?>
		<section class="ns-step" id="ns-oplysninger">
			<h2><span>4</span> Dine oplysninger</h2>
			<form method="post" class="ns-form" novalidate>
				<?php wp_nonce_field( 'ns_booking', 'ns_booking_nonce' ); ?>
				<input type="hidden" name="ns_butik" value="<?php echo esc_attr( $s['store']['id'] ); ?>">
				<input type="hidden" name="ns_dato" value="<?php echo esc_attr( $s['day']->format( 'Y-m-d' ) ); ?>">
				<input type="hidden" name="ns_tid" value="<?php echo esc_attr( $s['time'] ); ?>">
				<input type="hidden" name="ns_vare" value="<?php echo esc_attr( $vare ); ?>">
				<p class="ns-hp" aria-hidden="true"><label>Website <input type="text" name="ns_website" tabindex="-1" autocomplete="off"></label></p>

				<fieldset class="ns-radios"><legend>Hvad vil du?</legend>
					<?php foreach ( NS_Bookings::purposes() as $k => $label ) : ?>
						<label><input type="radio" name="ns_formaal" value="<?php echo esc_attr( $k ); ?>" <?php checked( ( $old( 'ns_formaal' ) ?: $s['purpose'] ), $k ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<p><label for="ns_name">Navn *</label><input id="ns_name" name="ns_name" required autocomplete="name" value="<?php echo esc_attr( $old( 'ns_name' ) ); ?>"></p>
				<div class="ns-row">
					<p><label for="ns_email">E-mail *</label><input id="ns_email" name="ns_email" type="email" required autocomplete="email" value="<?php echo esc_attr( $old( 'ns_email' ) ); ?>"></p>
					<p><label for="ns_phone">Telefon *</label><input id="ns_phone" name="ns_phone" type="tel" required autocomplete="tel" value="<?php echo esc_attr( $old( 'ns_phone' ) ); ?>"></p>
				</div>
				<p><label for="ns_note">Bemærkning</label><textarea id="ns_note" name="ns_note" rows="3"><?php echo esc_textarea( $old( 'ns_note' ) ); ?></textarea></p>
				<button type="submit" name="ns_booking_submit" value="1" class="ns-btn ns-btn--block">
					Bekræft booking – <?php echo esc_html( wp_date( 'j. F', $s['day']->getTimestamp() ) . ' kl. ' . $s['time'] ); ?>
				</button>
			</form>
		</section>
		<?php endif; ?>
	</div>

	<aside class="ns-summary">
		<h3>Din booking</h3>
		<div class="ns-summary__row"><span>Butik</span><span><?php echo esc_html( $s['store']['name'] ?? '–' ); ?></span></div>
		<div class="ns-summary__row"><span>Dag</span><span><?php echo $s['day'] ? esc_html( wp_date( 'j. F Y', $s['day']->getTimestamp() ) ) : '–'; ?></span></div>
		<div class="ns-summary__row"><span>Tid</span><span><?php echo esc_html( $s['time'] ?: '–' ); ?></span></div>
		<?php if ( $s['products'] ) : ?>
			<p><strong>Varer du vil se:</strong></p>
			<?php foreach ( $s['products'] as $pid ) : $p = wc_get_product( $pid ); if ( ! $p ) { continue; } ?>
				<div class="ns-mini">
					<div class="ns-mini__img"><?php echo $p->get_image( 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<span><?php echo esc_html( $p->get_name() ); ?><br><span class="ns-muted"><?php echo wp_kses_post( $p->get_price_html() ); ?></span></span>
				</div>
			<?php endforeach; ?>
			<p class="ns-muted ns-small">Vi lægger varerne frem til dit besøg.</p>
		<?php endif; ?>
		<p class="ns-muted ns-small">Husk gyldigt billed-ID ved køb og salg.</p>
	</aside>
</div>
