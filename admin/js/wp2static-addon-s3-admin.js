(function( $ ) {
	'use strict';

	$(function() {
    deploy_options['s3'] = {
      exportSteps: [
          's3_prepare_export',
          's3_upload_files',
          'finalize_deployment'
      ],
      required_fields: {
        azStorageAccountName: 'Please specify your Storage Account Name in order to deploy to S3.',
        azContainerName: 'Please specify your Container Name in order to deploy to S3.',
        azAccessKey: 'Please specify your Access Key for this Storage/Container.'
      }
    };

    status_descriptions['s3_prepare_export'] = 'Preparing to deploy to Microsoft S3 Storage';
    status_descriptions['s3_upload_files'] = 'Uploading files to Microsoft S3 Storage';

  }); // end DOM ready

})( jQuery );
