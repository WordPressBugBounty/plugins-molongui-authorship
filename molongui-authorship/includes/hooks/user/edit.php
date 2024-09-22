<?php

use Molongui\Authorship\Author;
defined( 'ABSPATH' ) or exit;
function authorship_user_add_profile_fields( $user )
{
    if ( is_object( $user ) )
    {
        if ( !current_user_can( 'edit_user', $user->ID ) )
        {
            if ( !current_user_can( 'read', $user_id ) or get_current_user_id() !== $user_id )
            {
                return;
            }
        }
        $match = array_intersect( $user->roles, apply_filters( 'authorship/user/roles', array( 'administrator', 'editor', 'author', 'contributor' ) ) );
        if ( empty( $match ) )
        {
            return;
        }
    }
    else
    {
        if ( 'add-new-user' !== $user )
        {
            return;
        }

        $user     = new stdClass();
        $user->ID = 0;
    }
    authorship_enqueue_edit_user_scripts();
    wp_nonce_field('molongui_authorship_update_user', 'molongui_authorship_update_user_nonce');
    if ( authorship_is_feature_enabled( 'user_profile' ) )
    {
        include MOLONGUI_AUTHORSHIP_DIR . 'views/user/html-admin-plugin-fields.php';
    }
    elseif ( authorship_is_feature_enabled( 'avatar' ) )
    {
        include MOLONGUI_AUTHORSHIP_DIR . 'views/user/html-admin-profile-picture.php';
    }
}
function authorship_user_filter_profile_picture_description( $description, $profileuser )
{
    $add          = ' ';
    $user_profile = authorship_is_feature_enabled( 'user_profile' );
    $local_avatar = authorship_is_feature_enabled( 'avatar' );
    if ( $user_profile and $local_avatar )
    {
        $add .= sprintf( __( 'Or you can upload a custom profile picture using %sMolongui Authorship field%s.', 'molongui-authorship' ), '<a href="#molongui-user-fields">', '</a>' );
    }
    elseif ( !$user_profile and $local_avatar )
    {
        $add .= __( 'Or you can upload a custom profile using the "Local Avatar" field below.', 'molongui-authorship' );
    }
    else
    {
        $add .= sprintf( __( 'Or you can upload a custom profile picture enabling Molongui Authorship "Local Avatar" feature %shere%s.', 'molongui-authorship' ), '<a href="' . authorship_options_url( 'users' ) . '" target="_blank">', '</a>' );
    }

    return $description . $add;
}
function authorship_user_save_profile_fields( $user_id )
{
    if ( !current_user_can( 'edit_user', $user_id ) )
    {
        if ( !current_user_can( 'read', $user_id ) or get_current_user_id() !== $user_id )
        {
            return $user_id;
        }
    }
    if ( !isset( $_POST['molongui_authorship_update_user_nonce'] ) or !wp_verify_nonce( $_POST['molongui_authorship_update_user_nonce'], 'molongui_authorship_update_user' ) )
    {
        return $user_id;
    }
    if ( authorship_is_feature_enabled( 'user_profile' ) )
    {
        update_user_meta( $user_id, 'molongui_author_phone', ( isset( $_POST['molongui_author_phone'] ) ? sanitize_text_field( $_POST['molongui_author_phone'] ) : '' ) );
        update_user_meta( $user_id, 'molongui_author_job', ( isset( $_POST['molongui_author_job'] ) ? sanitize_text_field( $_POST['molongui_author_job'] ) : '' ) );
        update_user_meta( $user_id, 'molongui_author_company', ( isset( $_POST['molongui_author_company'] ) ? sanitize_text_field( $_POST['molongui_author_company'] ) : '' ) );
        update_user_meta( $user_id, 'molongui_author_company_link', ( isset( $_POST['molongui_author_company_link'] ) ? sanitize_url( $_POST['molongui_author_company_link'] ) : '' ) );
        update_user_meta( $user_id, 'molongui_author_custom_link', ( isset( $_POST['molongui_author_custom_link'] ) ? sanitize_url( $_POST['molongui_author_custom_link'] ) : '' ) );

        foreach ( authorship_get_social_networks( 'enabled' ) as $id => $network )
        {
            if ( !empty( $_POST['molongui_author_' . $id] ) ) update_user_meta( $user_id, 'molongui_author_' . $id, sanitize_text_field( $_POST['molongui_author_' . $id] ) );
            else delete_user_meta( $user_id, 'molongui_author_' . $id );
        }
        $checkboxes = array
        (
            'molongui_author_show_meta_mail',
            'molongui_author_show_meta_phone',
            'molongui_author_show_icon_mail',
            'molongui_author_show_icon_web',
            'molongui_author_show_icon_phone',
            'molongui_author_archived',
        );
        foreach ( $checkboxes as $checkbox )
        {
            if ( isset( $_POST[$checkbox] ) ) update_user_meta( $user_id, $checkbox, sanitize_text_field( $_POST[$checkbox] ) );
            else delete_user_meta( $user_id, $checkbox );
        }
        update_post_meta( $user_id, 'molongui_author_box_display', 'default' );
        do_action( 'authorship/user/save', $user_id, $_POST );
    }
    if ( authorship_is_feature_enabled( 'avatar' ) )
    {
        if ( current_user_can( 'upload_files', $user_id ) )
        {
            if ( isset( $_POST['molongui_author_image_id']   ) ) update_user_meta( $user_id, 'molongui_author_image_id'  , sanitize_text_field( $_POST['molongui_author_image_id'] ) );
            if ( isset( $_POST['molongui_author_image_url']  ) ) update_user_meta( $user_id, 'molongui_author_image_url' , sanitize_url( $_POST['molongui_author_image_url'] )  );
            if ( isset( $_POST['molongui_author_image_edit'] ) ) update_user_meta( $user_id, 'molongui_author_image_edit', sanitize_url( $_POST['molongui_author_image_edit'] ) );
        }
    }
}
function authorship_user_delete( $user_id, $reassign )
{
    if ( $reassign === null ) return;

    $author     = new Author( $user_id, 'user' );
    $user_posts = $author->get_posts( array( 'fields' => 'ids', 'post_type' => 'all' ) );

    add_filter( 'authorship/admin/user/delete', function() use ( $user_posts )
    {
        return $user_posts;
    } );
}
function authorship_user_deleted( $user_id, $reassign )
{
    if ( $reassign === null ) return;
    $post_ids = apply_filters( 'authorship/admin/user/delete', array() );
    if ( empty( $post_ids ) ) return;
    $old_usr = 'user-' . $user_id;
    $new_usr = 'user-' . $reassign;
    foreach ( $post_ids as $post_id )
    {
        delete_post_meta( $post_id, '_molongui_author', $old_usr );
        if ( get_post_meta( $post_id, '_molongui_main_author', true ) === $old_usr )
        {
            update_post_meta( $post_id, '_molongui_main_author', $new_usr, $old_usr );
            update_post_meta( $post_id, '_molongui_author', $new_usr );
        }
    }
    authorship_update_post_counters( 'all', $new_usr );
}
//add_action( 'deleted_user', 'authorship_user_deleted', 10, 2 );