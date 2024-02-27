<?php

declare( strict_types=1 );
/*
Plugin Name: FAQ with categories
Plugin URI: https://github.com/joerivanveen/faq-with-categories
Description: Easy to maintain FAQ and answer plugin with categories.
Version: 1.3.0
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Author: Joeri van Veen
Author URI: https://wp-developer.eu
License: GPL3
Text Domain: faq-with-categories
Domain Path: /languages/
*/
defined( 'ABSPATH' ) || die();
const RUIGEHOND010_VERSION = '1.3.0';
$ruigehond010_basename = plugin_basename( __FILE__ );
$ruigehond010_dirname  = dirname( __FILE__ );
if ( ! class_exists( 'ruigehond_0_5_0\ruigehond', false ) ) {
	include_once( "$ruigehond010_dirname/includes/ruigehond.php" ); // base class
}
include_once( "$ruigehond010_dirname/includes/ruigehond010.php" );
// This is plugin nr. 10 by ruige hond. It identifies with: ruigehond010.
global $ruigehond010;
$ruigehond010 = new ruigehond010\ruigehond010( $ruigehond010_basename );
// Register hooks for plugin management
add_action( "activate_$ruigehond010_basename", array( $ruigehond010, 'activate' ) );
add_action( 'init', array( $ruigehond010, 'initialize' ) );
/**
 * set up ajax for admin interface, ajax call javascript needs to call whatever
 * comes after wp_ajax_ (so in this case: ruigehond010_handle_input)
 */
add_action( 'wp_ajax_ruigehond010_handle_input', 'ruigehond010_handle_input' );
function ruigehond010_handle_input() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ruigehond010_nonce' ) ) {
		die(0);
	}

	global $ruigehond010;
	$returnObj = $ruigehond010->handle_input( $_POST );
	echo wp_json_encode( $returnObj, JSON_PRETTY_PRINT );
	die(); // prevent any other output
}
