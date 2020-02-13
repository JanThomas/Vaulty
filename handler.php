<?php

function I( $A, $key = false, $default = false ) {
	if( is_string( $A ) ) {
		$default = $key;
		$key     = $A;
		$A       = $_REQUEST;
	}
	if( !is_array( $A ) ) {
		return $default;
	}
	if( is_array( $key ) ) {
		foreach( $key as $key_sing ) {
			$r = I( $A, $key_sing, $default );
			if( $r !== $default ) {
				return $r;
			}
		}

		return $default;
	}

	return ( ( !is_string( $key ) && !is_numeric( $key ) ) || !key_exists( $key, $A ) || $A[ $key ] === '' ) ? $default : $A[ $key ];
}


if( session_status() == PHP_SESSION_NONE ) {
	session_start();
}

$file = $_SERVER['REQUEST_URI'];

$vault   = I( $_SESSION, 'vaulty_vault' );
$uploads = I( $_SESSION, 'vaulty_uploads' );
$levels  = I( $_SESSION,);

if( !$vault || !is_dir( $vault ) || !$uploads || !is_dir( $uploads ) ) {
	http_response_code( 500 );
	exit;
}


var_dump( $_GET );
var_dump( $_SERVER );
var_dump( $_SESSION );