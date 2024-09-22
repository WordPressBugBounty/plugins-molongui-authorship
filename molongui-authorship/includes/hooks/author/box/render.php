<?php

use Molongui\Authorship\Common\Modules\Settings;
use Molongui\Authorship\Common\Utils\Debug;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_autoadd_box()
{
    $options = Settings::get();
    if ( empty( $options['author_box'] ) )
    {
        Debug::console_log( null, __( "The author box feature is disabled.", 'molongui-authorship' ) );
        return;
    }
    if ( empty( $options['box_hook_priority'] ) )
    {
        $options['box_hook_priority'] = 11;
    }
    if ( $options['box_hook_priority'] <= 10 )
    {
        remove_filter( 'the_content', 'wpautop' );
        add_filter( 'the_content', 'wpautop', $options['box_hook_priority'] - 1 );
    }
    add_filter( 'the_content', 'authorship_render_box', $options['box_hook_priority'], 1 );
}
add_action( 'init', 'authorship_autoadd_box' );
function authorship_dont_autoadd_box()
{
    $autoadd = false;
    if ( is_single() or is_page() )
    {
        if ( is_main_query() )
        {
            if ( in_the_loop() )
            {
                $autoadd = true;
            }
            else
            {
                $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
                $fn  = 'get_the_block_template_html';

                if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
                {
                    $autoadd = true;
                }
                else
                {
                    authorship_debug( null, __( "Automatic display for the author box has been disabled because we are running out of the Loop.", 'molongui-authorship' ) );
                }
            }
        }
        else
        {
            authorship_debug( null, __( "Automatic display for the author box has been disabled because it is not the main query.", 'molongui-authorship' ) );
        }
    }
    elseif ( wp_doing_ajax() and is_main_query() )
    {
        $autoadd = true;
    }
    else
    {
        authorship_debug( null, __( "Automatic display for the author box has been disabled because the request is not for a post/page nor an ajax request.", 'molongui-authorship' ) );
    }

    return $autoadd;
}
add_filter( 'authorship/render_box', 'authorship_dont_autoadd_box', 9 );