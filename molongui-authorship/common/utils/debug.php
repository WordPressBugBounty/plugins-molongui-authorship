<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Debug
{
    public static function is_enabled()
    {
        return apply_filters( 'authorship/debug', defined( 'MOLONGUI_AUTHORSHIP_DEBUG' ) and MOLONGUI_AUTHORSHIP_DEBUG );
    }
    public static function console_log( $value = null, $message = '' )
    {
        if ( self::is_enabled() )
        {
            if ( apply_filters( 'authorship/disable_console_log', false ) )
            {
                return;
            }

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
                    <?php if ( !empty( $intro ) ) : ?> console.log(<?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>); <?php endif; ?>
                    <?php if ( !is_null( $value ) ) : ?> console.log(<?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>); <?php endif; ?>
                </script>
                <?php
            });
        }
    }
    public static function dump( $data, $backtrace = false, $in_admin = true, $die = false )
    {
        if ( apply_filters( 'authorship/disable_dump', false ) )
        {
            return;
        }
        if ( Request::is_from( 'ajax' ) or Request::is_from( 'api' ) or wp_is_json_request() )
        {
            return;
        }
        if ( !$in_admin and is_admin() )
        {
            return;
        }

        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
        $debug = array
        (
            'file'     => ( isset( $dbt[0]['file'] )     ? $dbt[0]['file'] : '' ),
            'line'     => ( isset( $dbt[0]['line'] )     ? $dbt[0]['line'] : '' ),
            'class'    => ( isset( $dbt[1]['class'] )    ? $dbt[1]['class'] : '' ),
            'function' => ( isset( $dbt[1]['function'] ) ? $dbt[1]['function'] : '' ),
        );

        if ( $backtrace )
        {
            $debug['filter']      = current_filter();
            $debug['is_admin']    = Request::is_from( 'admin' );
            $debug['is_front']    = Request::is_from( 'front' );
            $debug['is_ajax']     = Request::is_from( 'ajax'  );
            $debug['is_cron']     = Request::is_from( 'cron'  );
            $debug['in_the_loop'] = in_the_loop();
            $debug['backtrace']   = wp_debug_backtrace_summary( null, 0, false );
        }

        $debug['data'] = $data;
        $debug = print_r( $debug, true );
        if ( is_admin() )
        {
            if ( !current_user_can( 'administrator' ) )
            {
                return;
            }
            add_filter( '_authorship/force_inline_dump', function( $value )
            {
                if ( did_action( 'elementor/loaded' ) and \Elementor\Plugin::$instance->editor->is_edit_mode() )
                {
                    return true;
                }

                return $value;
            });

            $force_inline = apply_filters( '_authorship/force_inline_dump', false );
            if ( !did_action( 'admin_notices' ) and !$die and !$force_inline )
            {
                add_action( 'admin_notices', function() use ( $debug )
                {
                    $html_message = '';
                    if ( Helpers::is_block_editor() and !did_action( '_authorship/hide_block_editor' ) )
                    {
                        do_action( '_authorship/hide_block_editor' );

                        echo '<style>#editor{display:none} .wrap.hide-if-js.block-editor-no-js{display:block}</style>';
                        $html_message .= sprintf( '<div class="notice notice-warning" style="display: block !important; background: #dba6171f;"><h2>Hidden Block Editor</h2><pre>%s</pre></div>', esc_html__( 'The Block Editor has been hidden so debug information can be seen.', 'molongui-authorship' ) );
                    }

                    $html_message .= sprintf( '<div class="notice notice-info" style="display: block !important;"><h2>Debug Information</h2><pre>%s</pre></div>', esc_html( $debug ) );
                    echo $html_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }, 0 );
            }
            else
            {
                if ( $die )
                {
                    echo '<pre style="margin: 20px; padding: 1em; border: 2px dashed green; background: #fbfbfb;">';
                }
                else
                {
                    echo '<pre style="margin: 20px 20px 20px 180px; padding: 1em; border: 2px dashed green; background: #fbfbfb;">';
                }
                echo esc_html( $debug );
                echo "</pre>";
            }
        }
        else
        {
            if ( function_exists( 'is_user_logged_in' ) and function_exists( 'current_user_can' ) )
            {
                if ( !is_user_logged_in() or !current_user_can( 'administrator' ) )
                {
                    return;
                }
            }

            echo '<pre style="margin: 1em; padding: 1em; border: 2px dashed green; background: #fbfbfb;">';
            echo esc_html( $debug );
            echo "</pre>";
        }
        if ( $die ) die;
    }
    public static function dump_filter( $value )
    {
        self::dump( $value );
        return $value;
    }
    public static function get_debug_data( $format = 'info' )
    {
        if ( !class_exists( 'WP_Debug_Data' ) )
        {
            require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
        }

        $data = \WP_Debug_Data::debug_data();
        if ( !empty( $format ) )
        {
            $data = \WP_Debug_Data::format( $data, $format );
        }

        return $data;
    }
    public static function display_errors()
    {
        ini_set( 'display_errors', 1 );
        ini_set( 'display_startup_errors', 1 );
        error_reporting( E_ALL );
    }
    public static function show_main_query()
    {
        if ( !isset( $_GET['molongui_show_query'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            return;
        }

        global $wp_query;

        self::dump( $wp_query );
    }

} // class