<?php
/**
 * Cache-safe district redirect and the redirect shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the redirect target URL (attribute -> settings -> /konserler).
 */
function kt_resolve_redirect_url( $custom_target = '' ) {
	$styles         = kt_get_style_settings();
	$default_target = ! empty( $styles['redirect_target'] ) ? $styles['redirect_target'] : '/konserler';
	$default_url    = kt_redirect_path_to_url( $default_target );

	if ( empty( $custom_target ) ) {
		return $default_url;
	}

	// A target set in a block/shortcode may only point to this site or to the host chosen in the plugin settings.
	// Anything else falls back to the default target, so content authors cannot create open redirects.
	$custom_url   = kt_redirect_path_to_url( $custom_target );
	$custom_host  = strtolower( (string) wp_parse_url( $custom_url, PHP_URL_HOST ) );
	$allowed_host = array(
		strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
		strtolower( (string) wp_parse_url( $default_url, PHP_URL_HOST ) ),
	);

	return in_array( $custom_host, $allowed_host, true ) ? $custom_url : $default_url;
}

/**
 * Converts a redirect path (or absolute http/https URL) into an absolute URL.
 */
function kt_redirect_path_to_url( $path ) {
	if ( 0 === strpos( $path, 'http://' ) || 0 === strpos( $path, 'https://' ) ) {
		return esc_url_raw( $path );
	}
	return home_url( '/' . ltrim( $path, '/' ) );
}

/**
 * Detects the district redirector block or shortcode before any page HTML is sent:
 * redirects to the configured page with a 302 when there is no upcoming concert.
 */
add_action( 'template_redirect', 'kt_handle_district_redirect_shortcode' );
function kt_handle_district_redirect_shortcode() {
	if ( is_admin() || ! is_singular() ) {
		return;
	}

	global $post;
	if ( ! $post || empty( $post->post_content ) ) {
		return;
	}

	$has_redirect_block = has_block( 'konser-takvimi/ilce-yonlendir', $post );
	$has_redirect_sc    = has_shortcode( $post->post_content, 'konser_ilce_yonlendir' );

	if ( ! $has_redirect_block && ! $has_redirect_sc ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( current_user_can( 'edit_pages' ) ) {
		return;
	}

	$targets_to_check = array();

	if ( $has_redirect_block ) {
		$blocks = parse_blocks( $post->post_content );
		foreach ( $blocks as $blk ) {
			if ( isset( $blk['blockName'] ) && 'konser-takvimi/ilce-yonlendir' === $blk['blockName'] ) {
				$targets_to_check[] = array(
					'district'        => isset( $blk['attrs']['district'] ) ? $blk['attrs']['district'] : '',
					'redirect_target' => isset( $blk['attrs']['redirect_target'] ) ? $blk['attrs']['redirect_target'] : '',
				);
			}
		}
	}

	if ( $has_redirect_sc ) {
		$pattern = get_shortcode_regex( array( 'konser_ilce_yonlendir' ) );
		if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts               = shortcode_parse_atts( $match[3] );
				$atts               = shortcode_atts(
					array(
						'district'        => '',
						'redirect_target' => '',
					),
					$atts,
					'konser_ilce_yonlendir'
				);
				$targets_to_check[] = $atts;
			}
		}
	}

	foreach ( $targets_to_check as $item_atts ) {
		$lookup_key = ! empty( $item_atts['district'] ) ? $item_atts['district'] : $post->post_name;

		if ( ! kt_has_upcoming_concert_for_target( $lookup_key ) ) {
			nocache_headers();
			$redirect_url = kt_resolve_redirect_url( $item_atts['redirect_target'] );

			// wp_safe_redirect() only accepts the site's own host by default; allow the (already validated) target host.
			$target_host = wp_parse_url( $redirect_url, PHP_URL_HOST );
			add_filter(
				'allowed_redirect_hosts',
				function ( $hosts ) use ( $target_host ) {
					$hosts[] = $target_host;
					return $hosts;
				}
			);

			wp_safe_redirect( $redirect_url, 302 );
			exit;
		}
	}
}

/**
 * @deprecated 3.6.0 Use the 'konser-takvimi/ilce-yonlendir' Gutenberg block instead.
 */
add_shortcode( 'konser_ilce_yonlendir', 'kt_district_redirect_shortcode_output' );
function kt_district_redirect_shortcode_output( $atts ) {
	$atts = shortcode_atts(
		array(
			'district'             => '',
			'redirect_target'      => '',
			'show_calendar_button' => '1',
		),
		$atts,
		'konser_ilce_yonlendir'
	);

	global $post;
	$lookup_key      = ! empty( $atts['district'] ) ? $atts['district'] : ( $post ? $post->post_name : '' );
	$page_label      = $post ? $post->post_title : '';
	$page_url        = $post ? get_permalink( $post->ID ) : '';
	$upcoming_events = kt_get_upcoming_events_for_target( $lookup_key );
	$has_event       = ! empty( $upcoming_events );
	$redirect_url    = kt_resolve_redirect_url( $atts['redirect_target'] );
	$show_cal_btn    = ( '0' !== strval( $atts['show_calendar_button'] ) && false !== $atts['show_calendar_button'] );

	if ( current_user_can( 'edit_pages' ) ) {
		if ( $has_event ) {
			$cal_preview = $show_cal_btn ? kt_render_add_to_calendar_widget( $upcoming_events, $page_url, $lookup_key ) : '';
			return '<div style="padding:10px 14px;margin:10px 0;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;color:#166534;font-size:13px;"><strong>Konser Takvimi Bilgi:</strong> ' . sprintf( /* translators: %s: district key */ esc_html__( 'Bu ilçe (%s) için yaklaşan konser aktif olduğundan ziyaretçiler bu sayfada kalacaktır (yönetici girişi yapıldığı için ziyaret ve tıklamalar sayaca eklenmez).', 'konser-takvimi' ), '<code>' . esc_html( $lookup_key ) . '</code>' ) . '</div>' . $cal_preview;
		}
		return '<div style="padding:10px 14px;margin:10px 0;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;font-size:13px;"><strong>Konser Takvimi Bilgi:</strong> ' . sprintf( /* translators: 1: district key, 2: redirect URL */ esc_html__( 'Bu ilçe (%1$s) için yaklaşan konser bulunmadığından normal ziyaretçiler otomatik olarak %2$s adresine yönlendirilecektir (yönetici olduğunuz için yönlendirilmediniz).', 'konser-takvimi' ), '<code>' . esc_html( $lookup_key ) . '</code>', '<code>' . esc_html( $redirect_url ) . '</code>' ) . '</div>';
	}

	if ( ! $has_event ) {
		return '<script>window.location.replace(' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ');</script>';
	}

	kt_enqueue_tracker_script( $lookup_key, $page_label, true );

	return $show_cal_btn ? kt_render_add_to_calendar_widget( $upcoming_events, $page_url, $lookup_key ) : '';
}
