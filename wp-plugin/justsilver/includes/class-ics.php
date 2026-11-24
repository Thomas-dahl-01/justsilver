<?php
/**
 * Calendar file (.ics, RFC 5545) for a booking – attached to the confirmation mail.
 * Pure string building, unit tested in tests/IcsTest.php.
 */
defined( 'ABSPATH' ) || exit;

class NS_ICS {

	/**
	 * @param array{uid:string,start:DateTimeImmutable,minutes:int,summary:string,location:string,description:string,organizer?:string} $e
	 */
	public static function build( array $e ): string {
		$utc   = new DateTimeZone( 'UTC' );
		$start = $e['start']->setTimezone( $utc );
		$end   = $start->modify( '+' . (int) $e['minutes'] . ' minutes' );
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//JustSilver//Booking//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . self::escape( $e['uid'] ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART:' . $start->format( 'Ymd\THis\Z' ),
			'DTEND:' . $end->format( 'Ymd\THis\Z' ),
			'SUMMARY:' . self::escape( $e['summary'] ),
			'LOCATION:' . self::escape( $e['location'] ),
			'DESCRIPTION:' . self::escape( $e['description'] ),
		);
		if ( ! empty( $e['organizer'] ) ) {
			$lines[] = 'ORGANIZER:mailto:' . $e['organizer'];
		}
		array_push( $lines, 'BEGIN:VALARM', 'TRIGGER:-PT2H', 'ACTION:DISPLAY', 'DESCRIPTION:' . self::escape( $e['summary'] ), 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR' );
		return implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n";
	}

	public static function escape( string $text ): string {
		return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), $text );
	}

	/** Fold lines longer than 75 octets (RFC 5545 §3.1), without splitting UTF-8 characters. */
	public static function fold( string $line ): string {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}
		$out   = '';
		$chunk = '';
		foreach ( preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
			$limit = '' === $out ? 75 : 74;
			if ( strlen( $chunk . $char ) > $limit ) {
				$out  .= ( '' === $out ? '' : "\r\n " ) . $chunk;
				$chunk = '';
			}
			$chunk .= $char;
		}
		return $out . "\r\n " . $chunk;
	}
}
