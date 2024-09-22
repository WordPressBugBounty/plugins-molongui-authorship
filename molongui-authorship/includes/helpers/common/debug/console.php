<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_console_log( $value = null, $message = '' )
{
    if ( apply_filters( 'authorship/disable_console_log', false ) ) return;

    $hook = is_admin() ? 'admin_footer' : 'wp_footer';

    add_action( $hook, function() use ( $value, $message )
    {
        if ( is_array( $value ) or is_object( $value ) )
        {
            $value = wp_json_encode( $value );
        }
        elseif ( is_string( $value ) )
        {
            $value = '"' . $value . '"';
        }

        if ( defined( 'MOLONGUI_AUTHORSHIP_TITLE' ) )
        {
            $intro = '"' . '%c' . strtoupper( MOLONGUI_AUTHORSHIP_TITLE ) . '\n%c' . $message . '", "background:yellow; color: black; font-weight: bold; text-decoration: underline;", ""';
        }
        else
        {
            $intro = '"' . $message . '"';
        }

        ?>
        <script>
            <?php if ( !empty( $intro ) ) : ?> console.log(<?php echo $intro; ?>); <?php endif; ?>
            <?php if ( !is_null( $value ) ) : ?> console.log(<?php echo $value; ?>); <?php endif; ?>
        </script>
        <?php
    });
}
function authorship_debug( $value = null, $message = '' )
{
    if ( authorship_is_debug_mode_enabled() )
    {
        authorship_console_log( $value, $message );
    }
}
function authorship_is_debug_mode_enabled()
{
    return ( defined( 'MOLONGUI_AUTHORSHIP_DEBUG' ) and MOLONGUI_AUTHORSHIP_DEBUG ) or apply_filters( 'authorship/debug', false );
}