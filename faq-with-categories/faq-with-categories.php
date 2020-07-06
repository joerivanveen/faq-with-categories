<?php
/*
Plugin Name: FAQ with categories
Plugin URI: https://github.com/joerivanveen/faq-with-categories
Description: Easy to maintain FAQ and answer plugin with categories.
Version: 0.3.3
Author: Ruige hond
Author URI: https://ruigehond.nl
License: GPL3
Text Domain: faq-with-categories
Domain Path: /languages/
*/
defined('ABSPATH') or die();
define('RUIGEHOND010_VERSION', '0.3.3');
// This is plugin nr. 10 by ruige hond. It identifies with: ruigehond010.
if (!class_exists('ruigehond_0_3_4', false)) {
    include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'ruigehond.php'); // base class
}
include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'ruigehond010.php');
// Register hooks for plugin management, functions are at the bottom of this file.
register_activation_hook(__FILE__, 'ruigehond010_install');
register_deactivation_hook(__FILE__, 'ruigehond010_deactivate');
register_uninstall_hook(__FILE__, 'ruigehond010_uninstall');
// Startup the plugin
add_action('init', array(new ruigehond010\ruigehond010('FAQ with categories'), 'initialize'));
/*
 * setup ajax for admin interface, ajax call javascript needs to call whatever
 * comes after wp_ajax_ (so in this case: ruigehond010_handle_input)
 */
add_action('wp_ajax_ruigehond010_handle_input', 'ruigehond010_handle_input');
function ruigehond010_handle_input()
{
    $ruigehond = new ruigehond010\ruigehond010();
    $r = $ruigehond->handle_input($_POST);
    echo json_encode($r);
    die(); // prevent any other output, TODO is there another possibility? Not sure about ramifications here.
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ruigehond010_settingslink'); // settings link on plugins page
function ruigehond010_settingslink($links)
{
    $url = get_admin_url() . 'options-general.php?page=faq-with-categories';
    $settings_link = '<a href="' . $url . '">' . __('Settings', 'faq-with-categories') . '</a>';
    array_unshift($links, $settings_link);

    return $links;
}

/*
 * Proxy functions for the class's system functionality
 */
function ruigehond010_install()
{
    $ruigehond = new ruigehond010\ruigehond010();
    $ruigehond->install();
}

function ruigehond010_deactivate()
{
    $ruigehond = new ruigehond010\ruigehond010();
    $ruigehond->deactivate();
}

function ruigehond010_uninstall()
{
    $ruigehond = new ruigehond010\ruigehond010();
    $ruigehond->uninstall();
}
