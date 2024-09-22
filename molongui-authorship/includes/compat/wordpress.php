<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $wp_files = array
    (
        'wp-includes/user.php',
        'wp-admin/user-edit.php',
        'wp-activate.php',
        'wp-includes/admin-bar.php',
        'wp-includes/canonical.php',
        'wp-includes/capabilities.php',
        'wp-includes/class-wp-customize-manager.php',
        'wp-includes/comment-template.php',           // todo: Excluding it may fix some compat issues with comments plugins... Check it
        'wp-includes/comment.php',
        'wp-includes/deprecated.php',
        'wp-includes/embed.php',
        'wp-includes/ms-functions.php',
        'wp-includes/pluggable-deprecated.php',
    );

    foreach ( $wp_files as $file )
    {
        foreach ( $dbt as $trace )
        {
            if ( isset( $trace['file'] ) and substr_compare( $trace['file'], $file, strlen( $trace['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
            {
                return $original_user;
            }
        }
    }
    $wp_fns = array
    (
        'retrieve_password',                 // wp-login.php
        'get_pages',                         // wp-includes/post.php
        'wp_validate_auth_cookie',           // wp-includes/pluggable.php
        'check_comment',                     // wp-includes/comment.php
        'get_user_locale',                   // wp-includes/I10n.php
        'wp_authenticate_username_password', // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'wp_authenticate_email_password',    // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'username_exists',                   // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'email_exists',                      // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'check_password_reset_key',          // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'wp_user_personal_data_exporter',    // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'wp_create_user_request',            // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'get_object_subtype',                // wp-includes/meta.php
        'wpmu_signup_blog_notification',     // wp-includes/ms-functions.php
        'wpmu_signup_user_notification',     // wp-includes/ms-functions.php
        'is_user_spammy',                    // wp-includes/ms-functions.php
        'get_posts',                         // wp-includes/class-wp-query.php
        'wp_media_personal_data_exporter',   // wp-includes/media.php
        'get_author_posts_url',              // wp-includes/author-template.php
        'create_item',                       // wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php
        'update_item',                       // wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php
        'get_the_modified_author',           // wp-includes/author-template.php
        'wp_list_authors',                   // wp-includes/author-template.php
        'author_can',                        // wp-includes/capabilities.php. The whole file is already excluded; left here just for reference.
        'user_can',                          // wp-includes/capabilities.php. The whole file is already excluded; left here just for reference.
        'is_super_admin',                    // wp-includes/capabilities.php. The whole file is already excluded; left here just for reference.
        'grant_super_admin',                 // wp-includes/capabilities.php. The whole file is already excluded; left here just for reference.
        'revoke_super_admin',                // wp-includes/capabilities.php. The whole file is already excluded; left here just for reference.
        'wp_getProfile',                     // wp-includes/class-wp-xmlrpc-server.php
        'wp_getUser',                        // wp-includes/class-wp-xmlrpc-server.php
        '_insert_post',                      // wp-includes/class-wp-xmlrpc-server.php
        'get_comment_class',                 // wp-includes/comment-template.php
        'wp_allow_comment',                  // wp-includes/comment.php
        'wp_generate_auth_cookie',           // wp-includes/pluggable.php
        'wp_notify_postauthor',              // wp-includes/pluggable.php
        'wp_notify_moderator',               // wp-includes/pluggable.php
        'wp_new_user_notification',          // wp-includes/pluggable.php
        'get_user_option',                   // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'wp_list_users',                     // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'is_user_member_of_blog',            // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'setup_userdata',                    // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'wp_dropdown_users',                 // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'wp_insert_user',                    // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'wp_update_user',                    // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'update_user_meta',                  // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'register_new_user',                 // wp-includes/user.php. The whole file is excluded; left here for clarity.
        'get_permalink',                     // wp-includes/link-template.php
        'get_edit_user_link',                // wp-includes/link-template.php
        'wp_admin_bar_my_account_menu',      // wp-includes/admin-bar.php
        'wp_admin_bar_my_account_item',      // wp-includes/admin-bar.php
    );

    if ( array_intersect( $wp_fns, array_column( $dbt, 'function' ) ) )
    {
        return $original_user;
    }
    return $user;
}, 10, 4 );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $wp_fns = array
    (
        'wp_list_authors',                   // wp-includes/author-template.php
        'render_block_core_latest_comments', // wp-includes/blocks/latest-comments.php
    );
    if ( array_intersect( $wp_fns, array_column( $dbt, 'function' ) ) )
    {
        return $original_link;
    }
    if ( ( is_author() or is_guest_author() )
          and
          $i = array_search( 'get_author_feed_link', array_column( $dbt, 'function' ) ) )
    {
        return authorship_filter_author_page_link( $original_link );
    }

    return $link;
}, 10, 4 );
add_filter( 'authorship/author_link', function( $url, $args )
{
    $fn_1  = 'get_author_posts_url';
    $fn_2  = 'get_url_list';
    $class = 'WP_Sitemaps_Users';
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

    if ( empty( $dbt ) ) return $url;
    if ( $j = array_search( $fn_1, array_column( $dbt, 'function' ) ) )
    {
        if ( $i = array_search( $fn_2, array_column( $dbt, 'function' ) ) )
        {
            if ( isset( $dbt[$i]['class'] ) and $dbt[$i]['class'] == $class )
            {
                $url = $args['link'];
            }
        }
    }

    return $url;
}, 10, 2 );
add_filter( 'authorship/get_avatar_data/skip', function( $default, $args, $dbt )
{
    $fn = 'post_comment_form_avatar';
    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        return true;
    }
    return $default;
}, 10, 3 );
add_filter( 'authorship/get_avatar_data/skip', function( $default, $args, $dbt )
{
    if ( !is_admin() ) return $default;
    $i    = 4;
    $fn   = 'get_avatar';
    $file = '/wp-admin/options-discussion.php';
    if ( isset( $dbt[$i]['function'] ) and $dbt[$i]['function'] == $fn and
        isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0
    ) return true;
    return $default;
}, 10, 3 );
add_filter( 'authorship/get_avatar_data/skip', function( $default, $args, $dbt )
{
    if ( !is_admin() ) return $default;

    $fn    = 'get_avatar';
    $files = array( '/wp-admin/user-edit.php', '/views/user/html-admin-gravatar-field.php' );
    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        foreach ( $files as $file )
        {
            if ( isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
            {
                return true;
            }
        }
    }
    return $default;
}, 10, 3 );
add_filter( 'authorship/render_box', function( $default )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    if ( empty( $dbt ) ) return $default;
    $wp_fns = array
    (
        'wp_trim_excerpt', // wp-includes/formatting.php
    );
    if ( array_intersect( $wp_fns, array_column( $dbt, 'function' ) ) ) return false;
    return $default;
}, 10, 1 );
if ( version_compare( get_bloginfo( 'version' ),'5.3.0', '<' ) )
{
    if ( !function_exists( 'wp_get_registered_image_subsizes()' ) )
    {
        function wp_get_registered_image_subsizes() {
            $additional_sizes = wp_get_additional_image_sizes();
            $all_sizes        = array();

            foreach ( get_intermediate_image_sizes() as $size_name ) {
                $size_data = array(
                    'width'  => 0,
                    'height' => 0,
                    'crop'   => false,
                );

                if ( isset( $additional_sizes[ $size_name ]['width'] ) ) {
                    $size_data['width'] = (int) $additional_sizes[ $size_name ]['width'];
                } else {
                    $size_data['width'] = (int) get_option( "{$size_name}_size_w" );
                }

                if ( isset( $additional_sizes[ $size_name ]['height'] ) ) {
                    $size_data['height'] = (int) $additional_sizes[ $size_name ]['height'];
                } else {
                    $size_data['height'] = (int) get_option( "{$size_name}_size_h" );
                }

                if ( empty( $size_data['width'] ) && empty( $size_data['height'] ) ) {
                    continue;
                }

                if ( isset( $additional_sizes[ $size_name ]['crop'] ) ) {
                    $size_data['crop'] = $additional_sizes[ $size_name ]['crop'];
                } else {
                    $size_data['crop'] = get_option( "{$size_name}_crop" );
                }

                if ( ! is_array( $size_data['crop'] ) || empty( $size_data['crop'] ) ) {
                    $size_data['crop'] = (bool) $size_data['crop'];
                }

                $all_sizes[ $size_name ] = $size_data;
            }

            return $all_sizes;
        }
    }
}