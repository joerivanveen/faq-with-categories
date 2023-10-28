<?php

declare( strict_types=1 );
/*
Plugin Name: FAQ with categories
Plugin URI: https://github.com/joerivanveen/faq-with-categories
Description: Easy to maintain FAQ and answer plugin with categories.
Version: 1.2.0
Author: Joeri van Veen
Author URI: https://wp-developer.eu
License: GPL3
Text Domain: faq-with-categories
Domain Path: /languages/
*/
defined( 'ABSPATH' ) or die();
const RUIGEHOND010_VERSION = '1.2.0';
// This is plugin nr. 10 by ruige hond. It identifies with: ruigehond010.
if ( ! class_exists( 'ruigehond_0_4_1\ruigehond', false ) ) {
	include_once( dirname( __FILE__ ) . '/includes/ruigehond.php' ); // base class
}
include_once( dirname( __FILE__ ) . '/includes/ruigehond010.php' );
// Register hooks for plugin management, functions are at the bottom of this file.
register_activation_hook( __FILE__, 'ruigehond010_install' );
register_deactivation_hook( __FILE__, 'ruigehond010_deactivate' );
register_uninstall_hook( __FILE__, 'ruigehond010_uninstall' );
// Startup the plugin
add_action( 'init', array( new ruigehond010\ruigehond010( 'FAQ with categories' ), 'initialize' ) );
/**
 * setup ajax for admin interface, ajax call javascript needs to call whatever
 * comes after wp_ajax_ (so in this case: ruigehond010_handle_input)
 */
add_action( 'wp_ajax_ruigehond010_handle_input', 'ruigehond010_handle_input' );
function ruigehond010_handle_input() {
	$ruigehond = new ruigehond010\ruigehond010();
	$returnObj = $ruigehond->handle_input( $_POST );
	echo json_encode( $returnObj );
	die(); // prevent any other output
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ruigehond010_settingslink' ); // settings link on plugins page
function ruigehond010_settingslink( $links ) {
	$admin_url  = get_admin_url();
	$__faq      = __( 'FAQ', 'faq-with-categories' );
	$__settings = __( 'Settings', 'faq-with-categories' );
	array_unshift(
		$links,
		"<a href=\"edit.php?post_type=ruigehond010_faq\">{$__faq}</a>",
		"<a href=\"{$admin_url}admin.php?page=faq-with-categories-with-submenu-settings\">{$__settings}</a>"
	);

	return $links;
}

/*
 * Proxy functions for the class's system functionality
 */
function ruigehond010_install() {
	$ruigehond = new ruigehond010\ruigehond010();
	$ruigehond->install();
}

function ruigehond010_deactivate() {
	$ruigehond = new ruigehond010\ruigehond010();
	$ruigehond->deactivate();
}

function ruigehond010_uninstall() {
	$ruigehond = new ruigehond010\ruigehond010();
	$ruigehond->uninstall();
}
