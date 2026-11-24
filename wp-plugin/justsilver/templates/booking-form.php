<?php
/**
 * Booking form. Themes may override: {theme}/justsilver/booking-form.php
 *
 * @var array $s State from NS_Bookings::state()
 */
defined( 'ABSPATH' ) || exit;

$vare = implode( ',', $s['products'] );
$base = array( 'ns_vare' => $vare, 'ns_formaal' => 'koeb' !== $s['purpose'] ? $s['purpose'] : '' );
// phpcs:disable WordPress.Security.NonceVerification -- refill fields after an error
$old = fn( $k ) => isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
// phpcs:enable
$step = $s['time'] ? 4 : ( $s['day'] ? 3 : ( $s['store'] ? 2 : 1 ) );
?>
<div class="ns ns-booking">
	<div class="ns-booking__steps">
		<ol class="ns-progress" aria-label="<?php esc_attr_e( 'Booking steps', 'justsilver' ); ?>">
			<?php foreach ( array( __( 'Store', 'justsilver' ), __( 'Day', 'justsilver' ), __( 'Time', 'justsilver' ), __( 'Details', 'justsilver' ) ) as $i => $label ) : ?>
				<li class="<?php echo $i + 1 < $step ? 'is-done' : ( $i + 1 === $step ? 'is-current' : '' ); ?>"><span><?php echo (int) $i + 1; ?></span><?php echo esc_html( $label ); ?></li>
			<?php endforeach; ?>
		</ol>

		<section class="ns-step <?php echo $s['store'] ? 'is-done' : ''; ?>">
			<h2><span>1</span> <?php esc_html_e( 'Choose store', 'justsilver' ); ?></h2>
			<?php if ( ! $s['stores'] ) : ?>
				<p class="ns-muted"><?php esc_html_e( 'No stores have been set up for booking yet.', 'justsilver' ); ?></p>
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
		<section class="ns-step ns-step--enter <?php echo $s['day'] ? 'is-done' : ''; ?>">
			<h2><span>2</span> <?php esc_html_e( 'Choose day', 'justsilver' ); ?></h2>
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
				<p class="ns-muted"><?php esc_html_e( 'No free times right now.', 'justsilver' ); ?> <?php echo $s['store']['phone'] ? esc_html( sprintf( /* translators: %s: phone */ __( 'Call us on %s.', 'justsilver' ), $s['store']['phone'] ) ) : ''; ?></p>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<?php if ( $s['day'] ) : ?>
		<section class="ns-step ns-step--enter <?php echo $s['time'] ? 'is-done' : ''; ?>">
			<h2><span>3</span> <?php /* translators: %s: date */ echo esc_html( sprintf( __( 'Choose time – %s', 'justsilver' ), wp_date( 'l j. F', $s['day']->getTimestamp() ) ) ); ?></h2>
			<div class="ns-slots">
				<?php foreach ( $s['slots'] as $t => $free ) : ?>
					<a class="ns-slot <?php echo $t === $s['time'] ? 'is-active' : ''; ?>"
					   href="<?php echo esc_url( NS_Bookings::url( $base + array( 'ns_butik' => $s['store']['id'], 'ns_dato' => $s['day']->format( 'Y-m-d' ), 'ns_tid' => $t ) ) . '#ns-oplysninger' ); ?>"><?php echo esc_html( $t ); ?></a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php if ( $s['time'] ) : ?>
		<section class="ns-step ns-step--enter" id="ns-oplysninger">
			<h2><span>4</span> <?php esc_html_e( 'Your details', 'justsilver' ); ?></h2>
			<form method="post" class="ns-form" novalidate>
				<?php wp_nonce_field( 'ns_booking', 'ns_booking_nonce' ); ?>
				<input type="hidden" name="ns_butik" value="<?php echo esc_attr( $s['store']['id'] ); ?>">
				<input type="hidden" name="ns_dato" value="<?php echo esc_attr( $s['day']->format( 'Y-m-d' ) ); ?>">
				<input type="hidden" name="ns_tid" value="<?php echo esc_attr( $s['time'] ); ?>">
				<input type="hidden" name="ns_vare" value="<?php echo esc_attr( $vare ); ?>">
				<p class="ns-hp" aria-hidden="true"><label>Website <input type="text" name="ns_website" tabindex="-1" autocomplete="off"></label></p>

				<fieldset class="ns-radios"><legend><?php esc_html_e( 'What would you like to do?', 'justsilver' ); ?></legend>
					<?php foreach ( NS_Bookings::purposes() as $k => $label ) : ?>
						<label><input type="radio" name="ns_formaal" value="<?php echo esc_attr( $k ); ?>" <?php checked( ( $old( 'ns_formaal' ) ?: $s['purpose'] ), $k ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<p><label for="ns_name"><?php esc_html_e( 'Name', 'justsilver' ); ?> *</label><input id="ns_name" name="ns_name" required autocomplete="name" value="<?php echo esc_attr( $old( 'ns_name' ) ); ?>"></p>
				<div class="ns-row">
					<p><label for="ns_email"><?php esc_html_e( 'Email', 'justsilver' ); ?> *</label><input id="ns_email" name="ns_email" type="email" required autocomplete="email" value="<?php echo esc_attr( $old( 'ns_email' ) ); ?>"></p>
					<p><label for="ns_phone"><?php esc_html_e( 'Phone', 'justsilver' ); ?> *</label><input id="ns_phone" name="ns_phone" type="tel" required autocomplete="tel" value="<?php echo esc_attr( $old( 'ns_phone' ) ); ?>"></p>
				</div>
				<p><label for="ns_note"><?php esc_html_e( 'Note', 'justsilver' ); ?></label><textarea id="ns_note" name="ns_note" rows="3"><?php echo esc_textarea( $old( 'ns_note' ) ); ?></textarea></p>
				<button type="submit" name="ns_booking_submit" value="1" class="ns-btn ns-btn--block ns-btn--shine">
					<?php /* translators: %s: date and time */ echo esc_html( sprintf( __( 'Confirm booking – %s', 'justsilver' ), wp_date( 'j. F', $s['day']->getTimestamp() ) . ' · ' . $s['time'] ) ); ?>
				</button>
			</form>
		</section>
		<?php endif; ?>
	</div>

	<aside class="ns-summary">
		<h3><?php esc_html_e( 'Your booking', 'justsilver' ); ?></h3>
		<div class="ns-summary__row"><span><?php esc_html_e( 'Store', 'justsilver' ); ?></span><span><?php echo esc_html( $s['store']['name'] ?? '–' ); ?></span></div>
		<div class="ns-summary__row"><span><?php esc_html_e( 'Day', 'justsilver' ); ?></span><span><?php echo $s['day'] ? esc_html( wp_date( 'j. F Y', $s['day']->getTimestamp() ) ) : '–'; ?></span></div>
		<div class="ns-summary__row"><span><?php esc_html_e( 'Time', 'justsilver' ); ?></span><span><?php echo esc_html( $s['time'] ?: '–' ); ?></span></div>
		<?php if ( $s['products'] ) : ?>
			<p><strong><?php esc_html_e( 'Items you want to see:', 'justsilver' ); ?></strong></p>
			<?php foreach ( $s['products'] as $pid ) : $p = wc_get_product( $pid ); if ( ! $p ) { continue; } ?>
				<div class="ns-mini">
					<div class="ns-mini__img"><?php echo $p->get_image( 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<span><?php echo esc_html( $p->get_name() ); ?><br><span class="ns-muted"><?php echo wp_kses_post( $p->get_price_html() ); ?></span></span>
				</div>
			<?php endforeach; ?>
			<p class="ns-note">
				<?php echo NS_Reservations::enabled()
					? esc_html__( '🔒 We reserve the items for you when you book, so nobody else can buy them before your visit.', 'justsilver' )
					: esc_html__( 'We will have the items ready for your visit.', 'justsilver' ); ?>
			</p>
		<?php endif; ?>
		<p class="ns-muted ns-small"><?php esc_html_e( 'You receive a confirmation with a calendar file and a reminder the day before. Remember valid photo ID when buying or selling.', 'justsilver' ); ?></p>
	</aside>
</div>
