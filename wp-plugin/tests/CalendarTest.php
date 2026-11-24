<?php
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase {

	public function test_separate_visits_each_get_the_full_width(): void {
		$lanes = NS_Admin::lanes( array( array( 'time' => '10:00' ), array( 'time' => '10:30' ), array( 'time' => '11:00' ) ), 30 );
		$this->assertSame( array( array( 'lane' => 0, 'lanes' => 1 ), array( 'lane' => 0, 'lanes' => 1 ), array( 'lane' => 0, 'lanes' => 1 ) ), $lanes );
	}

	public function test_visits_at_the_same_time_sit_side_by_side(): void {
		$lanes = NS_Admin::lanes( array( array( 'time' => '10:00' ), array( 'time' => '10:00' ), array( 'time' => '11:00' ) ), 30 );
		$this->assertSame( array( 'lane' => 0, 'lanes' => 2 ), $lanes[0] );
		$this->assertSame( array( 'lane' => 1, 'lanes' => 2 ), $lanes[1] );
		$this->assertSame( array( 'lane' => 0, 'lanes' => 1 ), $lanes[2] );
	}

	public function test_partly_overlapping_visits_reuse_free_lanes(): void {
		// 45-minute visits: 10:00 and 10:30 overlap, 10:45 can reuse lane 0.
		$lanes = NS_Admin::lanes( array( array( 'time' => '10:00' ), array( 'time' => '10:30' ), array( 'time' => '10:45' ) ), 45 );
		$this->assertSame( array( 0, 1, 0 ), array_column( $lanes, 'lane' ) );
		$this->assertSame( array( 2, 2, 2 ), array_column( $lanes, 'lanes' ) );
	}
}
