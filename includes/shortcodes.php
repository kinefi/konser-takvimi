<?php
/**
 * Ticket button and concert grid shortcodes.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated 3.6.0 Use the 'konser-takvimi/bilet-butonu' Gutenberg block instead.
 */
add_shortcode( 'konser_bilet_butonu', 'kt_ticket_button_shortcode' );
function kt_ticket_button_shortcode( $atts ) {
	$styles = kt_get_style_settings();

	$atts = shortcode_atts(
		array(
			'url'         => $styles['ticket_btn_url'],
			'image'       => $styles['ticket_btn_img'],
			'district'    => '',
			'alt'         => 'Bilet Al',
			'link_target' => '',
			'session'     => '',
		),
		$atts,
		'konser_bilet_butonu'
	);

	// Optional per-session attribution: "1" or "2" marks this button as the first/second session's ticket button.
	$session = in_array( (string) $atts['session'], array( '1', '2' ), true ) ? (string) $atts['session'] : '';

	$target_url = ! empty( $atts['url'] ) ? $atts['url'] : $styles['ticket_btn_url'];
	if ( empty( $target_url ) ) {
		return '';
	}

	global $post;
	$district_key = ! empty( $atts['district'] ) ? $atts['district'] : ( $post ? $post->post_name : 'general' );
	$page_label   = $post ? $post->post_title : '';
	$img_url      = ! empty( $atts['image'] ) ? $atts['image'] : $styles['ticket_btn_img'];

	kt_enqueue_tracker_script( $district_key, $page_label, false );

	// Tag the outbound ticket URL with UTM parameters (existing utm_* values on the URL are kept).
	$target_url = kt_append_utm_params( $target_url, kt_get_ticket_utm_params( $district_key, '' !== $session ? 'ticket-button-s' . $session : 'ticket-button' ) );

	$target_attr = '';
	if ( ! empty( $atts['link_target'] ) ) {
		$target_attr = ' target="' . esc_attr( $atts['link_target'] ) . '" rel="noopener noreferrer"';
	}

	$attachment_id = attachment_url_to_postid( $img_url );
	if ( $attachment_id ) {
		$img_html = wp_get_attachment_image(
			$attachment_id,
			'large',
			false,
			array(
				'fetchpriority' => 'high',
				'decoding'      => 'async',
				'alt'           => $atts['alt'],
			)
		);
	} else {
		$img_html = '<img fetchpriority="high" decoding="async" width="1024" height="281" src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $atts['alt'] ) . '">';
	}

	return sprintf(
		'<figure class="wp-block-image size-large"><a href="%s" class="kt-ticket-btn-link" data-kt-district="%s" data-kt-label="%s"%s%s>%s</a></figure>',
		esc_url( $target_url ),
		esc_attr( kt_normalize_district_key( $district_key ) ),
		esc_attr( $page_label ),
		'' !== $session ? ' data-kt-session="' . esc_attr( $session ) . '"' : '',
		$target_attr,
		$img_html
	);
}

/**
 * @deprecated 3.6.0 Use the 'konser-takvimi/takvim' Gutenberg block instead.
 */
add_shortcode( 'konser_takvimi', 'kt_concert_grid_shortcode' );
add_shortcode( 'subadap_konserler', 'kt_concert_grid_shortcode' );
function kt_concert_grid_shortcode() {
	$all_events      = get_option( KT_EVENTS_OPT, array() );
	$upcoming_events = array();
	$styles          = kt_get_style_settings();

	if ( is_array( $all_events ) ) {
		$now_ts = kt_get_site_timestamp();
		foreach ( $all_events as $event ) {
			$end_ts = kt_get_event_end_timestamp( $event );
			if ( false !== $end_ts && $end_ts >= $now_ts ) {
				$end_iso = ! empty( $event['time2'] ) ? kt_second_session_iso( $event ) : $event['datetime'];

				$upcoming_events[] = array(
					'datetime'    => sanitize_text_field( $event['datetime'] ),
					'endDatetime' => sanitize_text_field( $end_iso ),
					'time2'       => isset( $event['time2'] ) ? sanitize_text_field( $event['time2'] ) : '',
					'city'        => sanitize_text_field( $event['city'] ),
					'slug'        => sanitize_title( $event['slug'] ),
				);
			}
		}
	}

	wp_enqueue_style( 'kt-frontend-css' );

	$custom_css = sprintf(
		"#ktConcertGridContainer {
			--kt-radius: %dpx;
			--kt-height: %dpx;
			--kt-gap: %dpx;
			--kt-min-w: %dpx;
			--kt-date-text: %s;
			--kt-city-text: %s;
		}\n",
		intval( $styles['border_radius'] ),
		intval( $styles['badge_height'] ),
		intval( $styles['grid_gap'] ),
		intval( $styles['card_min_w'] ),
		esc_attr( $styles['date_text'] ),
		esc_attr( $styles['city_text'] )
	);

	foreach ( $styles['palettes'] as $idx => $pair ) {
		$num         = $idx + 1;
		$custom_css .= sprintf(
			"#ktConcertGridContainer .style-%d .date-part { background-color: %s; }\n#ktConcertGridContainer .style-%d .city-part { background-color: %s; }\n",
			$num,
			esc_attr( $pair['date'] ),
			$num,
			esc_attr( $pair['city'] )
		);
	}

	wp_add_inline_style( 'kt-frontend-css', $custom_css );
	wp_enqueue_script( 'kt-frontend-js' );
	wp_localize_script( 'kt-frontend-js', 'ktI18n', kt_get_js_dictionary() );
	wp_localize_script(
		'kt-frontend-js',
		'ktConcertData',
		array(
			'events'   => $upcoming_events,
			'baseUrl'  => trailingslashit( home_url() ),
			'maxItems' => intval( $styles['max_items'] ),
			'timezone' => $styles['timezone'],
		)
	);

	return '<div id="ktConcertGridContainer"><div class="kt-concert-grid" id="ktConcertGrid"></div></div>';
}
