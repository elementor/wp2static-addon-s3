<?php

/**
 * Plugin Name:       WP2Static Add-on: S3
 * Plugin URI:        https://wp2static.com
 * Description:       AWS S3 as a deployment option for WP2Static.
 * Version:           0.1
 * Author:            Leon Stafford
 * Author URI:        https://leonstafford.github.io
 * License:           Unlicense
 * License URI:       http://unlicense.org
 * Text Domain:       wp2static-addon-s3
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
if ( $ajax_action == 'test_s3' ) {
    require_once $wp2static_core_dir .
        '/plugin/WP2Static/SitePublisher.php';
    require_once $add_on_dir . '/S3Deployer.php';

    wp_die();
    return null;
} elseif ( $ajax_action == 's3_prepare_export' ) {
    require_once $wp2static_core_dir .
        '/plugin/WP2Static/SitePublisher.php';
    require_once $add_on_dir . '/S3Deployer.php';

    wp_die();
    return null;
} elseif ( $ajax_action == 's3_transfer_files' ) {
    require_once $wp2static_core_dir .
        '/plugin/WP2Static/SitePublisher.php';
    require_once $add_on_dir . '/S3Deployer.php';

    wp_die();
    return null;
} elseif ( $ajax_action == 'cloudfront_invalidate_all_items' ) {
    require_once $wp2static_core_dir .
        '/plugin/WP2Static/SitePublisher.php';
    require_once $add_on_dir . '/S3Deployer.php';

    wp_die();
    return null;
}

define( 'PLUGIN_NAME_VERSION', '0.1' );

require plugin_dir_path( __FILE__ ) . 'includes/class-wp2static-addon-s3.php';

function run_wp2static_addon_s3() {

	$plugin = new Wp2static_Addon_S3();
	$plugin->run();

}

run_wp2static_addon_s3();

