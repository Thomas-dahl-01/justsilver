<?php
use PHPUnit\Framework\TestCase;

final class IcsAndChartTest extends TestCase {

	private function ics(): string {
		return NS_ICS::build( array(
			'uid'         => 'booking-42@example.test',
			'start'       => new DateTimeImmutable( '2026-12-01 10:30', new DateTimeZone( 'Europe/Copenhagen' ) ),
			'minutes'     => 30,
			'summary'     => 'Besøg hos JustSilver København',
			'location'    => 'Sølvgade 12, 1620 København V',
			'description' => 'Se og købe sølv – Valcambi sølvbarre 100 g; Maple Leaf 1 oz sølvmønt 2026, Britannia 1 oz sølvmønt 2026 og flere varer',
		) );
	}

	public function test_ics_has_utc_times_and_duration(): void {
		$ics = $this->ics();
		$this->assertStringContainsString( "DTSTART:20261201T093000Z\r\n", $ics ); // 10:30 CET = 09:30 UTC
		$this->assertStringContainsString( "DTEND:20261201T100000Z\r\n", $ics );
		$this->assertStringStartsWith( "BEGIN:VCALENDAR\r\n", $ics );
		$this->assertStringEndsWith( "END:VCALENDAR\r\n", $ics );
	}

	public function test_ics_escapes_special_characters(): void {
		$this->assertStringContainsString( 'LOCATION:Sølvgade 12\, 1620 København V', $this->ics() );
		$this->assertSame( 'a\;b\,c\nd', NS_ICS::escape( "a;b,c\nd" ) );
	}

	public function test_ics_lines_are_folded_to_75_octets(): void {
		foreach ( explode( "\r\n", $this->ics() ) as $line ) {
			$this->assertLessThanOrEqual( 75, strlen( $line ), $line );
		}
		$this->assertStringContainsString( "\r\n ", $this->ics() ); // the long description was folded
	}

	public function test_daily_series_keeps_last_price_per_day(): void {
		$tz  = new DateTimeZone( 'UTC' );
		$now = strtotime( '2026-12-10 12:00 UTC' );
		$h   = array(
			array( 't' => strtotime( '2026-12-09 09:00 UTC' ), 'p' => 10000 ),
			array( 't' => strtotime( '2026-12-09 15:00 UTC' ), 'p' => 10200 ),
			array( 't' => strtotime( '2026-12-10 09:00 UTC' ), 'p' => 10500 ),
			array( 't' => strtotime( '2026-10-01 09:00 UTC' ), 'p' => 9000 ), // outside 30 days
		);
		$this->assertSame(
			array( array( 'd' => '2026-12-09', 'v' => 10.2 ), array( 'd' => '2026-12-10', 'v' => 10.5 ) ),
			NS_Chart::daily( $h, 30, $now, $tz )
		);
	}

	public function test_chart_geometry_maps_min_and_max_to_edges(): void {
		$g = NS_Chart::geometry( array( 10, 12, 11 ), 200, 100, 0 );
		$this->assertSame( array( array( 0.0, 100.0 ), array( 100.0, 0.0 ), array( 200.0, 50.0 ) ), $g['points'] );
		$this->assertSame( 'M0,100 L100,0 L200,50', $g['line'] );
		$this->assertSame( 10, $g['min'] );
		$this->assertSame( 12, $g['max'] );
		$this->assertSame( '', NS_Chart::geometry( array( 10 ), 200, 100 )['line'] );
	}
}
