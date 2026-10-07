<?php

use Molongui\Authorship\Author_Filters;

defined( 'ABSPATH' ) || exit;  


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
        'wp-includes/comment-template.php',            
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
        'retrieve_password',                  
        'get_pages',                          
        'wp_validate_auth_cookie',            
        'check_comment',                      
        'get_user_locale',                    
        'wp_authenticate_username_password',  
        'wp_authenticate_email_password',     
        'username_exists',                    
        'email_exists',                       
        'check_password_reset_key',           
        'wp_user_personal_data_exporter',     
        'wp_create_user_request',             
        'get_object_subtype',                 
        'wpmu_signup_blog_notification',      
        'wpmu_signup_user_notification',      
        'is_user_spammy',                     
        'get_posts',                          
        'wp_media_personal_data_exporter',    
        'get_author_posts_url',               
        'create_item',                        
        'update_item',                        

        'get_the_modified_author',            
        'wp_list_authors',                    
        'author_can',                         
        'user_can',                           
        'is_super_admin',                     
        'grant_super_admin',                  
        'revoke_super_admin',                 
        'wp_getProfile',                      
        'wp_getUser',                         
        '_insert_post',                       
        'get_comment_class',                  
        'wp_allow_comment',                   
        'wp_generate_auth_cookie',            
        'wp_notify_postauthor',               
        'wp_notify_moderator',                
        'wp_new_user_notification',           
        'get_user_option',                    
        'wp_list_users',                      
        'is_user_member_of_blog',             
        'setup_userdata',                     
        'wp_dropdown_users',                  
        'wp_insert_user',                     
        'wp_update_user',                     
        'update_user_meta',                   
        'register_new_user',                  
        'get_permalink',                      
        'get_edit_user_link',                 

        'wp_admin_bar_my_account_menu',       
        'wp_admin_bar_my_account_item',       
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
        'wp_list_authors',                    
        'render_block_core_latest_comments',  
    );
    if ( array_intersect( $wp_fns, array_column( $dbt, 'function' ) ) )
    {
        return $original_link;
    }

    if ( ( is_author() or molongui_is_guest_author() )
          and
          $i = array_search( 'get_author_feed_link', array_column( $dbt, 'function' ) ) )
    {
        return Author_Filters::the_author_page_link( $original_link );
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

add_filter( 'authorship/get_avatar_data/skip', function ( $skip, $args, $backtrace ) {

	if ( $skip || ! is_admin() ) {
		return $skip;
	}

	$index = array_search(
		'get_avatar',
		array_column( $backtrace, 'function' ),
		true
	);

	if ( false === $index || empty( $backtrace[ $index ]['file'] ) ) {
		return $skip;
	}

	$caller_file = wp_normalize_path( $backtrace[ $index ]['file'] );
	$legacy_file = '/views/user/html-admin-gravatar-field.php';

	if ( substr( $caller_file, -strlen( $legacy_file ) ) === $legacy_file ) {
		return true;
	}

	return $skip;
}, 10, 3 );


add_filter( 'molongui_authorship/display_author_box', function( $default )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    if ( empty( $dbt ) )
    {
        return $default;
    }

    $wp_fns = array
    (
        'get_the_excerpt',  
        'wp_trim_excerpt',  
    );

    if ( array_intersect( $wp_fns, array_column( $dbt, 'function' ) ) )
    {
        return false;
    }

    return $default;
} );


if ( version_compare( get_bloginfo( 'version' ),'5.3.0', '<' ) )
{
    if ( ! function_exists( 'wp_get_registered_image_subsizes' ) )
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
