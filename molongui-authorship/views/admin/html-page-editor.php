<?php

use Molongui\Authorship\Admin\Author_Box_Editor;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

define( 'MOLONGUI_AUTHORSHIP_IS_EDITOR', true );

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/nano.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.es5.min.js"></script>

<div class="molongui-authorship-editor">

    <?php include MOLONGUI_AUTHORSHIP_DIR . 'common/modules/settings/views/partials/html-part-header.php'; ?>

    <!-- Page Content -->
    <div class="m-page-content">

        <div class="m-editor-container">

            <div id="m-editor-property-info">
                <div class="m-editor-property-info__arrow"></div>
                <div class="m-editor-property-info__inner">
                    <div class="m-editor-property-info__title"></div>
                    <div class="m-editor-property-info__desc"></div>
                    <div class="m-editor-property-info__tip"></div>
                    <div class="m-editor-property-info__more"></div>
                </div>
            </div>

            <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/admin/editor/html-editor-controls.php'; ?>

            <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/admin/editor/html-editor-preview.php'; ?>

        </div>

    </div><!-- !m-page-content -->

    <script id="m-editor-js-extra">
        const m_editor = {
            "url"              : "<?php echo Author_Box_Editor::url(); ?>",
            "nonce"            : "<?php echo wp_create_nonce( 'authorship_box_editor_nonce' ); ?>",
            "learn_more"       : "<?php _e( "Learn more", 'molongui-authorship' ); ?>",
            "premium_warnings" : "<?php echo apply_filters( 'authorship/editor/show_premium_warnings', true ); ?>",
            "author_error"     : "<?php _e( "Something went wrong and the author box cannot be previewed for the selected author. Please save changes and refresh the page.", 'molongui-authorship' ); ?>",

            "preset" :
            {
                "title"      : "<?php _e( "About the author", 'molongui-authorship' ); ?>",
                "at"         : "<?php _e( "at", 'molongui-authorship' ); ?>",
                "web"        : "<?php _e( "Web", 'molongui-authorship' ); ?>",
                "more"       : "<?php _e( "+ posts", 'molongui-authorship' ); ?>",
                "bio"        : "<?php _e( "Bio ⮌", 'molongui-authorship' ); ?>",
                "profile"    : "<?php _e( "Author Profile", 'molongui-authorship' ); ?>",
                "related"    : "<?php _e( "Related Posts", 'molongui-authorship' ); ?>",
                "no_related" : "<?php _e( "This author does not have any more posts.", 'molongui-authorship' ); ?>",

                "p2" :
                {
                    "more" : "<?php _e( "More Posts", 'molongui-authorship' ); ?>",
                },
                "p5" :
                {
                    "title" : "<?php _e( "About Me", 'molongui-authorship' ); ?>",
                    "more"  : "<?php _e( "More Posts", 'molongui-authorship' ); ?>",
                },
                "p9" :
                {
                    "profile" : "<?php _e( "About the author", 'molongui-authorship' ); ?>",
                    "related" : "<?php _e( "More from this author", 'molongui-authorship' ); ?>",
                },
            },
        };
    </script>
    <script src="<?php echo MOLONGUI_AUTHORSHIP_URL . 'assets/js/author-box-presets.min.js'; ?>"></script>

    <?php
    include MOLONGUI_AUTHORSHIP_DIR . 'common/modules/settings/views/partials/html-part-footer.php';

    Settings::enqueue_styles();
    Author_Box_Editor::enqueue_styles();
    Author_Box_Editor::enqueue_scripts();
    ?>

</div><!-- !molongui-authorship-editor -->

<div id="m-options-saving"><div class="m-loader"><div></div><div></div><div></div><div></div></div></div>
<div id="m-options-saved"><span class="dashicons dashicons-yes"></span><strong><?php echo __( "Saved", 'molongui-authorship' ); ?></strong></div>
<div id="m-options-error"><span class="dashicons dashicons-no"></span><strong><?php echo __( "Error", 'molongui-authorship' ); ?></strong></div>
