<?php
/** Minimal GNU .mo reader for tests: returns msgid (plural forms joined with "\0") => msgstr. */
function kt_test_read_mo( $path ) {
	$d = file_get_contents( $path );
	$h = unpack( 'Vmagic/Vrev/Vn/Vo/Vt', $d );
	if ( 0x950412de !== $h['magic'] ) { return array(); }
	$out = array();
	for ( $i = 0; $i < $h['n']; $i++ ) {
		$a = unpack( 'Vl/Vo', substr( $d, $h['o'] + $i * 8, 8 ) );
		$b = unpack( 'Vl/Vo', substr( $d, $h['t'] + $i * 8, 8 ) );
		$out[ substr( $d, $a['o'], $a['l'] ) ] = substr( $d, $b['o'], $b['l'] );
	}
	return $out;
}
