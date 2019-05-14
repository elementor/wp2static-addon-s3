<?php

/**
 * Plugin Name:       WP2Static Add-on: S3
 * Plugin URI:        https://wp2static.com
 * Description:       S3 as a deployment option for WP2Static.
 * Version:           0.1
 * Author:            Leon Stafford
 * Author URI:        https://ljs.dev
 * License:           Unlicense
 * License URI:       http://unlicense.org
 * Text Domain:       wp2static-addon-s3
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WP2STATIC_S3_PATH', plugin_dir_path( __FILE__ ) );

require WP2STATIC_S3_PATH . 'vendor/autoload.php';

// @codingStandardsIgnoreStart
$ajax_action = isset( $_POST['ajax_action'] ) ? $_POST['ajax_action'] : '';
// @codingStandardsIgnoreEnd

// NOTE: bypass instantiating plugin for specific AJAX requests
if ( $ajax_action == 'test_s3' ) {
    $s3 = new WP2Static\S3;

    $s3->test_s3();

    wp_die();
    return null;
} elseif ( $ajax_action == 's3_prepare_export' ) {
    $s3 = new WP2Static\S3;

    $s3->bootstrap();
    $s3->prepareDeploy();

    wp_die();
    return null;
} elseif ( $ajax_action == 's3_transfer_files' ) {
    $s3 = new WP2Static\S3;

    $s3->bootstrap();
    $s3->s3_transfer_files();

    wp_die();
    return null;
} elseif ( $ajax_action == 'cloudfront_invalidate_all_items' ) {
    $s3 = new WP2Static\S3;

    $s3->cloudfront_invalidate_all_items();

    wp_die();
    return null;
}

define( 'PLUGIN_NAME_VERSION', '0.1' );

function run_wp2static_addon_s3() {
	$plugin = new WP2Static\S3Addon();
	$plugin->run();

}

run_wp2static_addon_s3();

