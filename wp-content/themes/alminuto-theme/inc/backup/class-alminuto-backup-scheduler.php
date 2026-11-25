<?php
/**
 * Backup scheduler supporting hours, days, weeks, and months with exact execution time.
 *
 * @package alminuto-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alminuto_Backup_Scheduler {

	const OPTION_KEY = 'alminuto_backup_schedule';
	const CRON_HOOK  = 'alminuto_backup_cron_event';

	/**
	 * Returns schedule settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = [
			'enabled'       => false,
			'unit'          => 'days', // hours | days | weeks | months.
			'interval'      => 1,
			'time'          => '03:00',
			'weekday'       => 1, // 1 (Mon) .. 7 (Sun).
			'monthday'      => 1, // 1 .. 28.
			'include_db'    => true,
			'include_media' => true,
			'retention'     => 7,
		];
		$raw = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		return array_merge( $defaults, $raw );
	}

	/**
	 * Sanitizes and saves schedule settings, then updates WP-Cron.
	 *
	 * @param array $input Raw input array.
	 * @return array Sanitized settings.
	 */
	public static function save_settings( array $input ) {
		$allowed_units = [ 'hours', 'days', 'weeks', 'months' ];
		$unit          = isset( $input['unit'] ) && in_array( $input['unit'], $allowed_units, true )
			? $input['unit']
			: 'days';

		$interval = isset( $input['interval'] ) ? max( 1, min( 365, (int) $input['interval'] ) ) : 1;

		$time = isset( $input['time'] ) ? sanitize_text_field( (string) $input['time'] ) : '03:00';
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			$time = '03:00';
		}

		$weekday  = isset( $input['weekday'] ) ? max( 1, min( 7, (int) $input['weekday'] ) ) : 1;
		$monthday = isset( $input['monthday'] ) ? max( 1, min( 28, (int) $input['monthday'] ) ) : 1;

		$settings = [
			'enabled'       => ! empty( $input['enabled'] ),
			'unit'          => $unit,
			'interval'      => $interval,
			'time'          => $time,
			'weekday'       => $weekday,
			'monthday'      => $monthday,
			'include_db'    => ! empty( $input['include_db'] ),
			'include_media' => ! empty( $input['include_media'] ),
			'retention'     => isset( $input['retention'] ) ? max( 0, min( 365, (int) $input['retention'] ) ) : 7,
		];

		update_option( self::OPTION_KEY, $settings, false );
		self::reschedule( $settings );

		return $settings;
	}

	/**
	 * Registers custom intervals in WordPress cron_schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function register_cron_schedules( $schedules ) {
		$settings = self::get_settings();
		$seconds  = self::interval_in_seconds( $settings['unit'], $settings['interval'] );

		$schedules['alminuto_backup_custom'] = [
			'interval' => $seconds,
			'display'  => sprintf( 'AlMinuto Backup (%d %s)', (int) $settings['interval'], $settings['unit'] ),
		];
		return $schedules;
	}

	/**
	 * Returns approximate interval in seconds for a unit + interval.
	 *
	 * @param string $unit     hours|days|weeks|months.
	 * @param int    $interval Multiplier.
	 * @return int
	 */
	public static function interval_in_seconds( $unit, $interval ) {
		$interval = max( 1, (int) $interval );
		switch ( $unit ) {
			case 'hours':
				return $interval * HOUR_IN_SECONDS;
			case 'weeks':
				return $interval * WEEK_IN_SECONDS;
			case 'months':
				return $interval * 30 * DAY_IN_SECONDS;
			case 'days':
			default:
				return $interval * DAY_IN_SECONDS;
		}
	}

	/**
	 * Clears any pending backup cron event and schedules the next one if enabled.
	 *
	 * @param array|null $settings Optional settings override.
	 * @param int|null   $after_ts Optional reference timestamp (defaults to now).
	 * @return int|false Next scheduled UTC timestamp or false if disabled.
	 */
	public static function reschedule( $settings = null, $after_ts = null ) {
		wp_clear_scheduled_hook( self::CRON_HOOK );

		if ( ! is_array( $settings ) ) {
			$settings = self::get_settings();
		}

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		$next_ts = self::compute_next_run( $settings, $after_ts ?: time() );
		wp_schedule_single_event( $next_ts, self::CRON_HOOK );
		return $next_ts;
	}

	/**
	 * Returns the currently scheduled UTC timestamp, or false if not scheduled.
	 *
	 * @return int|false
	 */
	public static function get_next_scheduled() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Computes the next execution UTC timestamp in the site's timezone.
	 *
	 * @param array $settings Schedule settings.
	 * @param int   $after_ts Reference UTC timestamp (must be strictly in the future of this).
	 * @return int UTC timestamp.
	 */
	public static function compute_next_run( array $settings, $after_ts ) {
		$tz       = wp_timezone();
		$now      = ( new DateTimeImmutable( '@' . (int) $after_ts ) )->setTimezone( $tz );
		$interval = max( 1, (int) $settings['interval'] );

		list( $hour, $minute ) = array_map( 'intval', explode( ':', $settings['time'] ) );

		switch ( $settings['unit'] ) {
			case 'hours':
				$candidate = $now->setTime( (int) $now->format( 'G' ), $minute, 0 );
				if ( $candidate->getTimestamp() <= $after_ts ) {
					$candidate = $candidate->modify( '+' . $interval . ' hours' );
				}
				return $candidate->getTimestamp();

			case 'weeks':
				$target_dow = max( 1, min( 7, (int) $settings['weekday'] ) );
				$current_dow = (int) $now->format( 'N' );
				$diff_days   = ( $target_dow - $current_dow + 7 ) % 7;
				$candidate   = $now->modify( '+' . $diff_days . ' days' )->setTime( $hour, $minute, 0 );
				if ( $candidate->getTimestamp() <= $after_ts ) {
					$candidate = $candidate->modify( '+' . $interval . ' weeks' );
				}
				return $candidate->getTimestamp();

			case 'months':
				$target_dom = max( 1, min( 28, (int) $settings['monthday'] ) );
				$candidate  = $now->setDate( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), $target_dom )
					->setTime( $hour, $minute, 0 );
				if ( $candidate->getTimestamp() <= $after_ts ) {
					$candidate = $candidate->modify( '+' . $interval . ' months' );
				}
				return $candidate->getTimestamp();

			case 'days':
			default:
				$candidate = $now->setTime( $hour, $minute, 0 );
				if ( $candidate->getTimestamp() <= $after_ts ) {
					$candidate = $candidate->modify( '+' . $interval . ' days' );
				}
				return $candidate->getTimestamp();
		}
	}

	/**
	 * Generates upcoming scheduled dates (YYYY-MM-DD => ['HH:MM', ...]) for calendar display.
	 *
	 * @param array $settings Schedule settings.
	 * @param int   $limit    Max occurrences to project.
	 * @return array<string, string[]>
	 */
	public static function project_upcoming_dates( array $settings, $limit = 60 ) {
		if ( empty( $settings['enabled'] ) ) {
			return [];
		}

		$tz     = wp_timezone();
		$cursor = time();
		$dates  = [];

		for ( $i = 0; $i < $limit; $i++ ) {
			$next_ts = self::compute_next_run( $settings, $cursor );
			if ( $next_ts <= $cursor ) {
				break;
			}
			$dt  = ( new DateTimeImmutable( '@' . $next_ts ) )->setTimezone( $tz );
			$key = $dt->format( 'Y-m-d' );
			if ( ! isset( $dates[ $key ] ) ) {
				$dates[ $key ] = [];
			}
			$dates[ $key ][] = $dt->format( 'H:i' );
			$cursor          = $next_ts;
		}

		return $dates;
	}
}
