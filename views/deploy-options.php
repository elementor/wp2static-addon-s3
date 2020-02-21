<?php
/**
 * @package WP2Static
 *
 * Copyright (c) 2011 Leon Stafford
 */

?>

<hr>

<h3>Zip Deployment Options</h3>

<label
    for="<?php echo $view['wp2static_zip_addon_options']['deployment_url']->name; ?>"
>Deployment URL</label>

<input
    type="url"
    name="<?php echo $view['wp2static_zip_addon_options']['deployment_url']->name; ?>"
    id="<?php echo $view['wp2static_zip_addon_options']['deployment_url']->name; ?>"
    value="<?php echo $view['wp2static_zip_addon_options']['deployment_url']->value; ?>"
    required
>
    
<p><i><?php echo $view['wp2static_zip_addon_options']['deployment_url']->description; ?></i></p>
