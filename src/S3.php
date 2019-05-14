<?php

namespace WP2Static;

class S3 extends SitePublisher {

    public function __construct() {
        $plugin = Controller::getInstance();

        $this->batch_size =
            $plugin->options->getOption( 'deployBatchSize' );
        $this->cf_distribution_id =
            $plugin->option->getOption( 'cfDistributionId' );
        $this->s3_bucket = $plugin->options->getOption( 's3Bucket' );
        $this->s3_cache_control =
            $plugin->options->getOption( 's3CacheControl' );
        $this->s3_key = $plugin->options->getOption( 's3Key' );
        $this->s3_region = $plugin->options->getOption( 's3Region' );
        $this->s3_remote_path = $plugin->options->getOption( 's3Region' );
        $this->s3_secret = $plugin->options->getOption( 's3Secret' );
        $this->previous_hashes_path =
            SiteInfo::getPath( 'uploads' ) .
                '/WP2STATIC-S3-PREVIOUS-HASHES.txt';
    }

    public function upload_files() {
        $this->files_remaining = $this->getRemainingItemsCount();

        if ( $this->files_remaining < 0 ) {
            echo 'ERROR';
            die(); }

        if ( $this->batch_size > $this->files_remaining ) {
            $this->batch_size = $this->files_remaining;
        }

        $lines = $this->getItemsToDeploy( $this->batch_size );

        $this->openPreviousHashesFile();

        foreach ( $lines as $line ) {
            list($this->local_file, $this->target_path) = explode( ',', $line );

            $this->local_file = $this->archive->path . $this->local_file;

            if ( ! is_file( $this->local_file ) ) {
                continue;
            }

            if ( isset( $this->s3_remote_path ) ) {
                $this->target_path =
                    $this->s3_remote_path . '/' .
                        $this->target_path;
            }

            $this->logAction(
                "Uploading {$this->local_file} to {$this->target_path} in S3"
            );

            $this->local_file_contents = file_get_contents( $this->local_file );

            $this->hash_key = $this->target_path . basename( $this->local_file );

            if ( isset( $this->file_paths_and_hashes[ $this->hash_key ] ) ) {
                $prev = $this->file_paths_and_hashes[ $this->hash_key ];
                $current = crc32( $this->local_file_contents );

                if ( $prev != $current ) {
                    $this->logAction(
                        "{$this->hash_key} differs from previous deploy cache "
                    );

                    try {
                        $this->put_s3_object(
                            $this->target_path .
                                    basename( $this->local_file ),
                            $this->local_file_contents,
                            GuessMimeType( $this->local_file )
                        );

                    } catch ( Exception $e ) {
                        $this->handleException( $e );
                    }
                } else {
                    $this->logAction(
                        "Skipping {$this->hash_key} as identical " .
                            'to deploy cache'
                    );
                }
            } else {
                $this->logAction(
                    "{$this->hash_key} not found in deploy cache "
                );

                try {
                    $this->put_s3_object(
                        $this->target_path .
                                basename( $this->local_file ),
                        $this->local_file_contents,
                        GuessMimeType( $this->local_file )
                    );

                } catch ( Exception $e ) {
                    $this->handleException( $e );
                }
            }

            $this->recordFilePathAndHashInMemory(
                $this->hash_key,
                $this->local_file_contents
            );
        }

        unset( $this->s3 );
        
        $this->writeFilePathAndHashesToFile();

        $this->pauseBetweenAPICalls();

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    public function test_s3() {
        try {
            $this->put_s3_object(
                '.tmp_wp2static.txt',
                'Test WP2Static connectivity',
                'text/plain'
            );

            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            }
        } catch ( Exception $e ) {
            WsLog::l( 'S3 ERROR RETURNED: ' . $e );
            echo "There was an error testing S3.\n";
        }
    }

    public function put_s3_object( $s3_path, $content, $content_type ) {
        // NOTE: quick fix for #287
        $s3_path = str_replace( '@', '%40', $s3_path );

        $this->logAction( "PUT'ing file to {$s3_path} in S3" );

        $host_name = $this->s3_region . '.s3.' .
            $this->s3_region . '.amazonaws.com';

        $this->logAction( "Using S3 Endpoint {$host_name}" );

        //$content_acl = 'public-read';
        $content_title = $s3_path;
        $aws_service_name = 's3';
        $timestamp = gmdate( 'Ymd\THis\Z' );
        $date = gmdate( 'Ymd' );

        // HTTP request headers as key & value
        $request_headers = array();
        $request_headers['Content-Type'] = $content_type;
        $request_headers['Date'] = $timestamp;
        $request_headers['Host'] = $host_name;
        //$request_headers['x-amz-acl'] = $content_acl;
        $request_headers['x-amz-content-sha256'] = hash( 'sha256', $content );

        if ( ! empty( $this->s3_cache_control ) ) {
            $max_age = $this-s3_cache_control;
            $request_headers['Cache-Control'] = 'max-age=' . $max_age;
        }

        // Sort it in ascending order
        ksort( $request_headers );

        $canonical_headers = array();

        foreach ( $request_headers as $key => $value ) {
            $canonical_headers[] = strtolower( $key ) . ':' . $value;
        }

        $canonical_headers = implode( "\n", $canonical_headers );

        $signed_headers = array();

        foreach ( $request_headers as $key => $value ) {
            $signed_headers[] = strtolower( $key );
        }

        $signed_headers = implode( ';', $signed_headers );

        $canonical_request = array();
        $canonical_request[] = 'PUT';
        $canonical_request[] = '/' . $content_title;
        $canonical_request[] = '';
        $canonical_request[] = $canonical_headers;
        $canonical_request[] = '';
        $canonical_request[] = $signed_headers;
        $canonical_request[] = hash( 'sha256', $content );
        $canonical_request = implode( "\n", $canonical_request );
        $hashed_canonical_request = hash( 'sha256', $canonical_request );

        $scope = array();
        $scope[] = $date;
        $scope[] = $this->s3_region;
        $scope[] = $aws_service_name;
        $scope[] = 'aws4_request';

        $string_to_sign = array();
        $string_to_sign[] = 'AWS4-HMAC-SHA256';
        $string_to_sign[] = $timestamp;
        $string_to_sign[] = implode( '/', $scope );
        $string_to_sign[] = $hashed_canonical_request;
        $string_to_sign = implode( "\n", $string_to_sign );

        // Signing key
        $k_secret = 'AWS4' . $this->s3_secret;
        $k_date = hash_hmac( 'sha256', $date, $k_secret, true );
        $k_region =
            hash_hmac( 'sha256', $this->s3_region, $k_date, true );
        $k_service = hash_hmac( 'sha256', $aws_service_name, $k_region, true );
        $k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );

        $signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

        $authorization = [
            'Credential=' . $this->s3_key . '/' .
                implode( '/', $scope ),
            'SignedHeaders=' . $signed_headers,
            'Signature=' . $signature,
        ];

        $authorization =
            'AWS4-HMAC-SHA256' . ' ' . implode( ',', $authorization );

        $curl_headers = [ 'Authorization: ' . $authorization ];

        foreach ( $request_headers as $key => $value ) {
            $curl_headers[] = $key . ': ' . $value;
        }

        $url = 'http://' . $host_name . '/' . $content_title;

        $this->logAction( "S3 URL: {$url}" );

        $ch = curl_init( $url );

        curl_setopt( $ch, CURLOPT_HEADER, false );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $curl_headers );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        // curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
        curl_setopt( $ch, CURLOPT_USERAGENT, 'WP2Static.com' );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
        curl_setopt( $ch, CURLOPT_VERBOSE, 1 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $content );

        $output = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        if ( ! $output ) {
            $this->logAction( "No response from API request, printing cURL error" );
            $response = curl_error( $ch );
            $this->logAction( stripslashes( $response ) );

            throw new Exception(
                'No response from API request, check Debug Log'
            );
        }

        if ( ! $http_code ) {
            $this->logAction( "No response code from API, printing cURL info" );
            $this->logAction( print_r( curl_getinfo( $ch ), true ) );

            throw new Exception(
                'No response code from API, check Debug Log'
            );
        }

        $this->logAction( "API response code: {$http_code}" );
        $this->logAction( "API response body: {$output}" );

        // TODO: pass $ch to checkForValidResponses
        $this->checkForValidResponses(
            $http_code,
            array( '100', '200' )
        );

        curl_close( $ch );
    }

    public function cloudfront_invalidate_all_items() {
        $this->logAction( 'Invalidating all CloudFront items' );

        if ( ! isset( $this->cf_distribution_id ) ) {
            $this->logAction(
                'No CloudFront distribution ID set, skipping invalidation'
            );

            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS'; }

            return;
        }

        $distribution = $this-cf_distribution_id;
        $access_key = $this->s3_key;
        $secret_key = $this->s3_secret;

        $epoch = date( 'U' );

        $xml = <<<EOD
<InvalidationBatch>
    <Path>/*</Path>
    <CallerReference>{$distribution}{$epoch}</CallerReference>
</InvalidationBatch>
EOD;

        $len = strlen( $xml );
        $date = gmdate( 'D, d M Y G:i:s T' );
        $sig = base64_encode(
            hash_hmac( 'sha1', $date, $secret_key, true )
        );
        $msg = 'POST /2010-11-01/distribution/';
        $msg .= "{$distribution}/invalidation HTTP/1.0\r\n";
        $msg .= "Host: cloudfront.amazonaws.com\r\n";
        $msg .= "Date: {$date}\r\n";
        $msg .= "Content-Type: text/xml; charset=UTF-8\r\n";
        $msg .= "Authorization: AWS {$access_key}:{$sig}\r\n";
        $msg .= "Content-Length: {$len}\r\n\r\n";
        $msg .= $xml;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $hostname = 'ssl://cloudfront.amazonaws.com:443';
        $fp = stream_socket_client(
            $hostname,
            $errno,
            $errstr,
            ini_get("default_socket_timeout"),
            STREAM_CLIENT_CONNECT,
            $context
        );


        //$fp = fsockopen(
        //    'ssl://cloudfront.amazonaws.com',
        //    443,
        //    $errno,
        //    $errstr,
        //    30
        //);

        if ( ! $fp ) {
            WsLog::l( "CLOUDFRONT CONNECTION ERROR: {$errno} {$errstr}" );
            die( "Connection failed: {$errno} {$errstr}\n" );
        }

        fwrite( $fp, $msg );
        $resp = '';

        while ( ! feof( $fp ) ) {
            $resp .= fgets( $fp, 1024 );
        }

        $this->logAction( "CloudFront response body: {$resp}" );

        fclose( $fp );

        if ( ! defined( 'WP_CLI' ) ) {
            echo 'SUCCESS';
        }
    }
}

$s3 = new S3();
