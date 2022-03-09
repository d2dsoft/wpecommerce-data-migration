<?php

/**
 * D2dSoft
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL v3.0) that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL: https://d2d-soft.com/license/AFL.txt
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this extension/plugin/module to newer version in the future.
 *
 * @author     D2dSoft Developers <developer@d2d-soft.com>
 * @copyright  Copyright (c) 2021 D2dSoft (https://d2d-soft.com)
 * @license    https://d2d-soft.com/license/AFL.txt
 */

/* @var $this D2dWpeMigration */ ?>
<div class="wrap">
    <h1 class="wp-heading-inline">Wp-eCommerce Migration</h1>
    <hr class="wp-header-end" />
    <div id="migration-page">
        <div class="page-header">
            <div class="current-title title">Migration</div>
            <div class="redirect-icon"><a href="<?php echo $this->getPluginPage('setting') ?>" title="Setting"><span class="icon-setting"></span></a></div>
        </div>
        <?php echo $html_content; ?>
    </div>
</div>
<script type="text/javascript">
    jQuery(document).ready(function(){
        jQuery.MigrationData({
            <?php foreach($js_config as $key => $value) { ?>
                <?php echo $key ?>: '<?php echo $value ?>',
            <?php } ?>
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            request_post: {
                action_type: 'import',
                action: 'd2d_wpemigration'
            },
            request_download: {
                action_type: 'download',
                action: 'd2d_wpemigration'
            }
        });
    });
</script>