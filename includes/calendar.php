<?php
/**
 * Add to Calendar widget (Google Calendar and .ics).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the "Add to Calendar" (Google Calendar & Apple/Outlook .ics) button HTML for a district concert page.
 * UTM tags are added to the page link inside the event description, per channel and per session.
 */
function kt_render_add_to_calendar_widget( $events, $page_url = '', $district_key = '' ) {
	if ( empty( $events ) || ! is_array( $events ) ) {
		return '';
	}

	$styles         = kt_get_style_settings();
	$performer_name = ! empty( $styles['schema_performer'] )
		? sanitize_text_field( $styles['schema_performer'] )
		: wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	$months_tr = kt_get_month_names();
	$tz        = kt_get_site_timezone();
	$utc_tz    = new DateTimeZone( 'UTC' );

	$sessions = array();
	foreach ( $events as $ev ) {
		if ( empty( $ev['datetime'] ) || empty( $ev['city'] ) ) {
			continue;
		}
		$city_name = sanitize_text_field( $ev['city'] );
		$title     = sprintf( /* translators: 1: performer, 2: city */ __( '%1$s — %2$s Konseri', 'konser-takvimi' ), $performer_name, $city_name );
		$ev_slug   = isset( $ev['slug'] ) ? sanitize_title( $ev['slug'] ) : sanitize_title( $city_name . '-konseri' );
		$loc_url   = ! empty( $page_url ) ? $page_url : home_url( '/' . ltrim( $ev_slug, '/' ) . '/' );
		$utm_key   = ! empty( $district_key ) ? $district_key : $ev_slug;
		$desc_base = sprintf( /* translators: 1: performer, 2: city */ __( '%1$s %2$s konseri. Detaylar ve bilet: ', 'konser-takvimi' ), $performer_name, $city_name );

		// Each channel (Google Calendar link vs .ics file) and each session gets its own UTM tags on the page link.
		$desc_gcal1 = $desc_base . kt_append_utm_params( $loc_url, kt_get_calendar_utm_params( 'google-calendar', $utm_key, 1 ) );
		$desc_ics1  = $desc_base . kt_append_utm_params( $loc_url, kt_get_calendar_utm_params( 'ics-file', $utm_key, 1 ) );
		$desc_gcal2 = $desc_base . kt_append_utm_params( $loc_url, kt_get_calendar_utm_params( 'google-calendar', $utm_key, 2 ) );
		$desc_ics2  = $desc_base . kt_append_utm_params( $loc_url, kt_get_calendar_utm_params( 'ics-file', $utm_key, 2 ) );

		try {
			$start1 = new DateTime( $ev['datetime'], $tz );
			$end1   = clone $start1;
			$end1->modify( '+1 hour' );

			$s1_utc = clone $start1;
			$s1_utc->setTimezone( $utc_tz );
			$e1_utc = clone $end1;
			$e1_utc->setTimezone( $utc_tz );

			$day_label  = intval( $start1->format( 'j' ) ) . ' ' . $months_tr[ intval( $start1->format( 'n' ) ) ];
			$time1_lbl  = $start1->format( 'H.i' );
			$gcal1_url  = add_query_arg(
				array(
					'action'   => 'TEMPLATE',
					'text'     => $title,
					'dates'    => $s1_utc->format( 'Ymd\THis\Z' ) . '/' . $e1_utc->format( 'Ymd\THis\Z' ),
					'details'  => $desc_gcal1,
					'location' => $city_name,
				),
				'https://calendar.google.com/calendar/render'
			);

			$sessions[] = array(
				'label'     => sprintf( /* translators: 1: date, 2: time */ __( '%1$s — Saat %2$s', 'konser-takvimi' ), $day_label, $time1_lbl ),
				'title'     => $title,
				'location'  => $city_name,
				'desc'      => $desc_ics1,
				'start_utc' => $s1_utc->format( 'Ymd\THis\Z' ),
				'end_utc'   => $e1_utc->format( 'Ymd\THis\Z' ),
				'gcal_url'  => $gcal1_url,
				'filename'  => sanitize_title( $city_name . '-' . $start1->format( 'Y-m-d-Hi' ) ) . '.ics',
			);

			if ( ! empty( $ev['time2'] ) && preg_match( '/^\d{2}:\d{2}$/', $ev['time2'] ) ) {
				$start2 = new DateTime( $start1->format( 'Y-m-d' ) . ' ' . $ev['time2'] . ':00', $tz );
				$end2   = clone $start2;
				$end2->modify( '+1 hour' );

				$s2_utc = clone $start2;
				$s2_utc->setTimezone( $utc_tz );
				$e2_utc = clone $end2;
				$e2_utc->setTimezone( $utc_tz );

				$time2_lbl = $start2->format( 'H.i' );
				$gcal2_url = add_query_arg(
					array(
						'action'   => 'TEMPLATE',
						'text'     => sprintf( /* translators: %s: event title */ __( '%s (2. Seans)', 'konser-takvimi' ), $title ),
						'dates'    => $s2_utc->format( 'Ymd\THis\Z' ) . '/' . $e2_utc->format( 'Ymd\THis\Z' ),
						'details'  => $desc_gcal2,
						'location' => $city_name,
					),
					'https://calendar.google.com/calendar/render'
				);

				$sessions[] = array(
					'label'     => sprintf( /* translators: 1: date, 2: time */ __( '%1$s — Saat %2$s (2. Seans)', 'konser-takvimi' ), $day_label, $time2_lbl ),
					'title'     => sprintf( /* translators: %s: event title */ __( '%s (2. Seans)', 'konser-takvimi' ), $title ),
					'location'  => $city_name,
					'desc'      => $desc_ics2,
					'start_utc' => $s2_utc->format( 'Ymd\THis\Z' ),
					'end_utc'   => $e2_utc->format( 'Ymd\THis\Z' ),
					'gcal_url'  => $gcal2_url,
					'filename'  => sanitize_title( $city_name . '-' . $start2->format( 'Y-m-d-Hi' ) ) . '.ics',
				);
			}
		} catch ( Exception $e ) {
			continue;
		}
	}

	if ( empty( $sessions ) ) {
		return '';
	}

	wp_enqueue_style( 'kt-frontend-css' );
	wp_enqueue_script( 'kt-frontend-js' );

	ob_start();
	?>
	<div class="kt-add-calendar-wrap">
		<details class="kt-cal-dropdown">
			<summary class="kt-cal-trigger">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
				<span><?php echo esc_html__( 'Takvime Ekle', 'konser-takvimi' ); ?></span>
				<svg class="kt-cal-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
			</summary>
			<div class="kt-cal-menu">
				<?php foreach ( $sessions as $ses ) : ?>
					<div class="kt-cal-session-group">
						<?php if ( count( $sessions ) > 1 ) : ?>
							<div class="kt-cal-session-title"><?php echo esc_html( $ses['label'] ); ?></div>
						<?php endif; ?>
						<a href="<?php echo esc_url( $ses['gcal_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="kt-cal-option">
							<?php echo esc_html__( 'Google Takvim\'e Ekle', 'konser-takvimi' ); ?>
						</a>
						<button
							type="button"
							class="kt-cal-option kt-cal-ics-btn"
							data-title="<?php echo esc_attr( $ses['title'] ); ?>"
							data-location="<?php echo esc_attr( $ses['location'] ); ?>"
							data-desc="<?php echo esc_attr( $ses['desc'] ); ?>"
							data-start="<?php echo esc_attr( $ses['start_utc'] ); ?>"
							data-end="<?php echo esc_attr( $ses['end_utc'] ); ?>"
							data-filename="<?php echo esc_attr( $ses['filename'] ); ?>"
						>
							<?php echo esc_html__( 'Apple / Outlook Takvimi (.ics)', 'konser-takvimi' ); ?>
						</button>
					</div>
				<?php endforeach; ?>
			</div>
		</details>
	</div>
	<?php
	return ob_get_clean();
}
