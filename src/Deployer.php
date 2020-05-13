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

    public function upload_files( string $processed_site_path ) : void {
        // check if dir exists
        if ( ! is_dir( $processed_site_path ) ) {
            return;
        }

        // instantiate S3 client
        $s3 = self::s3_client();

        // iterate each file in ProcessedSite
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processed_site_path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        $put_data = [
            'Bucket' => Controller::getValue( 's3Bucket' ),
            'ACL'    => 'public-read',
        ];

        $cache_control = Controller::getValue( 's3CacheControl' );
        if ( $cache_control ) {
            $put_data['CacheControl'] = $cache_control;
        }

        foreach ( $iterator as $filename => $file_object ) {
            $base_name = basename( $filename );
            if ( $base_name != '.' && $base_name != '..' ) {
                $real_filepath = realpath( $filename );

                // TODO: do filepaths differ when running from WP-CLI (non-chroot)?

                $cache_key = str_replace( $processed_site_path, '', $filename );

                if ( \WP2Static\DeployCache::fileisCached( $cache_key ) ) {
                    continue;
                }

                if ( ! $real_filepath ) {
                    $err = 'Trying to deploy unknown file to S3: ' . $filename;
                    \WP2Static\WsLog::l( $err );
                    continue;
                }

                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                if ( ! is_string( $filename ) ) {
                    continue;
                }

                $s3_key =
                    Controller::getValue( 's3RemotePath' ) ?
                    Controller::getValue( 's3RemotePath' ) . '/' .
                    ltrim( $cache_key, '/' ) :
                    ltrim( $cache_key, '/' );

                $mime_type = MimeTypes::GuessMimeType( $filename );

                $put_data['Key'] = $s3_key;
                $put_data['Body'] = file_get_contents( $filename );
                $put_data['ContentType'] = $mime_type;

                $result = $s3->putObject( $put_data );

                if ( $result['@metadata']['statusCode'] === 200 ) {
                    \WP2Static\DeployCache::addFile( $cache_key );
                }
            }
        }
    }

    public function s3_client() : \Aws\S3\S3Client {
        $client_options = [
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
            $client_options['credentials'] = [
                'key' => Controller::getValue( 's3AccessKeyID' ),
                'secret' => \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue( 's3SecretAccessKey' )
                ),
            ];
        } else if ( Controller::getValue( 's3Profile' ) ) {
            $client_options['profile'] = Controller::getValue( 's3Profile' );
        }

        return new \Aws\S3\S3Client( $client_options );
    }

    public function cloudfront_client() : \Aws\CloudFront\CloudFrontClient {
        /*
            If no credentials option, SDK attempts to load credentials from
            your environment in the following order:
                 - environment variables.
                 - a credentials .ini file.
                 - an IAM role.
        */
        if (
            Controller::getValue( 'cfAccessKeyID' ) &&
            Controller::getValue( 'cfSecretAccessKey' )
        ) {
            // Use the supplied access keys.
            $credentials = new \Aws\Credentials\Credentials(
                Controller::getValue( 'cfAccessKeyID' ),
                \WP2Static\CoreOptions::encrypt_decrypt(
                    'decrypt',
                    Controller::getValue( 'cfSecretAccessKey' )
                )
            );
            $client = \Aws\CloudFront\CloudFrontClient::factory(
                [
                    'region' => Controller::getValue( 'cfRegion' ),
                    'version' => 'latest',
                    'credentials' => $credentials,
                ]
            );
        } else if ( Controller::getValue( 'cfProfile' ) ) {
            // Use the specified profile.
            $client = \Aws\CloudFront\CloudFrontClient::factory(
                [
                    'profile' => Controller::getValue( 'cfProfile' ),
                    'region' => Controller::getValue( 'cfRegion' ),
                    'version' => 'latest',
                ]
            );
        } else {
            // Use the IAM role.
            $client = \Aws\CloudFront\CloudFrontClient::factory(
                [
                    'region' => Controller::getValue( 'cfRegion' ),
                    'version' => 'latest',
                ]
            );
        }

        return $client;
    }

    public function cloudfront_invalidate_all_items() : void {
        if ( ! Controller::getValue( 'cfDistributionID' ) ) {
            return;
        }

        \WP2Static\WsLog::l( 'Invalidating all CloudFront items' );

        $client = self::cloudfront_client();

        try {
            $result = $client->createInvalidation(
                [
                    'DistributionId' => Controller::getValue( 'cfDistributionID' ),
                    'InvalidationBatch' => [
                        'CallerReference' => 'WP2Static S3 Add-on ' . time(),
                        'Paths' => [
                            'Items' => [ '/*' ],
                            'Quantity' => 1,
                        ],
                    ],
                ]
            );

        } catch ( AwsException $e ) {
            // output error message if fails
            error_log( $e->getMessage() );
        }
    }
}

