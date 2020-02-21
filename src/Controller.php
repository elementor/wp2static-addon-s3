<?php

namespace WP2StaticS3;

class Controller {
    const WP2STATIC_S3_VERSION = '0.1';

	public function __construct() {}

	public function run() {
        // initialize options DB
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_s3_options';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            value VARCHAR(255) NOT NULL,
            label VARCHAR(255) NULL,
            description VARCHAR(255) NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // check for seed data
        // if deployment_url option doesn't exist, create:
        $options = $this->getOptions();

        if ( ! isset( $options['s3Bucket'] ) ) {
            $this->seedOptions();
        }

        add_filter( 'wp2static_add_menu_items', [ 'WP2StaticS3\Controller', 'addSubmenuPage' ] );

        // TOOD: used only if adding to core Options view
        // add_filter(
        //     'wp2static_render_options_page_vars',
        //     [ $this, 'addOptionsTemplateVars' ],
        //     15,
        //     1);

        // add_action(
        //     'wp2static_addon_ui_save_options',
        //     [ $this, 'uiSaveOptions' ],
        //     15,
        //     1);

        add_action(
            'admin_post_wp2static_s3_delete',
            [ $this, 'deleteZip' ],
            15,
            1);

        add_action(
            'wp2static_deploy',
            [ $this, 'generateZip' ],
            15,
            1);

        // add_action(
        //     'wp2static_post_process_file',
        //     [ $this, 'convertURLsToOffline' ],
        //     15,
        //     2);

        // add_action(
        //     'wp2static_set_destination_url',
        //     [ $this, 'setDestinationURL' ]);


        add_action(
            'wp2static_set_wordpress_site_url',
            [ $this, 'modifyWordPressSiteURL' ]);

        if ( defined( 'WP_CLI' ) ) {
            \WP_CLI::add_command(
                'wp2static s3',
                [ 'WP2StaticS3\CLI', 's3' ]);
        }
	}

    // TODO: is this needed? confirm slashing of destination URLs...
    public function modifyWordPressSiteURL( $site_url ) {
        return rtrim( $site_url, '/' );
    }

    // public function setDestinationURL( $destination_url ) {
    //     $options = $this->getOptions();

    //     return $options['deployment_url']->value;
    // }

    // TODO: should be own addon for offline files
    // public function convertURLsToOffline( $file, $processed_site_path ) {
    //     WsLog::l('Zip Addon converting URLs to offline in file: ' . $file);
    //     error_log('within ProcessedSite path: ' . $processed_site_path);
    //     error_log('Detect type of file by name, extension or content type');
    //     error_log('modify URL');

    //     // other actions can process after this, based on priority
    // }

    /**
     *  Get all add-on options
     *
     *  @return mixed[] All options
     */
    public static function getOptions() : array {
        global $wpdb;
        $options = [];

        $table_name = $wpdb->prefix . 'wp2static_addon_s3_options';

        $rows = $wpdb->get_results( "SELECT * FROM $table_name" );

        foreach($rows as $row) {
            $options[$row->name] = $row;
        }

        return $options;
    }

    /**
     * Seed options
     *
     */
    public static function seedOptions() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_s3_options';

        $query_string = "INSERT INTO $table_name (name, value, label, description) VALUES (%s, %s, %s, %s);";
        $query = $wpdb->prepare(
            $query_string,
            'cfDistributionID',
            '',
            'CloudFront Distribution ID',
            'If using CloudFront, set this to auto-invalidate cache');

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            's3Bucket',
            '',
            'S3 Bucket',
            '');

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            's3AccessKeyID',
            '',
            'AWS Access Key ID',
            '');

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            's3SecretAccessKey',
            '',
            'AWS Secret Access Key',
            '');

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            's3Region',
            '',
            'AWS Region',
            '');

        $wpdb->query( $query );

        $query = $wpdb->prepare(
            $query_string,
            's3RemotePath',
            '',
            'Path in S3 Bucket',
            'Optionally, deploy to a subdirectory within bucket');

        $wpdb->query( $query );

    // 'cfDistributionID',
    // 's3Bucket',
    // 's3AccessKeyID',
    // 's3Region',
    // 's3RemotePath',
    // 's3SecretAccessKey',

    }

    /**
     * Save options
     *
     */
    public static function saveOption( $name, $value ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_s3_options';

        $query_string = "INSERT INTO $table_name (name, value) VALUES (%s, %s);";
        $query = $wpdb->prepare( $query_string, $name, $value );

        $wpdb->query( $query );
    }

    public static function renderS3Page() : void {
        $view = [];
        $view['nonce_action'] = 'wp2static-s3-delete';
        $view['uploads_path'] = \WP2Static\SiteInfo::getPath('uploads');
        $s3_path = \WP2Static\SiteInfo::getPath( 'uploads' ) . 'wp2static-processed-site.s3';


        $view['options'] = self::getOptions();

        $view['s3_url'] =
            is_file( $s3_path ) ?
                \WP2Static\SiteInfo::getUrl( 'uploads' ) . 'wp2static-processed-site.s3' : '#';

        require_once __DIR__ . '/../views/s3-page.php';
    }

    // public function addOptionsTemplateVars( $template_vars ) {
    //     $template_vars['wp2static_s3_addon_options'] = $this->getOptions();

    //     // find position of deploy options
    //     $deployment_options_position = 0;
    //     foreach( $template_vars['options_templates'] as $index => $options_template ) {
    //       if (strpos($options_template, 'core-deployment-options.php') !== false) {
    //         $deployment_options_position = $index + 1;
    //       } 
    //     } 

    //     // insert s3 deploy options template after that
    //     array_splice(
    //         $template_vars['options_templates'],
    //         $deployment_options_position,
    //         0, // # elements to remove
    //         [__DIR__ . '/../views/deploy-options.php']
    //     );

    //     return $template_vars;
    // }

    // TODO: use in other addons needing to add to core options
    // public function uiSaveOptions() {
    //     error_log('S3 Addon Saving Options, accessing $_POST');

    //     if (isset($_POST['s3Bucket'])) {
    //         // TODO: validate URL
    //         // call other save function
    //         $this->saveOption( 's3Bucket', $_POST['s3Bucket'] );
    //     }
    // }

    public function deploy( $processed_site_path ) {
        \WP2Static\WsLog::l('S3 Addon deploying');

        $s3_deployer = new S3Deployer();
        $s3_deployer->deploy( $processed_site_path );
    }

    /*
     * Naive encypting/decrypting
     *
     */
    public static function encrypt_decrypt($action, $string) {
        $output = false;
        $encrypt_method = "AES-256-CBC";

        $secret_key =
            defined( 'AUTH_KEY' ) ?
            constant( 'AUTH_KEY' ) :
            'LC>_cVZv34+W.P&_8d|ejfr]d31h)J?z5n(LB6iY=;P@?5/qzJSyB3qctr,.D$[L';

        $secret_iv =
            defined( 'AUTH_SALT' ) ?
            constant( 'AUTH_SALT' ) :
            'ec64SSHB{8|AA_ThIIlm:PD(Z!qga!/Dwll 4|i.?UkC§NNO}z?{Qr/q.KpH55K9';

        $key = hash('sha256', $secret_key);
        $variate = substr(hash('sha256', $secret_iv), 0, 16);

        if ( $action == 'encrypt' ) {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $variate);
            $output = base64_encode($output);
        } else if( $action == 'decrypt' ) {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $variate);
        }

        return $output;
    }

    public static function activate_for_single_site() : void {
        error_log('activating WP2Static S3 Add-on');
    }

    public static function deactivate_for_single_site() : void {
        error_log('deactivating WP2Static S3 Add-on, maintaining options');
    }

    public static function deactivate( bool $network_wide = null ) : void {
        error_log('deactivating WP2Static S3 Add-on');
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::deactivate_for_single_site();
            }

            restore_current_blog();
        } else {
            self::deactivate_for_single_site();
        }
    }

    public static function activate( bool $network_wide = null ) : void {
        error_log('activating s3 addon');
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::activate_for_single_site();
            }

            restore_current_blog();
        } else {
            self::activate_for_single_site();
        }
    }

    public static function addSubmenuPage( $submenu_pages ) {
        $submenu_pages['s3'] = [ 'WP2StaticS3\Controller', 'renderS3Page' ];

        return $submenu_pages;
    }
}

