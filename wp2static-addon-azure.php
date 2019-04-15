<?php

/**
 * Plugin Name:       WP2Static Add-on: Azure
 * Plugin URI:        https://wp2static.com
 * Description:       Microsoft Azure Cloud Storage as a deployment option for WP2Static.
 * Version:           0.1
 * Author:            Leon Stafford
 * Author URI:        https://leonstafford.github.io
 * License:           Unlicense
 * License URI:       http://unlicense.org
 * Text Domain:       wp2static-addon-azure
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// @codingStandardsIgnoreStart
$ajax_action = isset( $_POST['ajax_action'] ) ? $_POST['ajax_action'] : '';
// @codingStandardsIgnoreEnd

$wp2static_core_dir =
    dirname( __FILE__ ) . '/../static-html-output-plugin';

$add_on_dir = dirname( __FILE__ );
// NOTE: bypass instantiating plugin for specific AJAX requests
if ( $ajax_action === 'test_azure' ) {
    require_once $wp2static_core_dir .
        '/plugin/WP2Static/SitePublisher.php';
    require_once $add_on_dir . '/AzureDeployer.php';

    wp_die();
    return null;
} else if ( $ajax_action === 'azure_prepare_export' ) {
    require_once $wp2static_core_dir .
        '/plugin/WP2Static/SitePublisher.php';
    require_once $add_on_dir . '/AzureDeployer.php';

    wp_die();
    return null;
} else if ( $ajax_action === 'azure_upload_files' ) {
    require_once $wp2static_core_dir .
        '/plugin/WP2Static/SitePublisher.php';
    require_once $add_on_dir . '/AzureDeployer.php';

    wp_die();
    return null;
}

define( 'PLUGIN_NAME_VERSION', '0.1' );

function activate_wp2static_addon_azure() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp2static-addon-azure-activator.php';
	Wp2static_Addon_Azure_Activator::activate();
}

function deactivate_wp2static_addon_azure() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp2static-addon-azure-deactivator.php';
	Wp2static_Addon_Azure_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp2static_addon_azure' );
register_deactivation_hook( __FILE__, 'deactivate_wp2static_addon_azure' );

require plugin_dir_path( __FILE__ ) . 'includes/class-wp2static-addon-azure.php';

function run_wp2static_addon_azure() {

	$plugin = new Wp2static_Addon_Azure();
	$plugin->run();

}

run_wp2static_addon_azure();

