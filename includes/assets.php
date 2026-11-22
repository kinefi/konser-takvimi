<?php
/**
 * Front-end script and style registration.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'kt_register_frontend_assets' );
function kt_register_frontend_assets() {
	$style_deps = array();

	// The Quicksand web font is loaded from Google Fonts; sites with strict privacy requirements can turn it off.
	$font_settings = kt_get_style_settings();
	if ( 'google' === $font_settings['font_mode'] ) {
		wp_register_style(
			'kt-quicksand-font',
			'https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700;900&display=swap',
			array(),
			null
		);
		$style_deps[] = 'kt-quicksand-font';
	}

	wp_register_style(
		'kt-frontend-css',
		KT_PLUGIN_URL . 'assets/css/frontend.css',
		$style_deps,
		KT_PLUGIN_VER
	);

	if ( 'theme' === $font_settings['font_mode'] ) {
		// Use the active theme's typography instead of Quicksand.
		wp_add_inline_style( 'kt-frontend-css', '#ktConcertGridContainer, .kt-add-calendar-wrap { font-family: inherit; }' );
	}

	wp_register_script(
		'kt-frontend-js',
		KT_PLUGIN_URL . 'assets/js/frontend.js',
		array(),
		KT_PLUGIN_VER,
		true
	);

	wp_register_script(
		'kt-tracker-js',
		KT_PLUGIN_URL . 'assets/js/tracker.js',
		array(),
		KT_PLUGIN_VER,
		true
	);
}

/**
 * Enqueues the tracker script and the district context on the front end (page views, unique visitors, time on page and ticket clicks).
 */
function kt_enqueue_tracker_script( $district_key = '', $page_label = '', $track_view = false ) {
	static $track_page_view = false;

	if ( $track_view ) {
		$track_page_view = true;
	}

	$styles      = kt_get_style_settings();
	$raw_domains = isset( $styles['ticket_domains'] ) ? $styles['ticket_domains'] : '';
	$domains     = array_values(
		array_filter(
			array_map(
				function ( $item ) {
					return strtolower( trim( $item ) );
				},
				explode( ',', $raw_domains )
			)
		)
	);

	$utm = kt_get_utm_settings();

	wp_enqueue_script( 'kt-tracker-js' );
	wp_localize_script(
		'kt-tracker-js',
		'ktTrackerData',
		array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'defaultDistrict' => kt_normalize_district_key( $district_key ),
			'defaultLabel'    => sanitize_text_field( $page_label ),
			'trackView'       => $track_page_view ? 1 : 0,
			'todayStr'        => kt_get_site_date_str( 0 ),
			'ticketDomains'   => $domains,
			'defaultImgUrl'   => esc_url_raw( $styles['ticket_btn_img'] ),
			'captureUtm'      => $utm['capture_enabled'] ? 1 : 0,
			'utm'             => array(
				'enabled'  => $utm['ticket_enabled'] ? 1 : 0,
				'source'   => $utm['source'],
				'medium'   => $utm['medium'],
				'campaign' => $utm['campaign'],
			),
		)
	);
}
