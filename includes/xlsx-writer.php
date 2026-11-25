<?php
/**
 * Minimal .xlsx writer (no external library): several sheets of strings and numbers, bold header row,
 * frozen header, auto filter and column widths. Needs PHP's ZipArchive; kt_xlsx_available() tells whether it exists.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kt_xlsx_available() {
	return class_exists( 'ZipArchive' );
}

/**
 * Excel column letters for a zero-based index (0 => A, 26 => AA).
 */
function kt_xlsx_col( $index ) {
	$letters = '';
	$index++;
	while ( $index > 0 ) {
		$mod     = ( $index - 1 ) % 26;
		$letters = chr( 65 + $mod ) . $letters;
		$index   = intdiv( $index - $mod - 1, 26 );
	}
	return $letters;
}

function kt_xlsx_escape( $text ) {
	$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $text );
	return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/**
 * Excel sheet names: at most 31 characters, none of []:*?/\ and not empty; names must be unique.
 */
function kt_xlsx_sheet_name( $name, $used ) {
	$name = trim( preg_replace( '/[\[\]:*?\/\\\\]/', ' ', (string) $name ) );
	$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 31, 'UTF-8' ) : substr( $name, 0, 31 );
	if ( '' === $name ) {
		$name = 'Sheet';
	}
	$base = $name;
	$i    = 2;
	while ( in_array( strtolower( $name ), $used, true ) ) {
		$suffix = ' ' . $i++;
		$name   = ( function_exists( 'mb_substr' ) ? mb_substr( $base, 0, 31 - strlen( $suffix ), 'UTF-8' ) : substr( $base, 0, 31 - strlen( $suffix ) ) ) . $suffix;
	}
	return $name;
}

/**
 * Builds the XML of one worksheet. $rows[0] is the header row.
 */
function kt_xlsx_sheet_xml( $rows ) {
	$col_count = 0;
	$widths    = array();
	foreach ( $rows as $row ) {
		$col_count = max( $col_count, count( $row ) );
		foreach ( array_values( $row ) as $c => $value ) {
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
			if ( ! isset( $widths[ $c ] ) || $len > $widths[ $c ] ) {
				$widths[ $c ] = $len;
			}
		}
	}

	$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
	$xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
	if ( $col_count > 0 ) {
		$xml .= '<cols>';
		for ( $c = 0; $c < $col_count; $c++ ) {
			$w    = min( 50, max( 10, ( isset( $widths[ $c ] ) ? $widths[ $c ] : 10 ) + 2 ) );
			$xml .= '<col min="' . ( $c + 1 ) . '" max="' . ( $c + 1 ) . '" width="' . $w . '" customWidth="1"/>';
		}
		$xml .= '</cols>';
	}
	$xml .= '<sheetData>';
	foreach ( array_values( $rows ) as $r => $row ) {
		$xml .= '<row r="' . ( $r + 1 ) . '">';
		foreach ( array_values( $row ) as $c => $value ) {
			$ref   = kt_xlsx_col( $c ) . ( $r + 1 );
			$style = 0 === $r ? ' s="1"' : '';
			if ( ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) ) {
				$xml .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
			} else {
				$xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . kt_xlsx_escape( $value ) . '</t></is></c>';
			}
		}
		$xml .= '</row>';
	}
	$xml .= '</sheetData>';
	if ( $col_count > 0 && count( $rows ) > 1 ) {
		$xml .= '<autoFilter ref="A1:' . kt_xlsx_col( $col_count - 1 ) . count( $rows ) . '"/>';
	}
	$xml .= '</worksheet>';
	return $xml;
}

/**
 * Builds a workbook. $sheets: list of array( 'name' => string, 'rows' => array of rows ). Returns the file bytes, or
 * false when ZipArchive is missing or the file cannot be written.
 */
function kt_build_xlsx( $sheets ) {
	if ( ! kt_xlsx_available() || empty( $sheets ) ) {
		return false;
	}

	$tmp = tempnam( sys_get_temp_dir(), 'ktx' );
	if ( false === $tmp ) {
		return false;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
		return false;
	}

	$used      = array();
	$sheet_xml = array();
	$names     = array();
	foreach ( $sheets as $sheet ) {
		$name        = kt_xlsx_sheet_name( isset( $sheet['name'] ) ? $sheet['name'] : '', $used );
		$used[]      = strtolower( $name );
		$names[]     = $name;
		$sheet_xml[] = kt_xlsx_sheet_xml( isset( $sheet['rows'] ) ? $sheet['rows'] : array() );
	}

	$types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
	$wb    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
	$rels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
	foreach ( $sheet_xml as $i => $xml ) {
		$n      = $i + 1;
		$types .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		$wb    .= '<sheet name="' . kt_xlsx_escape( $names[ $i ] ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
		$rels  .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
		$zip->addFromString( 'xl/worksheets/sheet' . $n . '.xml', $xml );
	}
	$styles_id = count( $sheet_xml ) + 1;
	$rels     .= '<Relationship Id="rId' . $styles_id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
	$types    .= '</Types>';
	$wb       .= '</sheets></workbook>';

	$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

	$zip->addFromString( '[Content_Types].xml', $types );
	$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
	$zip->addFromString( 'xl/workbook.xml', $wb );
	$zip->addFromString( 'xl/_rels/workbook.xml.rels', $rels );
	$zip->addFromString( 'xl/styles.xml', $styles );
	$zip->close();

	$bytes = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temp file, not a remote URL.
	wp_delete_file( $tmp );
	return $bytes;
}
