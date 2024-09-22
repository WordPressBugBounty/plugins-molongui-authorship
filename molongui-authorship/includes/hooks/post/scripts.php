<?php

use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\User;
use Molongui\Authorship\Common\Utils\Assets;
defined( 'ABSPATH' ) or exit;
function authorship_register_edit_post_scripts()
{
    $file = apply_filters( 'authorship/edit_post/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/edit-post.9aba.min.js' );

    $deps = array( 'jquery', 'suggest', 'wp-blocks', 'wp-i18n', 'wp-edit-post' );
    if ( authorship_is_feature_enabled( 'multi' ) )
    {
        $deps[] = 'jquery-ui-sortable';
    }

    Assets::register_script( $file, 'edit_post', $deps );
}
function authorship_enqueue_edit_post_scripts()
{
    $screen = get_current_screen();
    if ( !in_array( $screen->id, molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' ) )
         or
        ( !current_user_can( 'edit_others_posts' ) and !current_user_can( 'edit_others_pages' ) )
    )
    {
        return;
    }
    $file = apply_filters( 'authorship/edit_post/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/edit-post.9aba.min.js' );

    Assets::enqueue_script( $file, 'edit_post', true );
}
function authorship_edit_post_script_params()
{
    $ajax_suggest_link = add_query_arg( array
    (
        'action'    => 'authors_ajax_suggest',
        'post_type' => rawurlencode( get_post_type() ),
    ), wp_nonce_url( 'admin-ajax.php', 'authors-search' ) );

    $params = array
    (
'guest_enabled'          => authorship_is_feature_enabled( 'guest' ),
        'coauthors_enabled'      => authorship_is_feature_enabled( 'multi' ),
'remove_author_tip'      => esc_html__( "Remove author from selection", 'molongui-authorship' ),

        'tag_title'              => esc_html__( "Drag this author to reorder", 'molongui-authorship' ),
        'delete_label'           => esc_html__( "Remove", 'molongui-authorship' ),
        'up_label'               => esc_html__( "Move up", 'molongui-authorship' ),
        'down_label'             => esc_html__( "Move down", 'molongui-authorship' ),
        'confirm_delete'         => esc_html__( "Are you sure you want to remove this author?", 'molongui-authorship' ),
        'one_author_required'    => esc_html__( "Every post must have at least one author. You can remove the current author, but if you don't add a new one before saving, you will be assigned as the post author. Are you sure you want to proceed?", 'molongui-authorship' ),
        'ajax_suggest_link'      => $ajax_suggest_link,

        'new_author_required'    => esc_html__( "All fields are mandatory. Please fill them all in to proceed.", 'molongui-authorship' ),
        'new_author_wrong_email' => esc_html__( "Invalid email. Please enter a valid email address.", 'molongui-authorship' ),
        'new_author_confirm'     => esc_html__( "Are you sure you want to add this new author? To add an existing author, use the search box instead.", 'molongui-authorship' ),
        'new_author_added'       => esc_html__( "New author created and added to this post. You can complete their profile in the Authors > View All screen.", 'molongui-authorship' ),
        'new_author_ajax_error'  => esc_html__( "ERROR: Connection to the backend failed. The author has not be added.", 'molongui-authorship' ),

        'debug_mode'             => Debug::is_enabled(),
    );
    return apply_filters( 'authorship/edit_post/script_params', $params );
}