<?php

namespace Molongui\Authorship\Common\Utils;

use Molongui\Authorship\Common\Modules\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Plugin
{
    public static function restart( $plugin )
    {
        deactivate_plugins( $plugin, false, false );
        $r = activate_plugins( $plugin );

        return $r;
    }
    public static function enabled_post_screens( $plugin_id, $type = 'all' )
    {
        $screens = self::enabled_post_types( $plugin_id, $type );
        foreach ( $screens as $screen )
        {
            $screens[] = 'edit-'.$screen;
        }
        return $screens;
    }
    public static function enabled_post_types( $type = 'all', $select = false )
    {
        $post_types = $options = array();
        $settings = Settings::get();
        if ( !isset( $settings['post_types'] ) )
        {
            return ( $select ? $options : $post_types );
        }
        foreach ( Post::get_post_types( $type, 'objects', false ) as $post_type_name => $post_type_object )
        {
            if ( in_array( $post_type_name, explode( ",", $settings['post_types'] ) ) )
            {
                $post_types[] = $post_type_name;
                $options[]    = array( 'id' => $post_type_name, 'label' => $post_type_object->labels->name, 'singular' => $post_type_object->labels->singular_name );
            }
        }
        return ( $select ? $options : $post_types );
    }
    public static function is_post_type_enabled( $post_type = null, $post_types = null )
    {
        if ( !$post_type  )
        {
            if ( is_admin() )
            {
                $post_type = Post::get_post_type();
            }
            else
            {
                $post_type = get_post_type();
            }
        }

        if ( !$post_types )
        {
            $post_types = self::enabled_post_types();
        }

        return (bool) in_array( $post_type, $post_types );
    }
    public static function custom_admin_footer( $footer_text )
    {
        global $current_screen;
        if ( $current_screen->id == 'molongui_page_' . MOLONGUI_AUTHORSHIP_NAME )
        {
            /*! // translators: %1$s: Plugin name. %2$s: Opening a tag. %3$s: Closing a tag. */
            return ( sprintf( esc_html__( "If you like <strong>%s</strong> please leave us a %s&#9733;&#9733;&#9733;&#9733;&#9733;%s rating. A huge thank you in advance!", 'molongui-authorship' ),
                MOLONGUI_AUTHORSHIP_TITLE,
                '<a href="https://wordpress.org/support/view/plugin-reviews/' . MOLONGUI_AUTHORSHIP_NAME . '?filter=5#postform" target="_blank" class="molongui-admin-footer-link" data-rated="' . esc_attr__( "Thanks :)", 'molongui-authorship' ) . '">',
                '</a>' )
            );
        }

        return $footer_text;
    }
    public static function has_pro()
    {
        return did_action( 'authorship_pro/init' );
    }
    public static function add_go_pro_link( $links )
    {
        $more_links = array
        (
            'settings' => '<a href="' . admin_url( 'admin.php?page=' . MOLONGUI_AUTHORSHIP_NAME ) . '">' . __( "Settings" ) . '</a>',
            'docs'     => '<a href="' . 'https://www.molongui.com/help/docs/' . '" target="blank" >' . __( "Docs", 'molongui-authorship' ) . '</a>'
        );

        if ( apply_filters( 'authorship/action_links/go_pro', true ) )
        {
            $more_links['gopro'] = '<a href="' . MOLONGUI_AUTHORSHIP_WEB . '/" target="blank" style="font-weight:bold;color:orange">' . __( "Go Pro", 'molongui-authorship' ) . '</a>';
        }

        return array_merge( $more_links, $links );
    }
    public static function get_molonguis( $field = 'all' )
    {
        if ( !function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugins = get_plugins();
        if ( version_compare( PHP_VERSION, '5.6.0', '<' ) )
        {
            foreach ( $plugins as $plugin_file => $plugin )
            {
                if ( $plugin['Author'] == 'Molongui' )
                {
                    $molongui_plugins[$plugin_file] = $plugin;
                    $molongui_plugins[$plugin_file]['id'] = self::get_molongui_id_from_filepath( $plugin_file );
                }
            }
        }
        else
        {
            $molongui_plugins = array_filter( $plugins, function( $value, $key )
            {
                return ( $value['Author'] == 'Molongui' );
            }, ARRAY_FILTER_USE_BOTH);
        }
        if ( $field != 'all' )
        {
            if ( $field == 'keys' ) return array_keys( $molongui_plugins );

            $data = array();
            foreach ( $molongui_plugins as $plugin_file => $plugin )
            {
                $data[$plugin_file] = $plugin[$field];
            }
            $molongui_plugins = $data;
        }
        return $molongui_plugins;
    }
    public static function get_molongui_id_from_filepath( $filepath )
    {
        if ( !isset( $filepath ) ) return false;
        $plugin_id = explode( '/', $filepath );
        $plugin_id = strtolower( strtr( $plugin_id[0], array( 'molongui-' => '', ' ' => '_', '-' => '_' ) ) );
        if ( $plugin_id == "bump_offer" ) $plugin_id = "order_bump";
        return $plugin_id;
    }

} // class