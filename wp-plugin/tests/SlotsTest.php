<?php
use PHPUnit\Framework\TestCase;

final class SlotsTest extends TestCase {

	private DateTimeZone $tz;

	protected function setUp(): void {
		$this->tz = new DateTimeZone( 'Europe/Copenhagen' );
	}

	private function slots( array $taken = array(), string $earliest = '2026-01-01 00:00', int $capacity = 2, int $minutes = 30 ): array {
		return NS_Stores::generate_slots( '2026-12-01', '10:00', '12:00', $minutes, $capacity, $taken, new DateTimeImmutable( $earliest, $this->tz ), $this->tz );
	}

	public function test_all_slots_fit_inside_opening_hours(): void {
		$this->assertSame( array( '10:00' => 2, '10:30' => 2, '11:00' => 2, '11:30' => 2 ), $this->slots() );
	}

	public function test_last_slot_must_end_before_closing(): void {
		$this->assertSame( array( '10:00', '10:45' ), array_keys( $this->slots( array(), '2026-01-01 00:00', 1, 45 ) ) );
	}

	public function test_bookings_reduce_capacity_and_full_slots_disappear(): void {
		$slots = $this->slots( array( '10:00' => 1, '10:30' => 2 ) );
		$this->assertSame( 1, $slots['10:00'] );
		$this->assertArrayNotHasKey( '10:30', $slots );
	}

	public function test_slots_inside_the_notice_period_are_hidden(): void {
		$this->assertSame( array( '11:00', '11:30' ), array_keys( $this->slots( array(), '2026-12-01 10:40' ) ) );
	}
}
