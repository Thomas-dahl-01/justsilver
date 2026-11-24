<?php
/**
 * Integration smoke test against a real WordPress + WooCommerce (run inside wp-dev):
 *   docker compose run --rm -T cli eval-file /var/www/html/wp-content/plugins/../../smoke-test.php
 * or simply:  sh smoke-test.sh
 * Creates test data, checks the result, and removes the test data again.
 */
$fail = 0;
$ok   = function ( $cond, $label ) use ( &$fail ) {
	echo ( $cond ? '  ✓ ' : '  ✗ ' ) . $label . PHP_EOL;
	$fail += $cond ? 0 : 1;
};
$cleanup = array();

echo "Reservation\n";
$pid = wc_get_product_id_by_sku( 'JS-00007' );
$p   = wc_get_product( $pid );
$p->set_manage_stock( true );
$p->set_stock_quantity( 5 );
$p->save();
$store = NS_Stores::all()[0]->ID;
$bid   = wp_insert_post( array( 'post_type' => 'ns_booking', 'post_status' => 'publish', 'post_title' => 'Smoke', 'meta_input' => array(
	'_ns_store' => $store, '_ns_date' => wp_date( 'Y-m-d', strtotime( '+1 day' ) ), '_ns_time' => '11:00', '_ns_purpose' => 'koeb',
	'_ns_name' => 'Smoke Test', '_ns_email' => 'smoke@example.test', '_ns_phone' => '12345678', '_ns_products' => array( $pid ), '_ns_status' => 'booket',
) ) );
$cleanup[] = $bid;
do_action( 'justsilver_booking_created', $bid );
$ok( 4 === wc_get_product( $pid )->get_stock_quantity(), 'booking reserves 1 item (stock 5 → 4)' );
$ok( array( $pid ) === array_map( 'intval', (array) get_post_meta( $bid, '_ns_reserved', true ) ), 'reservation stored on booking' );
NS_Bookings::set_status( $bid, 'aflyst' );
$ok( 5 === wc_get_product( $pid )->get_stock_quantity(), 'cancelling releases it (stock back to 5)' );
NS_Bookings::set_status( $bid, 'booket' );
do_action( 'justsilver_booking_created', $bid );
NS_Bookings::set_status( $bid, 'gennemfoert' );
$ok( 4 === wc_get_product( $pid )->get_stock_quantity() && ! get_post_meta( $bid, '_ns_reserved', true ), 'completed visit counts as sold (stock stays 4)' );
wc_update_product_stock( $pid, 5, 'set' );

echo "Expired reservations\n";
$old = wp_insert_post( array( 'post_type' => 'ns_booking', 'post_status' => 'publish', 'post_title' => 'Old', 'meta_input' => array(
	'_ns_store' => $store, '_ns_date' => wp_date( 'Y-m-d', strtotime( '-3 days' ) ), '_ns_time' => '10:00', '_ns_status' => 'booket', '_ns_products' => array( $pid ),
) ) );
$cleanup[] = $old;
do_action( 'justsilver_booking_created', $old );
$ok( 4 === wc_get_product( $pid )->get_stock_quantity(), 'old booking reserved an item' );
$ok( NS_Reservations::release_expired() >= 1 && 5 === wc_get_product( $pid )->get_stock_quantity(), 'daily job releases visits that passed' );

echo "Reminder + calendar file\n";
NS_Bookings::set_status( $bid, 'booket' );
delete_post_meta( $bid, '_ns_reminded' );
$opts = get_option( 'ns_settings' );
update_option( 'ns_settings', array_merge( $opts, array( 'reminder_hour' => '0' ) ) );
$ok( NS_Reminders::run() >= 1 && get_post_meta( $bid, '_ns_reminded', true ), 'reminder sent for tomorrow’s visit' );
$ok( 0 === NS_Reminders::run(), 'reminder is only sent once' );
update_option( 'ns_settings', $opts );
$ics = file_get_contents( NS_Bookings::ics_file( NS_Bookings::details( $bid ) ) );
$ok( str_contains( $ics, 'BEGIN:VEVENT' ) && str_contains( $ics, 'LOCATION:' ), 'calendar file is generated' );

echo "Price alerts\n";
$a1 = wp_insert_post( array( 'post_type' => 'ns_alert', 'post_status' => 'publish', 'post_title' => 'a', 'meta_input' => array( '_ns_email' => 'a@example.test', '_ns_target' => 10.0 ) ) );
$a2 = wp_insert_post( array( 'post_type' => 'ns_alert', 'post_status' => 'publish', 'post_title' => 'b', 'meta_input' => array( '_ns_email' => 'b@example.test', '_ns_target' => 8.0 ) ) );
$cleanup[] = $a2;
$before = NS_Silver_Price::per_kg();
NS_Silver_Price::set( 9900, 'smoke' );
$ok( ! get_post( $a1 ) && get_post( $a2 ), 'alert at 10 kr/g fired at 9,90 – alert at 8 kr/g still waiting' );
NS_Silver_Price::set( $before, 'smoke' );

echo "Blocks\n";
foreach ( array( 'forside' => 'ns-hero', 'booking' => 'ns-booking', 'pris' => 'ns-ticker', 'prisgraf' => 'ns-chart', 'prisalarm' => 'ns-alert', 'saelg' => 'ns-sell' ) as $slug => $class ) {
	$html = render_block( array( 'blockName' => 'justsilver/' . $slug, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	$ok( str_contains( $html, $class ), "block justsilver/$slug renders" );
}

foreach ( $cleanup as $id ) {
	wp_delete_post( $id, true );
}
// Remove smoke history points.
update_option( 'ns_price_history', array_values( array_filter( NS_Silver_Price::history(), fn( $h ) => 'smoke' !== $h['s'] ) ), false );
echo $fail ? "\n$fail FAILED\n" : "\nAll checks passed\n";
