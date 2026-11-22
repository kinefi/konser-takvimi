<?php
/**
 * UTM helpers: sanitizing, tagging of outbound/calendar links and incoming UTM aggregation.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes a UTM value: accents/Turkish letters transliterated, lowercase, spaces to hyphens,
 * only [a-z0-9._-], max 64 characters.
 */
function kt_sanitize_utm_value( $value ) {
	$value = strtolower( remove_accents( sanitize_text_field( (string) $value ) ) );
	$value = preg_replace( '/\s+/', '-', $value );
	$value = preg_replace( '/[^a-z0-9._\-]/', '', $value );
	return substr( $value, 0, 64 );
}

/**
 * Returns the effective UTM configuration (settings merged with defaults).
 * The source falls back to the site host when it is left empty in the settings.
 */
function kt_get_utm_settings() {
	$styles = kt_get_style_settings();
	$source = kt_sanitize_utm_value( $styles['utm_source'] );

	if ( '' === $source ) {
		$source = kt_sanitize_utm_value( wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	return array(
		'ticket_enabled'   => ! empty( $styles['utm_ticket_enabled'] ),
		'calendar_enabled' => ! empty( $styles['utm_calendar_enabled'] ),
		'capture_enabled'  => ! empty( $styles['utm_capture_enabled'] ),
		'source'           => $source,
		'medium'           => kt_sanitize_utm_value( $styles['utm_medium'] ),
		'campaign'         => kt_sanitize_utm_value( $styles['utm_campaign'] ),
	);
}

/**
 * Resolves the campaign value: the fixed campaign from the settings, or the district key when it is empty.
 */
function kt_resolve_utm_campaign( $district_key = '' ) {
	$utm = kt_get_utm_settings();
	if ( '' !== $utm['campaign'] ) {
		return $utm['campaign'];
	}
	return kt_sanitize_utm_value( kt_normalize_district_key( $district_key ) );
}

/**
 * UTM parameters for outbound ticket links. Returns an empty array when ticket UTM is disabled.
 */
function kt_get_ticket_utm_params( $district_key = '', $content = 'ticket-button' ) {
	$utm = kt_get_utm_settings();
	if ( ! $utm['ticket_enabled'] ) {
		return array();
	}

	return array_filter(
		array(
			'utm_source'   => $utm['source'],
			'utm_medium'   => $utm['medium'],
			'utm_campaign' => kt_resolve_utm_campaign( $district_key ),
			'utm_content'  => kt_sanitize_utm_value( $content ),
		),
		'strlen'
	);
}

/**
 * UTM parameters for the page link placed in "Add to Calendar" event descriptions.
 * $channel is 'google-calendar' or 'ics-file'. Returns an empty array when calendar UTM is disabled.
 */
function kt_get_calendar_utm_params( $channel, $district_key = '', $session_number = 1 ) {
	$utm = kt_get_utm_settings();
	if ( ! $utm['calendar_enabled'] ) {
		return array();
	}

	return array_filter(
		array(
			'utm_source'   => kt_sanitize_utm_value( $channel ),
			'utm_medium'   => 'calendar',
			'utm_campaign' => kt_resolve_utm_campaign( $district_key ),
			'utm_content'  => 'session-' . max( 1, intval( $session_number ) ),
		),
		'strlen'
	);
}

/**
 * Appends utm_* parameters to an http(s) URL. If the URL already carries any utm_* parameter it is returned
 * unchanged (all-or-nothing), so manually tagged links and ticket platforms keep their own tagging. Non-http(s) and relative URLs are returned unchanged.
 */
function kt_append_utm_params( $url, $params ) {
	if ( empty( $url ) || empty( $params ) || ! is_array( $params ) ) {
		return $url;
	}

	$parts = wp_parse_url( $url );
	if ( empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return $url;
	}

	$existing = array();
	if ( ! empty( $parts['query'] ) ) {
		wp_parse_str( $parts['query'], $existing );
	}

	// All-or-nothing: if the destination already carries any utm_* parameter (for example the ticket platform's
	// own tagging), leave the URL alone so we never create a hybrid of two tagging schemes.
	foreach ( array_keys( $existing ) as $existing_name ) {
		if ( 0 === strpos( strtolower( (string) $existing_name ), 'utm_' ) ) {
			return $url;
		}
	}

	$to_add = array();
	foreach ( $params as $name => $value ) {
		if ( 0 !== strpos( $name, 'utm_' ) || '' === (string) $value ) {
			continue;
		}
		$to_add[ $name ] = $value;
	}

	if ( empty( $to_add ) ) {
		return $url;
	}

	return add_query_arg( $to_add, $url );
}

/**
 * Adds one hit to the UTM breakdown of a district stats row.
 * The number of distinct source/medium/campaign combinations is capped per district; once the cap is reached,
 * new combinations are merged into a single "(other)" bucket so that arbitrary utm_* values sent by
 * a hostile client cannot bloat the stats option.
 */
function kt_add_utm_hit( $utm_rows, $utm, $type, $is_unique ) {
	if ( ! is_array( $utm_rows ) ) {
		$utm_rows = array();
	}

	$source   = isset( $utm['source'] ) ? $utm['source'] : '';
	$medium   = isset( $utm['medium'] ) ? $utm['medium'] : '';
	$campaign = isset( $utm['campaign'] ) ? $utm['campaign'] : '';

	if ( '' === $source && '' === $medium && '' === $campaign ) {
		return $utm_rows;
	}

	// Sanitized values only contain [a-z0-9._-], so "|" is a safe separator.
	$combo_key = $source . '|' . $medium . '|' . $campaign;

	if ( ! isset( $utm_rows[ $combo_key ] ) && count( $utm_rows ) >= KT_UTM_MAX_COMBOS ) {
		$combo_key = '(other)';
		$source    = '(other)';
		$medium    = '';
		$campaign  = '';
	}

	if ( ! isset( $utm_rows[ $combo_key ] ) ) {
		$utm_rows[ $combo_key ] = array(
			'source'   => $source,
			'medium'   => $medium,
			'campaign' => $campaign,
			'views'    => 0,
			'visitors' => 0,
			'clicks'   => 0,
			'click_visitors' => 0,
		);
	}

	if ( 'views' === $type ) {
		$utm_rows[ $combo_key ]['views'] = intval( $utm_rows[ $combo_key ]['views'] ) + 1;
		if ( $is_unique ) {
			$utm_rows[ $combo_key ]['visitors'] = intval( $utm_rows[ $combo_key ]['visitors'] ) + 1;
		}
	} elseif ( 'clicks' === $type ) {
		$utm_rows[ $combo_key ]['clicks'] = intval( $utm_rows[ $combo_key ]['clicks'] ) + 1;
		if ( $is_unique ) {
			$utm_rows[ $combo_key ]['click_visitors'] = ( isset( $utm_rows[ $combo_key ]['click_visitors'] ) ? intval( $utm_rows[ $combo_key ]['click_visitors'] ) : 0 ) + 1;
		}
	}

	return $utm_rows;
}

/**
 * Flattens the per-district UTM breakdown into rows (all time), sorted by unique visitors then page views.
 * Used by the admin statistics tab and by the HTML/CSV/e-mail reports.
 */
function kt_get_utm_report_rows( $stats ) {
	$utm_rows = array();

	if ( ! is_array( $stats ) ) {
		return $utm_rows;
	}

	foreach ( $stats as $district_key => $stat_row ) {
		if ( empty( $stat_row['utm'] ) || ! is_array( $stat_row['utm'] ) ) {
			continue;
		}
		foreach ( $stat_row['utm'] as $utm_item ) {
			$visitors       = isset( $utm_item['visitors'] ) ? intval( $utm_item['visitors'] ) : 0;
			$views          = isset( $utm_item['views'] ) ? intval( $utm_item['views'] ) : 0;
			$clicks         = isset( $utm_item['clicks'] ) ? intval( $utm_item['clicks'] ) : 0;
			$click_visitors = isset( $utm_item['click_visitors'] ) ? intval( $utm_item['click_visitors'] ) : 0;

			$utm_rows[] = array(
				'district'       => isset( $stat_row['label'] ) ? $stat_row['label'] : $district_key,
				'district_key'   => $district_key,
				'source'         => isset( $utm_item['source'] ) ? $utm_item['source'] : '',
				'medium'         => isset( $utm_item['medium'] ) ? $utm_item['medium'] : '',
				'campaign'       => isset( $utm_item['campaign'] ) ? $utm_item['campaign'] : '',
				'visitors'       => $visitors,
				'views'          => $views,
				'clicks'         => $clicks,
				'click_visitors' => $click_visitors,
				'ctr'            => $views > 0 ? round( ( $clicks / $views ) * 100, 1 ) : 0.0,
				'conversion'     => kt_conversion_rate( $click_visitors, $visitors ),
			);
		}
	}

	usort(
		$utm_rows,
		function ( $a, $b ) {
			if ( $a['visitors'] === $b['visitors'] ) {
				return $b['views'] - $a['views'];
			}
			return $b['visitors'] - $a['visitors'];
		}
	);

	return $utm_rows;
}
