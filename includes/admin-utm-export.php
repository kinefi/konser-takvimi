<?php
/**
 * UTM traffic sources: view settings (group / sort / filter) and the Excel (.xlsx) export.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cleans the group / sort / filter values of the UTM table (from the URL or from the export form).
 * Returns array( 'group', 'sort', 'dir', 'district', 'source' ).
 */
function kt_normalize_utm_view( $raw ) {
	$groups  = array( '', 'district', 'source', 'medium', 'campaign' );
	$group   = isset( $raw['group'] ) ? sanitize_key( $raw['group'] ) : '';
	$sort    = isset( $raw['sort'] ) ? sanitize_key( $raw['sort'] ) : '';
	$dir     = isset( $raw['dir'] ) ? sanitize_key( $raw['dir'] ) : '';
	$columns = kt_utm_columns();
	if ( ! in_array( $group, $groups, true ) ) {
		$group = '';
	}
	// In a grouped view the first column is the group itself.
	$sort_ok = isset( $columns[ $sort ] ) || ( '' !== $group && 'group' === $sort );
	if ( ! $sort_ok ) {
		$sort = '';
	}
	if ( 'asc' !== $dir && 'desc' !== $dir ) {
		$dir = '';
	}
	return array(
		'group'    => $group,
		'sort'     => $sort,
		'dir'      => $dir,
		'district' => isset( $raw['district'] ) ? kt_normalize_district_key( sanitize_text_field( $raw['district'] ) ) : '',
		'source'   => isset( $raw['source'] ) ? sanitize_text_field( $raw['source'] ) : '',
	);
}

/**
 * Rows of the UTM table after filter, grouping and sorting, for the given view settings.
 */
function kt_get_utm_view_rows( $stats, $view ) {
	$rows = kt_filter_utm_rows( kt_get_utm_report_rows( $stats ), $view['district'], $view['source'] );
	if ( '' !== $view['group'] ) {
		$rows = kt_group_utm_rows( $rows, $view['group'] );
		// Sort grouped rows by their group value through the shared sorter.
		$sort = 'group' === $view['sort'] ? 'group' : $view['sort'];
		return kt_sort_group_rows( $rows, '' === $sort ? 'visitors' : $sort, $view['dir'] );
	}
	return kt_sort_utm_rows( $rows, '' === $view['sort'] ? 'visitors' : $view['sort'], $view['dir'] );
}

/**
 * Sorts grouped rows ('group' is the text column).
 */
function kt_sort_group_rows( $rows, $sort, $dir ) {
	if ( 'group' !== $sort ) {
		if ( ! in_array( $sort, array( 'visitors', 'views', 'clicks', 'ctr', 'click_visitors', 'conversion' ), true ) ) {
			$sort = 'visitors';
		}
		if ( 'asc' !== $dir && 'desc' !== $dir ) {
			$dir = 'desc';
		}
	} elseif ( 'asc' !== $dir && 'desc' !== $dir ) {
		$dir = 'asc';
	}
	$factor = 'asc' === $dir ? 1 : -1;
	usort(
		$rows,
		function ( $a, $b ) use ( $sort, $factor ) {
			$cmp = ( 'group' === $sort )
				? strcmp( strtolower( (string) $a['group'] ), strtolower( (string) $b['group'] ) )
				: ( $a[ $sort ] <=> $b[ $sort ] );
			if ( 0 !== $cmp ) {
				return $cmp * $factor;
			}
			return $b['visitors'] <=> $a['visitors'];
		}
	);
	return $rows;
}

/**
 * Header cells of the numeric columns, shared by every sheet.
 */
function kt_utm_number_headers() {
	return array(
		__( 'Tekil Ziyaretçi', 'konser-takvimi' ),
		__( 'Sayfa Gösterimi', 'konser-takvimi' ),
		__( 'Bilet Tıklaması', 'konser-takvimi' ),
		__( 'Tıklama Oranı - CTR (%)', 'konser-takvimi' ),
		__( 'Tıklayan Tekil Kişi', 'konser-takvimi' ),
		__( 'Dönüşüm (%)', 'konser-takvimi' ),
	);
}

/**
 * Sheets of the Excel export: the detailed table plus one summary per district, source, medium and campaign.
 */
function kt_build_utm_export_sheets( $stats, $view ) {
	$dash = function ( $value ) {
		return '' !== $value ? $value : '—';
	};

	$detail_view          = $view;
	$detail_view['group'] = '';
	$detail               = kt_get_utm_view_rows( $stats, $detail_view );
	$detail_rows          = array( array_merge( array( __( 'Kaynak', 'konser-takvimi' ), __( 'Ortam', 'konser-takvimi' ), __( 'Kampanya', 'konser-takvimi' ), __( 'İlçe', 'konser-takvimi' ) ), kt_utm_number_headers() ) );
	foreach ( $detail as $r ) {
		$detail_rows[] = array( $dash( $r['source'] ), $dash( $r['medium'] ), $dash( $r['campaign'] ), (string) $r['district'], $r['visitors'], $r['views'], $r['clicks'], (float) $r['ctr'], $r['click_visitors'], (float) $r['conversion'] );
	}
	$sheets = array( array( 'name' => __( 'Ayrıntı', 'konser-takvimi' ), 'rows' => $detail_rows ) );

	$summaries = array(
		'district' => array( __( 'İlçe özeti', 'konser-takvimi' ), __( 'İlçe', 'konser-takvimi' ) ),
		'source'   => array( __( 'Kaynak özeti', 'konser-takvimi' ), __( 'Kaynak', 'konser-takvimi' ) ),
		'medium'   => array( __( 'Ortam özeti', 'konser-takvimi' ), __( 'Ortam', 'konser-takvimi' ) ),
		'campaign' => array( __( 'Kampanya özeti', 'konser-takvimi' ), __( 'Kampanya', 'konser-takvimi' ) ),
	);
	foreach ( $summaries as $by => $names ) {
		$group_view          = $view;
		$group_view['group'] = $by;
		$group_view['sort']  = 'group' === $view['sort'] ? '' : $view['sort'];
		$rows                = array( array_merge( array( $names[1] ), kt_utm_number_headers() ) );
		foreach ( kt_get_utm_view_rows( $stats, $group_view ) as $r ) {
			$rows[] = array( $dash( (string) $r['group'] ), $r['visitors'], $r['views'], $r['clicks'], (float) $r['ctr'], $r['click_visitors'], (float) $r['conversion'] );
		}
		$sheets[] = array( 'name' => $names[0], 'rows' => $rows );
	}
	return $sheets;
}

add_action( 'admin_init', 'kt_handle_utm_export' );
function kt_handle_utm_export() {
	if ( ! isset( $_POST['kt_export_utm_action'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Yetkisiz erişim.', 'konser-takvimi' ) );
	}
	check_admin_referer( 'kt_stats_nonce_action', 'kt_stats_nonce' );
	kt_flush_stats_buffer();

	$view = kt_normalize_utm_view(
		array(
			'group'    => isset( $_POST['utm_group'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_group'] ) ) : '',
			'sort'     => isset( $_POST['utm_sort'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_sort'] ) ) : '',
			'dir'      => isset( $_POST['utm_dir'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_dir'] ) ) : '',
			'district' => isset( $_POST['utm_district'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_district'] ) ) : '',
			'source'   => isset( $_POST['utm_source_f'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_source_f'] ) ) : '',
		)
	);
	$stats  = get_option( KT_STATS_OPT, array() );
	$sheets = kt_build_utm_export_sheets( is_array( $stats ) ? $stats : array(), $view );
	$stamp  = kt_get_site_date_str( 0 );

	nocache_headers();
	$bytes = kt_build_xlsx( $sheets );
	if ( false !== $bytes ) {
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="konser-utm-kaynaklari-' . $stamp . '.xlsx"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		echo esc_html($bytes);
		exit;
	}

	// Without PHP's zip extension the detailed table is delivered as CSV (UTF-8 with BOM so Excel reads Turkish letters).
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="konser-utm-kaynaklari-' . $stamp . '.csv"' );
	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	foreach ( $sheets[0]['rows'] as $csv_row ) {
		fputcsv( $output, array_map( function ( $cell ) { return is_string( $cell ) ? preg_replace( '/^[=+\-@]/', "'$0", $cell ) : $cell; }, $csv_row ), ';' );
	}
	fclose( $output );
	exit;
}
