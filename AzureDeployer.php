<?php

class WP2Static_Azure extends WP2Static_SitePublisher {

    public function __construct() {
        // calling outside WP chain, need to specify this
        // Add-on's option keys
        $deploy_keys = array(
          'azure',
          array(
            'baseUrl-azure',
            'azStorageAccountName',
            'azContainerName',
            'azAccessKey',
            'azPath',
          ),
        );

        $this->loadSettings( 'azure', $deploy_keys );

        $this->previous_hashes_path =
            $this->settings['wp_uploads_path'] .
                '/WP2STATIC-AZURE-PREVIOUS-HASHES.txt';

        if ( defined( 'WP_CLI' ) ) {
            return; }

        switch ( $_POST['ajax_action'] ) {
            case 'test_azure':
                $this->test_azure();
                break;
            case 'azure_prepare_export':
                $this->bootstrap();
                $this->loadArchive();
                $this->prepareDeploy();
                break;
            case 'azure_upload_files':
                $this->bootstrap();
                $this->loadArchive();
                $this->upload_files();
                break;
        }
    }

    public function upload_files() {
        $this->files_remaining = $this->getRemainingItemsCount();

        if ( $this->files_remaining < 0 ) {
            echo 'ERROR';
            die(); }

        $batch_size = $this->settings['deployBatchSize'];

        if ( $batch_size > $this->files_remaining ) {
            $batch_size = $this->files_remaining;
        }

        $lines = $this->getItemsToDeploy( $batch_size );

        $this->openPreviousHashesFile();

        require_once dirname( __FILE__ ) .
            '/../static-html-output-plugin' .
            '/plugin/WP2Static/MimeTypes.php';

        foreach ( $lines as $line ) {
            list($local_file, $this->target_path) = explode( ',', $line );

            $local_file = $this->archive->path . $local_file;

            if ( ! is_file( $local_file ) ) {
                continue; }

            if ( isset( $this->settings['azPath'] ) ) {
                $this->target_path =
                    $this->settings['azPath'] . '/' . $this->target_path;
            }

            $this->logAction(
                "Uploading {$local_file} to {$this->target_path} in S3"
            );

            $this->local_file_contents = file_get_contents( $local_file );

            $this->hash_key = $this->target_path . basename( $local_file );

            if ( isset( $this->file_paths_and_hashes[ $this->hash_key ] ) ) {
                $prev = $this->file_paths_and_hashes[ $this->hash_key ];
                $current = crc32( $this->local_file_contents );

                if ( $prev != $current ) {
                    try {
                        $this->put_azure_object(
                            $this->target_path .
                                    basename( $local_file ),
                            $local_file,
                            GuessMimeType( $local_file )
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
                try {
                    $this->put_azure_object(
                        $this->target_path .
                                basename( $local_file ),
                        $local_file,
                        GuessMimeType( $local_file )
                    );

                } catch ( Exception $e ) {
                    $this->handleException( $e );
                }
            }

            $this->recordFilePathAndHashInMemory(
                $this->hash_key,
                $local_file
            );
        }

        $this->writeFilePathAndHashesToFile();

        $this->pauseBetweenAPICalls();

        if ( $this->uploadsCompleted() ) {
            $this->finalizeDeployment();
        }
    }

    public function test_azure() {
        // create temp file with content
        $tmpfname = tempnam(sys_get_temp_dir(), 'WP2STATIC');

        file_put_contents(
            $tmpfname,
            'Test WP2Static connectivity'
        );

        try {
            $this->put_azure_object(
                'tmp_wp2static.txt',
                $tmpfname,
                'text/plain'
            );

            if ( ! defined( 'WP_CLI' ) ) {
                echo 'SUCCESS';
            }
        } catch ( Exception $e ) {
            error_log( $e );
            require_once dirname( __FILE__ ) .
                '/../static-html-output-plugin' .
                '/plugin/WP2Static/WsLog.php';

            WsLog::l( 'AZURE ERROR RETURNED: ' . $e );
            echo "There was an error testing Azure.\n";
        }
    }

    public function put_azure_object( $azure_path, $source_file, $content_type ) {
        // NOTE: override content type for SVGs which Azure chokes on
        if ( $content_type === 'image/svg+xml' ) {
            $content_type = 'image/svg';
        }

        $accesskey = $this->settings['azAccessKey'];
        $storageAccount = $this->settings['azStorageAccountName'];
        $filetoUpload = $source_file;
        $containerName = $this->settings['azContainerName'];


        $blobName = $azure_path;

        $destinationURL = "https://$storageAccount.blob.core.windows.net/$containerName/$blobName";

        $this->logAction( "PUT'ing file to {$azure_path} in Azure" );

        $currentDate = gmdate("D, d M Y H:i:s T", time());

        $handle = fopen($filetoUpload, "r");

        $fileLen = filesize($filetoUpload);

        $headerResource = "x-ms-blob-cache-control:max-age=3600\nx-ms-blob-type:BlockBlob\nx-ms-date:$currentDate\nx-ms-version:2015-12-11";

        $urlResource = "/$storageAccount/$containerName/$blobName";

        $arraysign = array();
        $arraysign[] = 'PUT';               /*HTTP Verb*/
        $arraysign[] = '';                  /*Content-Encoding*/
        $arraysign[] = '';                  /*Content-Language*/
        $arraysign[] = $fileLen;            /*Content-Length (include value when zero)*/
        $arraysign[] = '';                  /*Content-MD5*/
        $arraysign[] = $content_type;         /*Content-Type*/
        $arraysign[] = '';                  /*Date*/
        $arraysign[] = '';                  /*If-Modified-Since */
        $arraysign[] = '';                  /*If-Match*/
        $arraysign[] = '';                  /*If-None-Match*/
        $arraysign[] = '';                  /*If-Unmodified-Since*/
        $arraysign[] = '';                  /*Range*/
        $arraysign[] = $headerResource;     /*CanonicalizedHeaders*/
        $arraysign[] = $urlResource;        /*CanonicalizedResource*/

        $str2sign = implode("\n", $arraysign);

        $sig = base64_encode(hash_hmac('sha256', urldecode(utf8_encode($str2sign)), base64_decode($accesskey), true));
        $authHeader = "SharedKey $storageAccount:$sig";

        $headers = [
            'Authorization: ' . $authHeader,
            'x-ms-blob-cache-control: max-age=3600',
            'x-ms-blob-type: BlockBlob',
            'x-ms-date: ' . $currentDate,
            'x-ms-version: 2015-12-11',
            'Content-Type: ' . $content_type,
            'Content-Length: ' . $fileLen
        ];

        $this->logAction( "Azure URL: {$destinationURL}" );

        $ch = curl_init( $destinationURL );

        curl_setopt( $ch, CURLOPT_HEADER, false );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
        curl_setopt( $ch, CURLOPT_USERAGENT, 'WP2Static.com' );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
        curl_setopt( $ch, CURLOPT_INFILE, $handle );
        curl_setopt( $ch, CURLOPT_INFILESIZE, $fileLen );
        curl_setopt( $ch, CURLOPT_UPLOAD, true );

        $output = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        $this->logAction( "API response code: {$http_code}" );
        $this->logAction( "API response body: {$output}" );

        $this->checkForValidResponses(
            $http_code,
            array( '200', '201' )
        );

        curl_close( $ch );
    }
}

$azure = new WP2Static_Azure();
