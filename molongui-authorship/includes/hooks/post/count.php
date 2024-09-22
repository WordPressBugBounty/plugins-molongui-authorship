<?php

use Molongui\Authorship\Author;
defined( 'ABSPATH' ) or exit;
function authorship_enable_post_count_update()
{
    new \Molongui\Authorship\Update_Post_Counters();
}
add_action( 'authorship/init', 'authorship_enable_post_count_update' );
function authorship_post_counters_update()
{
    if ( apply_filters( 'authorship/check_wp_cron', true ) and ( defined( 'DISABLE_WP_CRON' ) and DISABLE_WP_CRON ) ) return false;

    if ( get_option( 'molongui_authorship_update_post_counters' ) )
    {
        if ( get_option( 'molongui_authorship_update_post_authors', false ) or get_option( 'm_update_post_authors_running', false ) )
        {
            add_action( 'admin_notices', function()
            {
                $message = '<p>' . sprintf( __( '%sAuthorship Data Updater%s - Post counters update will run once the post authorship update process finishes.', 'molongui-authorship' ), '<strong>', '</strong>' ) . '</p>';
                echo '<div class="notice notice-warning is-dismissible">' . $message . '</div>';
            });
        }
        else
        {
            delete_option( 'molongui_authorship_update_post_counters' );
            authorship_update_post_counters();
        }
    }
}
add_action( 'admin_init', 'authorship_post_counters_update', 11 );
function authorship_post_counters_update_completed()
{
    if ( get_option( 'm_update_post_counters_complete' ) )
    {
        delete_option( 'm_update_post_counters_complete' );
        delete_option( 'm_update_post_counters_running' );

        $message = '<p>' . sprintf( __( "%sAuthorship Data Updater%s - The update process is now complete. All post counters have been updated.", 'molongui-authorship' ), '<strong>', '</strong>' ) . '</p>';
        echo '<div class="notice notice-success is-dismissible">' . $message . '</div>';
    }
    elseif ( get_option( 'm_update_post_counters_running' ) )
    {
        $message = '<p>' . sprintf( __( "%sAuthorship Data Updater%s - The posts counter update process is running in the background. This may take some time to complete. Please be patient.", 'molongui-authorship' ), '<strong>', '</strong>' ) . '</p>';
        echo '<div class="notice notice-warning is-dismissible">' . $message . '</div>';
    }
}
add_action( 'admin_notices', 'authorship_post_counters_update_completed' );
function authorship_post_count( $count, $userid, $post_type, $public_only )
{
    $post_count = apply_filters( 'authorship/pre_post_count', null, $count, $userid, $post_type, $public_only );

    if ( !is_null( $post_count ) )
    {
        return apply_filters( 'authorship/post_count', $post_count, $count, $userid, $post_type, $public_only );
    }

    global $wp_query;
    if ( is_guest_author() and isset( $wp_query->guest_author_id ) and !in_the_loop() )
    {
        $author_type = 'guest';
        $author_id   = $wp_query->guest_author_id;
    }
    else
    {
        $author_type = 'user';
        $author_id   = $userid;
    }

    /*!
     * PRIVATE FILTERS.
     *
     * For internal use only. Not intended to be used by plugin or theme developers.
     * Future compatibility NOT guaranteed.
     *
     * Please do not rely on this filter for your custom code to work. As a private filter it is meant to be used only
     * by Molongui. It may be edited, renamed or removed from future releases without prior notice or deprecation phase.
     *
     * If you choose to ignore this notice and use this filter, please note that you do so at on your own risk and
     * knowing that it could cause code failure.
     */
    list( $author_id, $author_type ) = apply_filters( '_authorship/post_count/author', array( $author_id, $author_type ), $count, $userid, $post_type, $public_only );
    $author      = new Author( $author_id, $author_type );
    $post_counts = $author->get_post_count( $post_type );
    $post_count  = array_sum( $post_counts );
    return apply_filters( 'authorship/post_count', $post_count, $count, $userid, $post_type, $public_only );
}
add_filter( 'get_usernumposts', 'authorship_post_count', 999, 4 );