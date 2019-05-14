(function( $ ) {
	'use strict';

	$(function() {
    deploy_options['s3'] = {
      exportSteps: [
          's3_prepare_export',
          's3_transfer_files',
          'cloudfront_invalidate_all_items',
          'finalize_deployment'
      ],
      required_fields: {
        s3Key: 'Please input an S3 Key in order to authenticate when using the S3 deployment method.',
        s3Secret: 'Please input an S3 Secret in order to authenticate when using the S3 deployment method.',
        s3Bucket: 'Please input the name of the S3 bucket you are trying to deploy to.',
      }
    };

    status_descriptions['s3_prepare_export'] = 'Preparing files for S3 deployment';
    status_descriptions['s3_transfer_files'] = 'Deploying files to S3';
    status_descriptions['cloudfront_invalidate_all_items'] = 'Invalidating CloudFront cache';
  }); // end DOM ready

})( jQuery );
