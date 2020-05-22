<?php

namespace WP2StaticS3;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Aws\S3\S3Client;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use Aws\Credentials\Credentials;

class Deployer { 

    // prepare deploy, if modifies URL structure, should be an action
    // $this->prepareDeploy();

    // options - load from addon's static methods

    public function __construct() {}

        /* This function added by : Maulik for htaccess_redirects*/    
    public function read_data() {
            $myfile = fopen(ABSPATH ."/.htaccess", "r") or die("Unable to open file!");
            $fileread =  fread($myfile,filesize(ABSPATH ."/.htaccess"));

            $regular_data = $this->tag_contents($fileread, 'RewriteRule ^', '[R=301,L]');

            $data_redirect = array();
            foreach ($regular_data as $key => $value) {
                $data_redirect[$key] = explode("$", $value, 2);
            }
            return $data_redirect;
        }

        /* This function added by : Maulik htaccess_redirects*/
    public function tag_contents($string, $tag_open, $tag_close){
               foreach (explode($tag_open, $string) as $key => $value) {
                   if(strpos($value, $tag_close) !== FALSE){
                        $result[] = substr($value, 0, strpos($value, $tag_close));;
                   }
               }
               return $result;
        }

  

    public function upload_files( string $processed_site_path ) : void {
        // check if dir exists
        //echo '<pre>';
        
        if ( ! is_dir( $processed_site_path ) ) {
            return;
         }

        $client_options = [
            'profile' => Controller::getValue( 's3Profile' ),
            'version' => 'latest',
            'region' => Controller::getValue( 's3Region' ),
        ];

        /*
            If no credentials option, SDK attempts to load credentials from
            your environment in the following order:

                 - environment variables.
                 - a credentials .ini file.
                 - an IAM role.
        */
        if (
            Controller::getValue( 's3AccessKeyID' ) &&
            Controller::getValue( 's3SecretAccessKey' )
        ) {
            error_log( 'using supplied creds' );
            $client_options['credentials'] = [
                'key' => Controller::getValue( 's3AccessKeyID' ),
                'secret' => \WP2StaticS3\Controller::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue( 's3SecretAccessKey' )
                ),
            ];
            unset( $client_options['profile'] );
        }

        error_log( print_r( $client_options, true ) );

        // instantiate S3 client
        $s3 = new \Aws\S3\S3Client( $client_options );

        /*Start: Code Here for HTaccess 301 rule : By Maulik*/
        if (Controller::getValue('cfredirect') == 'yes')  {
                $redirect_rules=$this->read_data();
                foreach ($redirect_rules as $redirect_rule) {
                    $old_url= str_replace('/','',$redirect_rule[0])."/index.html";    
                    $redirected_url=$redirect_rule[1];
                    try{  
                        $result = $s3->putObject(
                                [
                                    'Bucket' => Controller::getValue( 's3Bucket' ),
                                    'Key' => $old_url,
                                    'Body' => '',
                                    'ACL'    => 'public-read',
                                    'WebsiteRedirectLocation' => $redirected_url
                                ]
                        );
                    }catch(Exception $ex){ 
                         error_log( "Error Occurred\n", $ex->getMessage());
                    }             
               
                }

        }

        /*Exit: Code Here for HTaccess 301 rule : By Maulik */

        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processed_site_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ( $iterator as $filename => $file_object ) {
            $base_name = basename( $filename );
            if ( $base_name != '.' && $base_name != '..' ) {
                $real_filepath = realpath( $filename );

                // TODO: do filepaths differ when running from WP-CLI (non-chroot)?

                // TODO: check if in DeployCache
                if ( \WP2Static\DeployCache::fileisCached( $filename ) ) {
                    continue;
                }

                if ( ! $real_filepath ) {
                    $err = 'Trying to add unknown file to Zip: ' . $filename;
                    \WP2Static\WsLog::l( $err );
                    continue;
                }

                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                if ( ! is_string( $filename ) ) {
                    continue;
                }

                $key =
                    Controller::getValue( 's3RemotePath' ) ?
                    Controller::getValue( 's3RemotePath' ) . '/' .
                    ltrim( str_replace( $processed_site_path, '', $filename ), '/' ) :
                    ltrim( str_replace( $processed_site_path, '', $filename ), '/' );

                $mime_type = MimeTypes::GuessMimeType( $filename );

                $result = $s3->putObject(
                    [
                        'Bucket' => Controller::getValue( 's3Bucket' ),
                        'Key' => $key,
                        'Body' => file_get_contents( $filename ),
                        'ACL'    => 'public-read',
                        'ContentType' => $mime_type,
                    ]
                );



                if ( $result['@metadata']['statusCode'] === 200 ) {
                    \WP2Static\DeployCache::addFile( $filename );
                }
            }
        }
        
    }


    public function cloudfront_invalidate_all_items() : void {
        if ( ! Controller::getValue( 'cfDistributionID' ) ) {
            return;
        }

        \WP2Static\WsLog::l( 'Invalidating all CloudFront items' );

        $client_options = [
            'profile' => 'default',
            'version' => 'latest',
            'region' => Controller::getValue( 'cfRegion' ),
        ];

        /*
            If no credentials option, SDK attempts to load credentials from
            your environment in the following order:

                 - environment variables.
                 - a credentials .ini file.
                 - an IAM role.
        */
        if (
            Controller::getValue( 's3AccessKeyID' ) &&
            Controller::getValue( 's3SecretAccessKey' )
        ) {

            $credentials = new \Aws\Credentials\Credentials(
                Controller::getValue( 's3AccessKeyID' ),
                \WP2StaticS3\Controller::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue( 's3SecretAccessKey' )
                )
            );

            $client_options['credentials'] = $credentials;
        }

        $client = new \Aws\CloudFront\CloudFrontClient( $client_options );

        try {
            $result = $client->createInvalidation(
                [
                    'DistributionId' => Controller::getValue( 'cfDistributionID' ),
                    'InvalidationBatch' => [
                        'CallerReference' => 'WP2Static S3 Add-on',
                        'Paths' => [
                            'Items' => [ '/*' ],
                            'Quantity' => 1,
                        ],
                    ],
                ]
            );

            error_log( print_r( $result, true ) );

        } catch ( AwsException $e ) {
            // output error message if fails
            error_log( $e->getMessage() );
        }
    }



}






