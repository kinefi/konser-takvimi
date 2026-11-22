<?php
/**
 * MusicEvent JSON-LD output.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns all upcoming active concert records for a given district / slug.
 */
function kt_get_upcoming_events_for_target( $target ) {
	$target_slug = sanitize_title( $target );
	if ( empty( $target_slug ) ) {
		return array();
	}

	$base_target = preg_replace( '/-(konseri|konser)$/', '', $target_slug );
	$all_events  = get_option( KT_EVENTS_OPT, array() );
	if ( ! is_array( $all_events ) || empty( $all_events ) ) {
		return array();
	}

	$now_ts  = kt_get_site_timestamp();
	$matched = array();

	foreach ( $all_events as $event ) {
		$end_ts = kt_get_event_end_timestamp( $event );
		if ( false === $end_ts || $end_ts < $now_ts ) {
			continue;
		}

		$ev_slug      = isset( $event['slug'] ) ? sanitize_title( $event['slug'] ) : '';
		$ev_city_slug = isset( $event['city'] ) ? sanitize_title( $event['city'] ) : '';
		$ev_base_slug = preg_replace( '/-(konseri|konser)$/', '', $ev_slug );

		if (
			$ev_slug === $target_slug ||
			$ev_city_slug === $target_slug ||
			( ! empty( $base_target ) && ( $ev_base_slug === $base_target || $ev_city_slug === $base_target ) )
		) {
			$matched[] = $event;
		}
	}

	usort( $matched, 'kt_sort_events_asc' );
	return $matched;
}

/**
 * Converts a single concert record into a Google MusicEvent JSON-LD object.
 */
function kt_build_music_event_schema_item( $event, $performer_name, $ticket_url = '', $image_url = '', $page_url = '' ) {
	if ( empty( $event['datetime'] ) || empty( $event['city'] ) ) {
		return null;
	}

	$city_name = sanitize_text_field( $event['city'] );
	$ev_slug   = isset( $event['slug'] ) ? sanitize_title( $event['slug'] ) : sanitize_title( $city_name . '-konseri' );
	$event_url = ! empty( $page_url ) ? $page_url : home_url( '/' . ltrim( $ev_slug, '/' ) . '/' );

	$start_iso = sanitize_text_field( $event['datetime'] );
	$dt_parts  = explode( 'T', $start_iso );
	$last_start = $start_iso;

	if ( ! empty( $event['time2'] ) && preg_match( '/^\d{2}:\d{2}$/', $event['time2'] ) && ! empty( $dt_parts[0] ) ) {
		$last_start = kt_second_session_iso( $event );
	}

	// Google expects endDate after startDate: assume one hour after the start of the last session (same as the calendar entries).
	$end_iso = $last_start;
	try {
		$end_dt = new DateTime( $last_start );
		$end_dt->modify( '+1 hour' );
		$end_iso = $end_dt->format( 'Y-m-d\TH:i:sP' );
	} catch ( Exception $e ) {
		$end_iso = $last_start;
	}

	$item = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'MusicEvent',
		'name'                => sprintf( /* translators: 1: performer, 2: city */ __( '%1$s — %2$s Konseri', 'konser-takvimi' ), $performer_name, $city_name ),
		'description'         => sprintf( /* translators: 1: performer, 2: city */ __( '%1$s %2$s konseri etkinlik ve bilet bilgileri.', 'konser-takvimi' ), $performer_name, $city_name ),
		'startDate'           => $start_iso,
		'endDate'             => $end_iso,
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'location'            => array(
			'@type'   => 'Place',
			'name'    => $city_name,
			'address' => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => $city_name,
				'addressCountry'  => 'TR',
			),
		),
		'performer'           => array(
			'@type' => 'MusicGroup',
			'name'  => $performer_name,
		),
		'organizer'           => array(
			'@type' => 'Organization',
			'name'  => $performer_name,
			'url'   => home_url( '/' ),
		),
		'url'                 => esc_url_raw( $event_url ),
	);

	if ( ! empty( $image_url ) ) {
		$item['image'] = array( esc_url_raw( $image_url ) );
	}

	$offer_url      = ! empty( $ticket_url ) ? $ticket_url : $event_url;
	$item['offers'] = array(
		'@type'         => 'Offer',
		'url'           => esc_url_raw( $offer_url ),
		'availability'  => 'https://schema.org/InStock',
		'validFrom'     => kt_local_to_iso( gmdate( 'Y' ) . '-01-01', '00:00' ),
		'priceCurrency' => 'TRY',
	);

	return $item;
}

/**
 * Outputs Google MusicEvent JSON-LD in <head> on pages that contain the Concert Calendar or District Redirector blocks/shortcodes.
 */
add_action( 'wp_head', 'kt_output_music_event_json_ld', 20 );
function kt_output_music_event_json_ld() {
	if ( is_admin() || ! is_singular() ) {
		return;
	}

	$styles = kt_get_style_settings();
	if ( empty( $styles['schema_enabled'] ) ) {
		return;
	}

	global $post;
	if ( ! $post || empty( $post->post_content ) ) {
		return;
	}

	$has_calendar = has_block( 'konser-takvimi/takvim', $post )
		|| has_shortcode( $post->post_content, 'konser_takvimi' )
		|| has_shortcode( $post->post_content, 'subadap_konserler' );
	$has_district = has_block( 'konser-takvimi/ilce-yonlendir', $post )
		|| has_shortcode( $post->post_content, 'konser_ilce_yonlendir' );

	if ( ! $has_calendar && ! $has_district ) {
		return;
	}

	$performer_name = ! empty( $styles['schema_performer'] )
		? sanitize_text_field( $styles['schema_performer'] )
		: wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	$ticket_url = ! empty( $styles['ticket_btn_url'] ) ? $styles['ticket_btn_url'] : '';
	$image_url  = get_the_post_thumbnail_url( $post->ID, 'full' );
	if ( empty( $image_url ) && ! empty( $styles['ticket_btn_img'] ) ) {
		$image_url = $styles['ticket_btn_img'];
	}

	$schema_items = array();
	$seen_keys    = array();

	if ( $has_district ) {
		$district_keys = array();

		if ( has_block( 'konser-takvimi/ilce-yonlendir', $post ) ) {
			$blocks = parse_blocks( $post->post_content );
			foreach ( $blocks as $blk ) {
				if ( isset( $blk['blockName'] ) && 'konser-takvimi/ilce-yonlendir' === $blk['blockName'] ) {
					$district_keys[] = ! empty( $blk['attrs']['district'] ) ? $blk['attrs']['district'] : $post->post_name;
				}
			}
		}

		$pattern = get_shortcode_regex( array( 'konser_ilce_yonlendir' ) );
		if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts            = shortcode_atts( array( 'district' => '' ), shortcode_parse_atts( $match[3] ), 'konser_ilce_yonlendir' );
				$district_keys[] = ! empty( $atts['district'] ) ? $atts['district'] : $post->post_name;
			}
		}

		foreach ( array_unique( $district_keys ) as $lookup_key ) {
			$events = kt_get_upcoming_events_for_target( $lookup_key );
			foreach ( $events as $ev ) {
				$dedup = $ev['datetime'] . '|' . $ev['slug'];
				if ( isset( $seen_keys[ $dedup ] ) ) {
					continue;
				}
				$seen_keys[ $dedup ] = true;
				$built               = kt_build_music_event_schema_item( $ev, $performer_name, $ticket_url, $image_url, get_permalink( $post->ID ) );
				if ( $built ) {
					$schema_items[] = $built;
				}
			}
		}
	}

	if ( $has_calendar ) {
		$all_events = get_option( KT_EVENTS_OPT, array() );
		if ( is_array( $all_events ) ) {
			$now_ts   = kt_get_site_timestamp();
			$upcoming = array();
			foreach ( $all_events as $ev ) {
				$end_ts = kt_get_event_end_timestamp( $ev );
				if ( false !== $end_ts && $end_ts >= $now_ts ) {
					$upcoming[] = $ev;
				}
			}
			usort( $upcoming, 'kt_sort_events_asc' );
			$max_items = max( 1, intval( $styles['max_items'] ) );
			$upcoming  = array_slice( $upcoming, 0, $max_items );

			foreach ( $upcoming as $ev ) {
				$dedup = $ev['datetime'] . '|' . $ev['slug'];
				if ( isset( $seen_keys[ $dedup ] ) ) {
					continue;
				}
				$seen_keys[ $dedup ] = true;
				$built               = kt_build_music_event_schema_item( $ev, $performer_name, $ticket_url, $image_url, '' );
				if ( $built ) {
					$schema_items[] = $built;
				}
			}
		}
	}

	if ( empty( $schema_items ) ) {
		return;
	}

	$payload = ( 1 === count( $schema_items ) ) ? $schema_items[0] : $schema_items;
	echo "\n<!-- Konser Takvimi Google MusicEvent JSON-LD -->\n";
	echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

/**
 * Checks whether a given district / slug has an upcoming active concert.
 */
function kt_has_upcoming_concert_for_target( $target ) {
	$events = kt_get_upcoming_events_for_target( $target );
	return ! empty( $events );
}
