<?php
/**
 * Extracts translatable strings (text domain "konser-takvimi") from the plugin's PHP files into languages/konser-takvimi.pot.
 * Usage: php tools/extract-strings.php
 * Dependency-free replacement for `xgettext` for the few functions this plugin uses.
 */
$root  = dirname( __DIR__ );
$funcs = array( '__' => 1, 'esc_html__' => 1, 'esc_attr__' => 1, '_e' => 1, 'esc_html_e' => 1, '_n' => 2 );
$files = array_merge( array( $root . '/konser-takvimi.php' ), glob( $root . '/includes/*.php' ), glob( $root . '/includes/admin/*.php' ) );
sort( $files );

$entries = array(); // msgid => array( 'refs' => [], 'comment' => '', 'plural' => '' )
foreach ( $files as $file ) {
	$tokens = token_get_all( file_get_contents( $file ) );
	$n      = count( $tokens );
	$rel    = ltrim( str_replace( $root, '', $file ), '/' );
	for ( $i = 0; $i < $n; $i++ ) {
		$t = $tokens[ $i ];
		if ( ! is_array( $t ) || T_STRING !== $t[0] || ! isset( $funcs[ $t[1] ] ) ) {
			continue;
		}
		// Must be a call: next significant token is "(".
		$j = $i + 1;
		while ( $j < $n && is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE ), true ) ) { $j++; }
		if ( '(' !== $tokens[ $j ] ) { continue; }
		$j++;
		$strings = array();
		$comment = '';
		$depth   = 1;
		for ( ; $j < $n && $depth > 0; $j++ ) {
			$tk = $tokens[ $j ];
			if ( '(' === $tk ) { $depth++; }
			elseif ( ')' === $tk ) { $depth--; }
			elseif ( is_array( $tk ) && T_COMMENT === $tk[0] && 1 === $depth && preg_match( '#/\*\s*translators:\s*(.*?)\s*\*/#s', $tk[1], $m ) ) { $comment = $m[1]; }
			elseif ( is_array( $tk ) && T_CONSTANT_ENCAPSED_STRING === $tk[0] && 1 === $depth ) {
				$q = $tk[1][0];
				$v = substr( $tk[1], 1, -1 );
				$v = ( "'" === $q ) ? str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $v ) : stripcslashes( $v );
				$strings[] = $v;
			}
		}
		$need = $funcs[ $t[1] ] + 1; // string args + text domain
		if ( count( $strings ) < $need || 'konser-takvimi' !== $strings[ $need - 1 ] ) { continue; }
		$id = $strings[0];
		if ( ! isset( $entries[ $id ] ) ) { $entries[ $id ] = array( 'refs' => array(), 'comment' => '', 'plural' => '' ); }
		$entries[ $id ]['refs'][] = $rel . ':' . $t[2];
		if ( '' !== $comment ) { $entries[ $id ]['comment'] = $comment; }
		if ( '_n' === $t[1] ) { $entries[ $id ]['plural'] = $strings[1]; }
	}
}
ksort( $entries, SORT_STRING );

function kt_po_quote( $s ) {
	$s = str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $s );
	return '"' . $s . '"';
}
$out  = "# Konser Takvimi translation template. Source language: Turkish.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: Konser Takvimi\\n\"\n\"Language: tr_TR\\n\"\n\"MIME-Version: 1.0\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\n";
foreach ( $entries as $id => $e ) {
	if ( '' !== $e['comment'] ) { $out .= '#. ' . preg_replace( '/\s+/', ' ', $e['comment'] ) . "\n"; }
	$out .= '#: ' . implode( ' ', array_slice( array_unique( $e['refs'] ), 0, 3 ) ) . "\n";
	if ( false !== strpos( $id, '%' ) && preg_match( '/%[0-9$]*[sd]/', $id ) ) { $out .= "#, php-format\n"; }
	$out .= 'msgid ' . kt_po_quote( $id ) . "\n";
	if ( '' !== $e['plural'] ) {
		$out .= 'msgid_plural ' . kt_po_quote( $e['plural'] ) . "\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
	} else {
		$out .= "msgstr \"\"\n\n";
	}
}
file_put_contents( $root . '/languages/konser-takvimi.pot', $out );
echo count( $entries ) . " strings written to languages/konser-takvimi.pot\n";
