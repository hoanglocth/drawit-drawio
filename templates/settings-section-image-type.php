<?php

/**
 * Image type settings section template
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}
?>
<p>These settings specify if you would like to allow uploading of images in SVG format and the default image type to save as (either PNG or SVG). Note that whatever you choose for the default selection can be overridden per-diagram when saving a diagram.</p>
<p class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-warn-svg">
    <strong>WARNING:</strong> If you plan to use SVG images, you should be aware that you may have visual problems when viewed in ALL versions of Internet Explorer, which does not support the usage of the &quot;foreignObject&quot; tags that are used in these SVG images. These SVGs and the foreignObject tags are supported in pretty much any other modern browser, including Microsoft's new Edge browser.
</p>