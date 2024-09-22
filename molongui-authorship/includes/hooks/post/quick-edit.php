<?php

use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\WP;
use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_post_quick_edit_remove_author()
{
    global $pagenow, $post_type;

    $post_types = molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' );

    if ( 'edit.php' == $pagenow and authorship_byline_takeover() and in_array( $post_type, $post_types ) )
    {
        remove_post_type_support( $post_type, 'author' );
    }
}
function authorship_post_quick_edit_add_fields( $column_name, $post_type )
{
if ( !authorship_byline_takeover() )
{
    return;
}
    $post_types = molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' );
    if ( !in_array( $post_type, $post_types ) )
    {
        return;
    }
    if ( $column_name == 'molongui-author' ) : ?>

        <br class="clear" />
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <h4><?php _e( "Authorship data", 'molongui-authorship' ); ?></h4>
                <div class="inline-edit-group wp-clearfix">
                    <label class="inline-edit-authors alignleft" style="width: 100%;">
                        <span class="title"><?php authorship_is_feature_enabled( 'multi' ) ? _e( "Authors", 'molongui-authorship' ) : _e( "Author" ); ?></span>
                            <?php Post::author_selector( null, 'quick' ); ?>
                            <?php wp_nonce_field( 'molongui_authorship_quick_edit', 'molongui_authorship_quick_edit_nonce' ); ?>
                    </label>
                </div>
            </div>
        </fieldset>

    <?php
    elseif ( $column_name == 'molongui-box' ) : ?>

        <br class="clear" />
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <div class="inline-edit-group wp-clearfix">
                    <label class="inline-edit-box-display alignleft">
                        <span class="title"><?php _e( "Author box", 'molongui-authorship' ); ?></span>
                        <select name="_molongui_author_box_display">
                            <option value="default" ><?php _e( "Default", 'molongui-authorship' ); ?></option>
                            <option value="show"    ><?php _e( "Show"   , 'molongui-authorship' ); ?></option>
                            <option value="hide"    ><?php _e( "Hide"   , 'molongui-authorship' ); ?></option>
                        </select>
                    </label>
                </div>
            </div>
            <?php wp_nonce_field( 'molongui_authorship_quick_edit', 'molongui_authorship_quick_edit_nonce' ); ?>
        </fieldset>

    <?php endif;
}
function authorship_post_quick_edit_fill_fields()
{
    if ( !authorship_byline_takeover() )
    {
        return;
    }
    $current_screen = get_current_screen();
    if ( substr( $current_screen->id, 0, strlen( 'edit-' ) ) != 'edit-' or !in_array( $current_screen->id, molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' ) ) )
    {
        return;
    }
    wp_enqueue_script( 'jquery' );

    ob_start();
    ?>

    <script type="text/javascript">
        jQuery(function($)
        {
            const $inline_editor = inlineEditPost.edit;
            inlineEditPost.edit = function(id)
            {
                $inline_editor.apply(this, arguments);
                let post_id = 0;
                if ( typeof(id) === 'object' )
                {
                    post_id = parseInt(this.getId(id));
                }
                if ( post_id !== 0 )
                {
                    const $q_editor = $('#edit-' + post_id);
                    const $post_row = $('#post-' + post_id);
                    const authorList = $q_editor.find('.molongui-post-authors__list');
                    if ( typeof(authorList) !== 'undefined' && authorList !== null )
                    {
                        $post_row.find('.molongui-author p').each(function(index, item)
                        {
                            const $ref = $(item).data('author-type') + '-' + $(item).data('author-id');
                            const $div = '<div id="' + $ref + '" class="molongui-post-authors__item molongui-post-authors__item--' + $(item).data('author-type') + '" data-author-id="' + $(item).data('author-id') + '" data-author-type="' + $(item).data('author-type') + '" data-author-ref="' + $ref + '">' +
                                '<div class="molongui-post-authors__row">' +
                                    '<div class="molongui-post-authors__actions">' +
                                        '<span class="dashicons dashicons-no-alt molongui-post-authors__delete" title="Delete"></span>' +
                                        '<span class="dashicons dashicons-arrow-up-alt2 molongui-post-authors__up" data-direction="up" title="Move up"></span>' +
                                        '<span class="dashicons dashicons-arrow-down-alt2 molongui-post-authors__down" data-direction="down" title="Move down"></span>' +
                                    '</div>' +
                                    '<div class="molongui-post-authors__name" title="">' + $(item).data('author-display-name') + '</div>' +
                                    '<input type="hidden" name="molongui_post_authors[]" value="' + $ref + '">' +
                                    '</div>' +
                                '</div>';
                            authorList.append($div);
                        });
                        $.molonguiAuthorshipInitAuthorPicker( $q_editor.find('#molongui-post-authors') );
                    }
                    let $box_display = $('#box_display_' + post_id).data('display-box');
                    if ( $box_display === '' )
                    {
                        $box_display = 'default';
                    }
                    $q_editor.find('[name="_molongui_author_box_display"]').val($box_display);
                    $q_editor.find('[name="_molongui_author_box_display"]').children('[value="' + $box_display + '"]').attr('selected', true);
                }
            };
        });
    </script>
    <?php

    echo Helpers::minify_js( ob_get_clean() );
}
function authorship_post_quick_edit_save_fields( $post_id, $post )
{
    if ( !WP::verify_nonce( 'molongui_authorship_quick_edit' ) )
    {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) and DOING_AUTOSAVE )
    {
        return;
    }
    if ( !authorship_byline_takeover() )
    {
        return;
    }
    if ( !in_array( $post->post_type, molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all' ) ) )
    {
        return;
    }
    if ( !current_user_can( 'edit_post', $post_id ) )
    {
        return;
    }
    Post::update_authors( $_POST['molongui_post_authors'], $post_id, $post->post_type, $post->post_author );
    if ( isset( $_POST['_molongui_author_box_display'] ) )
    {
        update_post_meta( $post_id, '_molongui_author_box_display', sanitize_text_field( $_POST['_molongui_author_box_display'] ) );
    }
}
