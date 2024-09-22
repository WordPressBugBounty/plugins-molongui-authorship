<?php

namespace Molongui\Authorship\Common\Modules;

use Molongui\Authorship\Common\Utils\Assets;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Notice
{
    public function __construct()
    {
        add_action( 'admin_enqueue_scripts', array( $this, 'register_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
        add_action( 'wp_ajax_authorship_notice_dismiss', array( $this, 'dismiss' ) );
    }
    public function register_styles()
    {
        $file = MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/modules/notice/assets/css/styles-rtl.5221.min.css' : '/modules/notice/assets/css/styles.be16.min.css' );
        if ( file_exists( trailingslashit( WP_PLUGIN_DIR ) . $file ) )
        {
            wp_register_style( 'molongui-authorship-notice-styles', plugins_url( '/' ).$file, array(), MOLONGUI_AUTHORSHIP_VERSION, 'screen' );
        }
    }
    public function register_scripts()
    {
        $file = MOLONGUI_AUTHORSHIP_FOLDER . '/modules/notice/assets/js/scripts.8b4a.min.js';
        if ( file_exists( trailingslashit( WP_PLUGIN_DIR ) . $file ) )
        {
            wp_register_script( 'molongui-authorship-notice-scripts', plugins_url( '/' ).$file, array( 'jquery' ), MOLONGUI_AUTHORSHIP_VERSION, true );
            wp_localize_script( 'molongui-authorship-notice-scripts', 'authorship_notice_params', array
            (
                'ajax_nonce' => wp_create_nonce( 'molongui-authorship-notice-nonce' ),
            ));
        }
    }
    public static function dismiss()
    {
        check_ajax_referer( 'molongui-authorship-notice-nonce', 'nonce', true );
        $id   = sanitize_text_field( $_POST['dismissible_id'] );
        $days = sanitize_text_field( $_POST['dismissible_length'] );
        $notices = get_option( MOLONGUI_AUTHORSHIP_NOTICES );
        $notices[$id] = ( 'forever' == $days ? 'forever' : time() + absint( $days ) * DAY_IN_SECONDS );
        update_option( MOLONGUI_AUTHORSHIP_NOTICES, $notices, true );
        wp_die();
    }
    public static function display( $id, $content, $screens = array(), $dismissible = false, $type = 'error', $class = '', $load_styles = false  )
    {
        if ( $dismissible and self::is_dismissed( $id ) )
        {
            return;
        }
        if ( !empty( $screens ) )
        {
            global $current_screen;
            if ( !in_array( $current_screen->id, $screens ) )
            {
                return;
            }
        }

        $data_attr = '';

        if ( $dismissible )
        {
            $data_attr  = 'data-dismissible="'.$dismissible.'"';
            $class     .= ' is-dismissible';
            wp_enqueue_script( 'molongui-authorship-notice-scripts' );
            Assets::enqueue_sweetalert();
        }
        if ( $load_styles )
        {
            wp_enqueue_style( 'molongui-authorship-notice-styles' );
        }

        ?>
        <div id="<?php echo esc_attr( $id ); ?>" class="notice notice-<?php echo esc_attr( $type ); ?> <?php echo esc_attr( $class ) ?>" <?php echo esc_attr( $data_attr ); ?>>
            <?php echo wp_kses_post( wpautop( $content ) ); ?>
        </div>
        <?php
    }
    public static function is_dismissed( $id )
    {
        $notices = get_option( MOLONGUI_AUTHORSHIP_NOTICES );
        if ( !isset( $notices[$id] ) )
        {
            return false;
        }
        if ( 'forever' == $notices[$id] )
        {
            return true;
        }
        if ( time() >= $notices[$id] )
        {
            unset( $notices[$id] );
            update_option( MOLONGUI_AUTHORSHIP_NOTICES, $notices, true );
            return false;
        }
        else
        {
            return true;
        }
    }

} // class
