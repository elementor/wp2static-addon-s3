<h2>S3 Deployment Options</h2>

<form
    name="wp2static-s3-save-options"
    method="POST"
    action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">

    <?php wp_nonce_field( $view['nonce_action'] ); ?>
    <input name="action" type="hidden" value="wp2static_s3_save_options" />

<table class="widefat striped">
    <tbody>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['s3Bucket']->name; ?>"
                ><?php echo $view['options']['s3Bucket']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['s3Bucket']->name; ?>"
                    name="<?php echo $view['options']['s3Bucket']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['s3Bucket']->value !== '' ? $view['options']['s3Bucket']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['s3Region']->name; ?>"
                ><?php echo $view['options']['s3Region']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['s3Region']->name; ?>"
                    name="<?php echo $view['options']['s3Region']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['s3Region']->value !== '' ? $view['options']['s3Region']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['s3AccessKeyID']->name; ?>"
                ><?php echo $view['options']['s3AccessKeyID']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['s3AccessKeyID']->name; ?>"
                    name="<?php echo $view['options']['s3AccessKeyID']->name; ?>"
                    type="password"
                    value="<?php echo $view['options']['s3AccessKeyID']->value !== '' ? $view['options']['s3AccessKeyID']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['s3SecretAccessKey']->name; ?>"
                ><?php echo $view['options']['s3SecretAccessKey']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['s3SecretAccessKey']->name; ?>"
                    name="<?php echo $view['options']['s3SecretAccessKey']->name; ?>"
                    type="password"
                    value="<?php echo $view['options']['s3SecretAccessKey']->value !== '' ? $view['options']['s3SecretAccessKey']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['s3RemotePath']->name; ?>"
                ><?php echo $view['options']['s3RemotePath']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['s3RemotePath']->name; ?>"
                    name="<?php echo $view['options']['s3RemotePath']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['s3RemotePath']->value !== '' ? $view['options']['s3RemotePath']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['cfDistributionID']->name; ?>"
                ><?php echo $view['options']['cfDistributionID']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['cfDistributionID']->name; ?>"
                    name="<?php echo $view['options']['cfDistributionID']->name; ?>"
                    type="text"
                    value="<?php echo $view['options']['cfDistributionID']->value !== '' ? $view['options']['cfDistributionID']->value : ''; ?>"
                />
            </td>
        </tr>


    </tbody>
</table>

<br>

    <button class="button btn-primary">Save S3 Options</button>
</form>

