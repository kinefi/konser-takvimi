<?php
/**
 * Konser Takvimi — Gutenberg blocks (modern counterparts of the shortcodes).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'kt_register_gutenberg_blocks' );
function kt_register_gutenberg_blocks() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	wp_register_script(
		'kt-blocks-editor-js',
		KT_PLUGIN_URL . 'assets/js/blocks-editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
		KT_PLUGIN_VER,
		true
	);

	// 1. Concert Calendar block
	register_block_type(
		'konser-takvimi/takvim',
		array(
			'api_version'     => 2,
			'editor_script'   => 'kt-blocks-editor-js',
			'style'           => 'kt-frontend-css',
			'render_callback' => 'kt_render_calendar_block',
		)
	);

	// 2. District Redirector & Add to Calendar block
	register_block_type(
		'konser-takvimi/ilce-yonlendir',
		array(
			'api_version'     => 2,
			'editor_script'   => 'kt-blocks-editor-js',
			'style'           => 'kt-frontend-css',
			'render_callback' => 'kt_render_district_redirect_block',
			'attributes'      => array(
				'district'             => array(
					'type'    => 'string',
					'default' => '',
				),
				'redirect_target'      => array(
					'type'    => 'string',
					'default' => '',
				),
				'show_calendar_button' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
		)
	);

	// 3. Ticket Button block
	register_block_type(
		'konser-takvimi/bilet-butonu',
		array(
			'api_version'     => 2,
			'editor_script'   => 'kt-blocks-editor-js',
			'render_callback' => 'kt_render_ticket_button_block',
			'attributes'      => array(
				'url'         => array(
					'type'    => 'string',
					'default' => '',
				),
				'image'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'district'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'alt'         => array(
					'type'    => 'string',
					'default' => 'Bilet Al',
				),
				'link_target' => array(
					'type'    => 'string',
					'default' => '',
				),
				'session'     => array(
					'type'    => 'string',
					'default' => '',
				),
			),
		)
	);
}

function kt_render_calendar_block( $attributes ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return kt_render_calendar_editor_preview();
	}
	return kt_concert_grid_shortcode();
}

function kt_render_district_redirect_block( $attributes ) {
	$show_cal = ! isset( $attributes['show_calendar_button'] ) || ! empty( $attributes['show_calendar_button'] );
	$atts     = array(
		'district'             => isset( $attributes['district'] ) ? sanitize_text_field( $attributes['district'] ) : '',
		'redirect_target'      => isset( $attributes['redirect_target'] ) ? sanitize_text_field( $attributes['redirect_target'] ) : '',
		'show_calendar_button' => $show_cal ? '1' : '0',
	);
	return kt_district_redirect_shortcode_output( $atts );
}

function kt_render_ticket_button_block( $attributes ) {
	$atts = array(
		'url'         => isset( $attributes['url'] ) ? esc_url_raw( $attributes['url'] ) : '',
		'image'       => isset( $attributes['image'] ) ? esc_url_raw( $attributes['image'] ) : '',
		'district'    => isset( $attributes['district'] ) ? sanitize_text_field( $attributes['district'] ) : '',
		'alt'         => isset( $attributes['alt'] ) ? sanitize_text_field( $attributes['alt'] ) : 'Bilet Al',
		'link_target' => isset( $attributes['link_target'] ) ? sanitize_text_field( $attributes['link_target'] ) : '',
		'session'     => isset( $attributes['session'] ) ? sanitize_text_field( $attributes['session'] ) : '',
	);

	$html = kt_ticket_button_shortcode( $atts );
	if ( empty( $html ) && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return '<div style="padding:12px;background:#fefce8;border:1px solid #fde047;border-radius:6px;color:#854d0e;font-size:13px;"><strong>Konser Bilet Butonu:</strong> Önizleme için sağ panelden veya eklenti genel ayarlarından bir Bilet Satış URL\'si girin.</div>';
	}
	return $html;
}

/**
 * Builds a static preview of the concert cards for the Gutenberg editor (used by ServerSideRender).
 */
function kt_render_calendar_editor_preview() {
	$all_events = get_option( KT_EVENTS_OPT, array() );
	$styles     = kt_get_style_settings();
	$upcoming   = array();

	if ( is_array( $all_events ) ) {
		$now_ts = kt_get_site_timestamp();
		foreach ( $all_events as $ev ) {
			$end_ts = kt_get_event_end_timestamp( $ev );
			if ( false !== $end_ts && $end_ts >= $now_ts ) {
				$upcoming[] = $ev;
			}
		}
		usort( $upcoming, 'kt_sort_events_asc' );
		$upcoming = array_slice( $upcoming, 0, max( 1, intval( $styles['max_items'] ) ) );
	}

	if ( empty( $upcoming ) ) {
		return '<div style="padding:20px;text-align:center;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;color:#475569;"><strong>Konser Takvimi Bloğu:</strong> Şu anda yaklaşan aktif konser kaydı bulunmuyor.</div>';
	}

	$today_str    = kt_get_site_date_str( 0 );
	$tomorrow_str = kt_get_site_date_str( 1 );
	$months_tr    = array( '', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );
	$days_tr      = array( 'Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi' );
	$tz           = kt_get_site_timezone();

	ob_start();
	?>
	<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(<?php echo intval( $styles['card_min_w'] ); ?>px,1fr));gap:<?php echo intval( $styles['grid_gap'] ); ?>px;padding:8px;">
		<?php foreach ( $upcoming as $idx => $ev ) : ?>
			<?php
			$dt       = new DateTime( $ev['datetime'], $tz );
			$ymd      = $dt->format( 'Y-m-d' );
			$day_num  = intval( $dt->format( 'j' ) );
			$mon_name = $months_tr[ intval( $dt->format( 'n' ) ) ];
			$day_name = $days_tr[ intval( $dt->format( 'w' ) ) ];
			$time_str = $dt->format( 'H.i' );
			if ( ! empty( $ev['time2'] ) ) {
				$time_str .= ' ve ' . str_replace( ':', '.', sanitize_text_field( $ev['time2'] ) );
			}
			$pair  = $styles['palettes'][ $idx % 12 ];
			$badge = '';
			if ( $ymd === $today_str ) {
				$badge = '<span style="position:absolute;top:6px;right:8px;background:#dc2626;color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;">BUGÜN</span>';
			} elseif ( $ymd === $tomorrow_str ) {
				$badge = '<span style="position:absolute;top:6px;right:8px;background:#d97706;color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;">YARIN</span>';
			}
			?>
			<div style="display:flex;height:<?php echo intval( $styles['badge_height'] ); ?>px;border-radius:<?php echo intval( $styles['border_radius'] ); ?>px;overflow:hidden;box-shadow:0 3px 10px rgba(0,0,0,0.06);">
				<div style="width:44%;background:<?php echo esc_attr( $pair['date'] ); ?>;color:<?php echo esc_attr( $styles['date_text'] ); ?>;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;line-height:1.15;">
					<strong style="font-size:15px;"><?php echo esc_html( $day_num . ' ' . $mon_name ); ?></strong>
					<span style="font-size:13px;"><?php echo esc_html( $day_name ); ?></span>
					<span style="font-size:12px;font-weight:700;"><?php echo esc_html( $time_str ); ?></span>
				</div>
				<div style="width:56%;background:<?php echo esc_attr( $pair['city'] ); ?>;color:<?php echo esc_attr( $styles['city_text'] ); ?>;display:flex;justify-content:center;align-items:center;position:relative;padding:8px;text-align:center;">
					<?php echo wp_kses_post( $badge ); ?>
					<strong style="font-size:18px;"><?php echo esc_html( $ev['city'] ); ?></strong>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
