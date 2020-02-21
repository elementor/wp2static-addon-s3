<h2>S3 Deployment Options</h2>


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
                    for="<?php echo $view['options']['basicAuthPassword']->name; ?>"
                ><?php echo $view['options']['basicAuthPassword']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['basicAuthPassword']->name; ?>"
                    name="<?php echo $view['options']['basicAuthPassword']->name; ?>"
                    type="password"
                    value="<?php echo $view['options']['basicAuthPassword']->value !== '' ? $view['options']['basicAuthPassword']->value : ''; ?>"
                />
            </td>
        </tr>

        <tr>
            <td style="width:50%;">
                <label
                    for="<?php echo $view['options']['includeDiscoveredAssets']->name; ?>"
                ><?php echo $view['options']['includeDiscoveredAssets']->label; ?></label>
            </td>
            <td>
                <input
                    id="<?php echo $view['options']['includeDiscoveredAssets']->name; ?>"
                    name="<?php echo $view['options']['includeDiscoveredAssets']->name; ?>"
                    value="1"
                    type="checkbox"
                    <?php echo (int) $view['options']['includeDiscoveredAssets']->value === 1 ? 'checked' : ''; ?>
                />
            </td>
        </tr>

    </tbody>
</table>



