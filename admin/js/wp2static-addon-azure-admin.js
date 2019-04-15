(function( $ ) {
	'use strict';

	$(function() {
    deploy_options['azure'] = {
      exportSteps: [
          'azure_prepare_export',
          'azure_upload_files',
          'finalize_deployment'
      ],
      required_fields: {
        azStorageAccountName: 'Please specify your Storage Account Name in order to deploy to Azure.',
        azContainerName: 'Please specify your Container Name in order to deploy to Azure.',
        azAccessKey: 'Please specify your Access Key for this Storage/Container.'
      }
    };

    status_descriptions['azure_prepare_export'] = 'Preparing to deploy to Microsoft Azure Storage';
    status_descriptions['azure_upload_files'] = 'Uploading files to Microsoft Azure Storage';

  }); // end DOM ready

})( jQuery );
