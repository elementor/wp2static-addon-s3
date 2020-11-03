<?php

/**
 * Plugin Name:       WP2Static Add-on: S3 Deployment
 * Plugin URI:        https://wp2static.com
 * Description:       AWS S3 deployment add-on for WP2Static.
 * Version:           1.0-alpha-007
 * Author:            Leon Stafford
 * Author URI:        https://ljs.dev
 * License:           Unlicense
 * License URI:       http://unlicense.org
 * Text Domain:       wp2static-addon-s3
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WP2STATIC_S3_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP2STATIC_S3_VERSION', '1.0-alpha-007' );

if ( file_exists( WP2STATIC_S3_PATH . 'vendor/autoload.php' ) ) {
    require_once WP2STATIC_S3_PATH . 'vendor/autoload.php';
}

function run_wp2static_addon_s3() {
    $controller = new WP2StaticS3\Controller();
    $controller->run();
}

register_activation_hook(
    __FILE__,
    [ 'WP2StaticS3\Controller', 'activate' ]
);

register_deactivation_hook(
    __FILE__,
    [ 'WP2StaticS3\Controller', 'deactivate' ]
);

run_wp2static_addon_s3();

