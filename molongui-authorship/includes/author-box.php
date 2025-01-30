<?php
/*!
 * Utilities for handling the display and customization of the Author Box.
 *
 * This class provides a collection of utility methods designed to retrieve and render author information within the
 * author box. It includes hooks for customizing the display of author-related information and integrates with WordPress
 * content filters for automatic addition of the Author Box.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      5.0.0
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Admin\Author_Box_Editor;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Author_Box
{
    static  $javascript     = '';
    static  $stylesheet     = '';
    private $stylesheet_ltr = '/assets/css/author-box.29d2.min.css';
    private $stylesheet_rtl = '/assets/css/author-box-rtl.cdb1.min.css';
    use Singleton;
    public function __construct()
    {
        if ( Settings::is_enabled( 'author-box' ) )
        {
            add_action( 'init', array( $this, 'init' ), 10 );
        }
        else
        {
            Debug::console_log( null, "The Author Box feature is disabled in the plugin settings." );
        }
    }
    public function init()
    {
        $this->set_assets();
        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
        add_filter( 'authorship/box_script_params', array( $this, 'box_script_params' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_styles' ) );
        add_filter( "authorship/box_extra_styles", array( $this, 'extra_styles' ) );
        add_filter( '_authorship/box/styles_contents', array( $this, 'update_font_path' ) );
        if ( !is_admin() )
        {
            $priority = apply_filters( 'molongui_authorship/author_box_hook_priority', 11 );
            if ( $priority <= 10 )
            {
                remove_filter( 'the_content', 'wpautop' );
                add_filter( 'the_content', 'wpautop', $priority - 1 );
            }

            add_filter( 'the_content', array( $this, 'filter_content' ), $priority );
        }
    }
    public function set_assets()
    {
        self::$stylesheet = MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? $this->stylesheet_rtl : $this->stylesheet_ltr );
        self::$stylesheet = apply_filters( 'authorship/box/styles', self::$stylesheet );
        self::$javascript = MOLONGUI_AUTHORSHIP_FOLDER . self::$javascript;
        self::$javascript = apply_filters( 'authorship/box/script', self::$javascript );
    }
    public function register_scripts()
    {
        Assets::register_script( self::$javascript, 'box' );
    }
    public function enqueue_scripts()
    {
        Assets::enqueue_script( self::$javascript, 'box', true );
    }
    public function box_script_params()
    {
        $params = array
        (
        );
        return apply_filters( 'authorship/box/script_params', $params );
    }
    public function register_styles()
    {
        $deps = array();

        Assets::register_style( self::$stylesheet, 'box', $deps );
    }
    public static function maybe_enqueue_styles()
    {
        if ( WP::is_block_theme() )
        {
            if ( Post::has_author_box() )
            {
                self::enqueue_styles();
            }
        }
    }
    public static function enqueue_styles()
    {
        if ( apply_filters( 'molongui_authorship/load_author_box_styles', true ) )
        {
            Assets::enqueue_style( self::$stylesheet, 'box', true );
        }
    }
    public function extra_styles()
    {
        $options = Settings::get();
        $css     = '';
        $bp      = empty( $options['breakpoint'] ) ? '600' : $options['breakpoint'];
        $bp_low_limit = $bp - 1;
        $css .= ":root{ --m-a-box-bp: " . $bp . "px; --m-a-box-bp-l: " . $bp_low_limit . "px; }";
        if ( !empty( $options['compatibility_mode_browser'] ) and !empty( $options['element_queries_enabled'] ) and !empty( $options['element_queries_cdn_compatibility'] ) )
        {
            $item_spacing = '20';
            $eqcss        = '';
            $eqcss .= '.m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content .m-a-box-content-top,
                   .m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content .m-a-box-content-middle,
                   .m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content .m-a-box-content-bottom { flex-direction: row; flex-wrap: nowrap; }
                  ';
            $eqcss .= '.m-a-box-container[max-width~="'.$bp_low_limit.'px"] .m-a-box-name > :first-child { text-align: center !important; }
                   .m-a-box-container[max-width~="'.$bp_low_limit.'px"] .m-a-box-meta { text-align: center !important; }
                  ';

            $eqcss .= '.m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content.m-a-box-profile .m-a-box-data { flex: 1 0; margin-top: 0; }
                   .m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content.m-a-box-profile .m-a-box-data .m-a-box-name * { text-align: '.$options['author_box_name_text_align'].'; }
                   .m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content.m-a-box-profile .m-a-box-data .m-a-box-meta { text-align: '.$options['author_box_meta_text_align'].'; }
                  ';

            if ( is_rtl() )
            {
                $eqcss .= '.rtl .m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content.m-a-box-profile .m-a-box-avatar { flex: 0 0 auto; align-self: center; padding: 0 0 0 '.$item_spacing.'px; min-width: auto; }
                       .rtl .m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content.m-a-box-profile .m-a-box-social { display: flex; flex-direction: column; margin-top: 0; padding: 0 0 0 '.$item_spacing.'px; }
                       .rtl .m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content[data-profile-layout="layout-7"].m-a-box-profile .m-a-box-social,
                       .rtl .m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content[data-profile-layout="layout-8"].m-a-box-profile .m-a-box-social { display: flex; flex-direction: row; margin: 10px 0; padding: 0 '.$item_spacing.'px; }
                      ';
            }
            else
            {
                $eqcss .= '.m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content.m-a-box-profile .m-a-box-avatar { flex: 0 0 auto; align-self: center; padding: 0 '.$item_spacing.'px 0 0; min-width: auto; }
                       .m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content.m-a-box-profile .m-a-box-social { display: flex; flex-direction: column; margin-top: 0; padding: 0 '.$item_spacing.'px 0 0; }
                      ';
            }
            $eqcss .= '.m-a-box-container[min-width~="'.$bp.'px"] .m-a-box-content .m-a-box-social .m-a-box-social-icon { margin: 0.4em 0; }';
            $eqcss = apply_filters( 'authorship/eqcss/fallback', $eqcss, $bp, $item_spacing );

            $css .= $eqcss;
        }
        $css .= self::get_styles();
        return apply_filters( 'authorship/box/extra_styles', $css );
    }
    public static function get_styles( $options = array(), $box_id = '' )
    {
        if ( empty( $options ) )
        {
            $options = apply_filters( 'authorship/box/options', Settings::get() );
        }
        if ( !empty( $box_id ) )
        {
            $box_id = '#mab-' . trim( $box_id );
        }

        $css = '';
        $styles  = '';
        $styles .= !empty( $options['author_box_width'] ) ? 'width:'.$options['author_box_width'].'%;' : '';
        $styles .= ( isset( $options['author_box_margin_top'] ) and '' !== $options['author_box_margin_top'] ) ? 'margin-top:'.$options['author_box_margin_top'].' !important;' : '';
        $styles .= ( isset( $options['author_box_margin_right'] ) and '' !== $options['author_box_margin_right'] ) ? 'margin-right:'.$options['author_box_margin_right'].' !important;' : '';
        $styles .= ( isset( $options['author_box_margin_bottom'] ) and '' !== $options['author_box_margin_bottom'] ) ? 'margin-bottom:'.$options['author_box_margin_bottom'].' !important;' : '';
        $styles .= ( isset( $options['author_box_margin_left'] ) and '' !== $options['author_box_margin_left'] ) ? 'margin-left:'.$options['author_box_margin_left'].' !important;' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.'.m-a-box {' . $styles . '}';
        }
        $styles  = '';
        $styles .= !empty( $options['author_box_header_background_color'] ) ? 'background-color:'.$options['author_box_header_background_color'].';' : '';
        $styles .= !empty( $options['author_box_header_bottom_space'] ) ? 'margin-bottom:'.$options['author_box_header_bottom_space'].'px;' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-header {' . $styles . '}';
        }

        $styles  = '';
        $styles .= ( isset( $options['author_box_header_font_size'] ) and '' !== $options['author_box_header_font_size'] ) ? 'font-size:'.$options['author_box_header_font_size'].'px;' : '';
        $styles .= ( isset( $options['author_box_header_line_height'] ) and '' !== $options['author_box_header_line_height'] ) ? 'line-height:'.$options['author_box_header_line_height'].'px;' : '';
        $styles .= !empty( $options['author_box_header_font_weight'] ) ? 'font-weight:'.$options['author_box_header_font_weight'].';' : '';
        $styles .= !empty( $options['author_box_header_text_transform'] ) ? 'text-transform:'.$options['author_box_header_text_transform'].';' : '';
        $styles .= !empty( $options['author_box_header_font_style'] ) ? 'font-style:'.$options['author_box_header_font_style'].';' : '';
        $styles .= !empty( $options['author_box_header_text_decoration'] ) ? 'text-decoration:'.$options['author_box_header_text_decoration'].';' : '';
        $styles .= !empty( $options['author_box_header_text_align'] ) ? 'text-align:'.$options['author_box_header_text_align'].';' : '';
        $styles .= !empty( $options['author_box_header_color'] ) ? 'color:'.$options['author_box_header_color'].';' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-header > :first-child, '.$box_id.' .m-a-box-header a.m-a-box-header-url {' . $styles . '}';
        }
        if ( 'tabbed' === $options['author_box_layout'] )
        {
            $styles  = '';
            switch ( $options['author_box_tabs_position'] )
            {
                case 'top-left':
                    $styles .= '.m-a-box .m-a-box-tabs nav {-webkit-justify-content: flex-start; -ms-justify-content: flex-start; justify-content: flex-start;}';
                    break;

                case 'top-center':
                    $styles .= '.m-a-box .m-a-box-tabs nav {-webkit-justify-content: center; -ms-justify-content: center; justify-content: center;}';
                    break;

                case 'top-right':
                    $styles .= '.m-a-box .m-a-box-tabs nav {-webkit-justify-content: flex-end; -ms-justify-content: flex-end; justify-content: flex-end;}';
                    break;

                case 'top-full':
                default:
                    $styles .= '.m-a-box .m-a-box-tabs nav {-webkit-justify-content: space-between; -ms-justify-content: space-between; justify-content: space-between;}';
                    $styles .= '.m-a-box .m-a-box-tabs nav label {-webkit-flex-grow: 1; -ms-flex-grow: 1; flex-grow: 1;}';
                    break;
            }
            if ( !empty( $styles ) )
            {
                $css .= $styles;
            }

            $styles  = '';
            $styles .= !empty( $options['author_box_tabs_background_color'] ) ? 'background-color:'.$options['author_box_tabs_background_color'].';' : '';
            if ( !empty( $styles ) )
            {
                $css .= $box_id.'.m-a-box .m-a-box-tabs nav {' . $styles . '}';
            }

            $styles  = '';
            $styles .= !empty( $options['author_box_tabs_color'] ) ? 'background-color:'.$options['author_box_tabs_color'].';' : '';
            $styles .= !empty( $options['author_box_tabs_text_color'] ) ? 'color:'.$options['author_box_tabs_text_color'].';' : '';
            if ( !empty( $styles ) )
            {
                $css .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab {' . $styles . '}';
            }

            $styles  = '';
            $styles .= !empty( $options['author_box_tabs_active_background_color'] ) ? 'background-color:'.$options['author_box_tabs_active_background_color'].';' : '';
            $styles .= !empty( $options['author_box_tabs_active_text_color'] ) ? 'color:'.$options['author_box_tabs_active_text_color'].';' : '';
            if ( !empty( $styles ) )
            {
                $css .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab.m-a-box-tab-active {' . $styles . '}';
            }

            $styles  = '';
            switch( $options['author_box_tabs_active_border'] )
            {
                case 'around':
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab.m-a-box-tab-active {border:'.$options['author_box_tabs_active_border_width'].'px '.$options['author_box_tabs_active_border_style'].' '.$options['author_box_tabs_active_border_color'].'; border-bottom-style:none;}';
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab {border-width:'.$options['author_box_tabs_active_border_width'].'px; border-color:'.$options['author_box_tabs_active_border_color'].'; border-bottom-style:'.$options['author_box_tabs_active_border_style'].';}';

                    if ( 'top-full' != $options['author_box_tabs_position'] )
                    {
                        $styles .= $box_id.'.m-a-box .m-a-box-tabs nav {border-width:'.$options['author_box_tabs_active_border_width'].'px; border-color:'.$options['author_box_tabs_active_border_color'].'; border-bottom-style:'.$options['author_box_tabs_active_border_style'].';}';
                        $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab { margin-bottom: -'.$options['author_box_tabs_active_border_width'].'px; }';
                    }
                    break;

                case 'top':
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab.m-a-box-tab-active {border:'.$options['author_box_tabs_active_border_width'].'px '.$options['author_box_tabs_active_border_style'].' '.$options['author_box_tabs_active_border_color'].'; border-left-style:none; border-right-style:none; border-bottom-style:none;}';
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab {border-width:'.$options['author_box_tabs_active_border_width'].'px; border-color:'.$options['author_box_tabs_active_border_color'].'; border-bottom-style:none;}';
                    break;

                case 'topline':
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab.m-a-box-tab-active {border:'.$options['author_box_tabs_active_border_width'].'px '.$options['author_box_tabs_active_border_style'].' '.$options['author_box_tabs_active_border_color'].'; border-left-style:none; border-right-style:none; border-bottom-style:none;}';
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab {border-width:'.$options['author_box_tabs_active_border_width'].'px; border-color:'.$options['author_box_tabs_active_border_color'].'; border-bottom-style:'.$options['author_box_tabs_active_border_style'].';}';
                    break;

                case 'bottom':
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab.m-a-box-tab-active {border:'.$options['author_box_tabs_active_border_width'].'px '.$options['author_box_tabs_active_border_style'].' '.$options['author_box_tabs_active_border_color'].'; border-left-style:none; border-top-style:none; border-right-style:none;}';
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab {border-width:'.$options['author_box_tabs_active_border_width'].'px; border-color:'.$options['author_box_tabs_active_border_color'].'; border-bottom-style:none;}';
                    break;

                case 'none':
                default:
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab.m-a-box-tab-active {border:'.$options['author_box_tabs_active_border_width'].'px none '.$options['author_box_tabs_active_border_color'].';}';
                    $styles .= $box_id.'.m-a-box .m-a-box-tabs nav label.m-a-box-tab {border-width:'.$options['author_box_tabs_active_border_width'].'px; border-color:'.$options['author_box_tabs_active_border_color'].'; border-style:none;}';
                    break;
            }
            if ( !empty( $styles ) )
            {
                $css .= $styles;
            }
        }
        $styles  = '';
        $styles .= 'padding-top:' . ( empty( $options['author_box_padding_top'] ) ? '0' : $options['author_box_padding_top'] ) . ';';
        $styles .= 'padding-right:' . ( empty( $options['author_box_padding_right'] ) ? '0' : $options['author_box_padding_right'] ) . ';';
        $styles .= 'padding-bottom:' . ( empty( $options['author_box_padding_bottom'] ) ? '0' : $options['author_box_padding_bottom'] ) . ';';
        $styles .= 'padding-left:' . ( empty( $options['author_box_padding_left'] ) ? '0' : $options['author_box_padding_left'] ) . ';';
        $styles .= !empty( $options['author_box_border_style'] ) ? 'border-style:'.$options['author_box_border_style'].';' : '';
        $styles .= 'border-top-width:' . ( empty( $options['author_box_border_top'] ) ? '0' : $options['author_box_border_top'] ) . ';';
        $styles .= 'border-right-width:' . ( empty( $options['author_box_border_right'] ) ? '0' : $options['author_box_border_right'] ) . ';';
        $styles .= 'border-bottom-width:' . ( empty( $options['author_box_border_bottom'] ) ? '0' : $options['author_box_border_bottom'] ) . ';';
        $styles .= 'border-left-width:' . ( empty( $options['author_box_border_left'] ) ? '0' : $options['author_box_border_left'] ) . ';';
        $styles .= !empty( $options['author_box_border_color'] ) ? 'border-color:'.$options['author_box_border_color'].';' : ''; // '' = inherit
        $styles .= !empty( $options['author_box_border_radius'] ) ? 'border-radius:'.$options['author_box_border_radius'].'px;' : '';
        $styles .= !empty( $options['author_box_background_color'] ) ? 'background-color:'.$options['author_box_background_color'].';' : ''; // '' = inherit
        $styles .= 'box-shadow:' . ( empty( $options['author_box_shadow_h_offset'] ) ? '0' : $options['author_box_shadow_h_offset'].'px' ) . ' ' . ( empty( $options['author_box_shadow_v_offset'] ) ? '0' : $options['author_box_shadow_v_offset'].'px' ) . ' ' . ( empty( $options['author_box_shadow_blur'] ) ? '0' : $options['author_box_shadow_blur'].'px' ) . ' ' .  ( empty( $options['author_box_shadow_spread'] ) ? '0' : $options['author_box_shadow_spread'].'px' ) . ' ' . $options['author_box_shadow_color'] . ' ' . ( $options['author_box_shadow_inset'] ? 'inset' : '' ) . ';';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-container {' . $styles . '}';
        }
        $styles  = '';
        $styles .= ( !empty( $options['author_box_profile_valign'] ) and $options['author_box_profile_valign'] != 'center' ) ? 'align-self:'.$options['author_box_profile_valign'].' !important;' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-avatar {' . $styles . '}';
        }

        $styles  = '';
        $styles .= !empty( $options['author_box_avatar_border_style'] ) ? 'border-style:'.$options['author_box_avatar_border_style'].';' : '';
        $styles .=  isset( $options['author_box_avatar_border_width'] ) ? 'border-width:'.$options['author_box_avatar_border_width'].'px;' : '';
        $styles .= !empty( $options['author_box_avatar_border_color'] ) ? 'border-color:'.$options['author_box_avatar_border_color'].';' : ''; // '' = inherit
        $styles .= !empty( $options['author_box_avatar_border_radius'] ) ? 'border-radius:'.$options['author_box_avatar_border_radius'].'%;' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-avatar img, '.$box_id.' .m-a-box-avatar div[data-avatar-type="acronym"] {' . $styles . '}';
        }

        if ( 'acronym' === $options['author_box_avatar_source'] )
        {
            $styles  = '';
            $styles .= !empty( $options['author_box_avatar_background_color'] ) ? 'background-color:'.$options['author_box_avatar_background_color'].';' : ''; // '' = inherit
            $styles .= !empty( $options['author_box_avatar_color'] ) ? 'color:'.$options['author_box_avatar_color'].';' : ''; // '' = inherit
            if ( !empty( $styles ) )
            {
                $css .= $box_id.' .m-a-box-avatar [data-avatar-type="acronym"] {' . $styles . '}';
            }

            $styles  = '';
            $styles .= ( isset( $options['author_box_avatar_font_size'] ) and '' !== $options['author_box_avatar_font_size'] ) ? 'font-size:'.$options['author_box_avatar_font_size'].'px;' : '';
            $styles .= !empty( $options['author_box_avatar_font_weight'] ) ? 'font-weight:'.$options['author_box_avatar_font_weight'].';' : '';
            if ( !empty( $styles ) )
            {
                $css .= $box_id.' .m-a-box-avatar [data-avatar-type="acronym"] div {' . $styles . '}';
            }
        }
        $styles  = '';
        $styles .= ( isset( $options['author_box_name_font_size'] ) and '' !== $options['author_box_name_font_size'] ) ? 'font-size:'.$options['author_box_name_font_size'].'px;' : '';
        $styles .= ( isset( $options['author_box_name_line_height'] ) and '' !== $options['author_box_name_line_height'] ) ? 'line-height:'.$options['author_box_name_line_height'].'px;' : '';
        $styles .= !empty( $options['author_box_name_font_weight'] ) ? 'font-weight:'.$options['author_box_name_font_weight'].';' : '';
        $styles .= !empty( $options['author_box_name_text_transform'] ) ? 'text-transform:'.$options['author_box_name_text_transform'].';' : '';
        $styles .= !empty( $options['author_box_name_font_style'] ) ? 'font-style:'.$options['author_box_name_font_style'].';' : '';
        $styles .= !empty( $options['author_box_name_text_decoration'] ) ? 'text-decoration:'.$options['author_box_name_text_decoration'].' !important;' : '';
        $styles .= !empty( $options['author_box_name_text_align'] ) ? 'text-align:'.$options['author_box_name_text_align'].';' : '';
        $styles .= !empty( $options['author_box_name_color'] ) ? 'color:'.$options['author_box_name_color'].' !important;' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-name *  {' . $styles . '}';
        }

        $styles  = '';
        if ( !empty( $options['author_box_name_text_align'] ) )
        {
            $styles .= 'text-align:'.$options['author_box_name_text_align'].';';
            $styles .= 'span' === $options['author_box_name_tag'] ? 'display:block;' : '';
        }
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-container .m-a-box-content.m-a-box-profile .m-a-box-data .m-a-box-name * {' . $styles . '}';
        }
        $styles  = '';
        $styles .= !empty( $options['author_box_meta_text_align'] ) ? 'text-align:'.$options['author_box_meta_text_align'].';' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-content.m-a-box-profile .m-a-box-data .m-a-box-meta {' . $styles . '}';
        }

        $styles  = '';
        $styles .= ( isset( $options['author_box_meta_font_size'] ) and '' !== $options['author_box_meta_font_size'] ) ? 'font-size:'.$options['author_box_meta_font_size'].'px;' : '';
        $styles .= ( isset( $options['author_box_meta_line_height'] ) and '' !== $options['author_box_meta_line_height'] ) ? 'line-height:'.$options['author_box_meta_line_height'].'px;' : '';
        $styles .= !empty( $options['author_box_meta_font_weight'] ) ? 'font-weight:'.$options['author_box_meta_font_weight'].';' : '';
        $styles .= !empty( $options['author_box_meta_text_transform'] ) ? 'text-transform:'.$options['author_box_meta_text_transform'].';' : '';
        $styles .= !empty( $options['author_box_meta_font_style'] ) ? 'font-style:'.$options['author_box_meta_font_style'].';' : '';
        $styles .= !empty( $options['author_box_meta_text_decoration'] ) ? 'text-decoration:'.$options['author_box_meta_text_decoration'].';' : '';
        $styles .= !empty( $options['author_box_meta_color'] ) ? 'color:'.$options['author_box_meta_color'].';' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-content.m-a-box-profile .m-a-box-data .m-a-box-meta * {' . $styles . '}';
        }

        $styles  = '';
        $styles .= !empty( $options['author_box_meta_divider_spacing'] ) ? 'padding:0 '.$options['author_box_meta_divider_spacing'].'em;' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-meta-divider {' . $styles . '}';
        }
        $styles  = '';
        $styles .= ( isset( $options['author_box_bio_font_size'] ) and '' !== $options['author_box_bio_font_size'] ) ? 'font-size:'.$options['author_box_bio_font_size'].'px;' : '';
        $styles .=  ( isset( $options['author_box_bio_line_height'] ) and '' !== $options['author_box_bio_line_height'] ) ? 'line-height:'.$options['author_box_bio_line_height'].'px;' : '';
        $styles .= !empty( $options['author_box_bio_font_weight'] ) ? 'font-weight:'.$options['author_box_bio_font_weight'].';' : '';
        $styles .= !empty( $options['author_box_bio_text_transform'] ) ? 'text-transform:'.$options['author_box_bio_text_transform'].';' : '';
        $styles .= !empty( $options['author_box_bio_font_style'] ) ? 'font-style:'.$options['author_box_bio_font_style'].';' : '';
        $styles .= !empty( $options['author_box_bio_text_decoration'] ) ? 'text-decoration:'.$options['author_box_bio_text_decoration'].';' : '';
        $styles .= !empty( $options['author_box_bio_text_align'] ) ? 'text-align:'.$options['author_box_bio_text_align'].';' : '';
        $styles .= !empty( $options['author_box_bio_color'] ) ? 'color:'.$options['author_box_bio_color'].';' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-bio > * {' . $styles . '}';
        }
        $styles = '';
        if ( isset( $options['author_box_social_color'] ) and $options['author_box_social_color'] != 'inherit' )
        {
            switch ( $options['author_box_social_style'] )
            {
                case 'squared':
                case 'circled':
                case 'branded-squared':
                case 'branded-circled':
                    $styles .= 'background-color: ' . $options['author_box_social_color'] . ' !important; border-color: ' . $options['author_box_social_color'] .' !important; color: inherit;';
                    break;

                case 'boxed':
                    $styles .= 'background-color: inherit; border-color: ' . $options['author_box_social_color'] .' !important; color: ' . $options['author_box_social_color'] . ' !important;';
                    break;

                case 'branded':
                    $styles .= 'background-color: inherit; border-color: inherit; color: inherit;';
                    break;

                case 'branded-boxed':
                    $styles .= 'background-color: inherit; border-color: ' . $options['author_box_social_color'] .' !important; color: inherit;';
                    break;

                case 'branded-squared-reverse':
                case 'branded-circled-reverse':
                case 'default':
                default:
                    $styles .= 'background-color: inherit; border-color: inherit; color: ' . $options['author_box_social_color'] . ' !important;';
                    break;
            }
        }
        $styles .= ( isset( $options['author_box_social_font_size'] ) and '' !== $options['author_box_social_font_size'] ) ? 'font-size:'.$options['author_box_social_font_size'].'px;' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-icon-container {' . $styles . '}';
        }
        $styles  = '';
        $styles .= ( isset( $options['author_box_related_font_size'] ) and '' !== $options['author_box_related_font_size'] ) ? 'font-size:'.$options['author_box_related_font_size'].'px;' : '';
        $styles .= !empty( $options['author_box_related_font_weight'] ) ? 'font-weight:'.$options['author_box_related_font_weight'].';' : '';
        $styles .= !empty( $options['author_box_related_text_transform'] ) ? 'text-transform:'.$options['author_box_related_text_transform'].';' : '';
        $styles .= !empty( $options['author_box_related_font_style'] ) ? 'font-style:'.$options['author_box_related_font_style'].';' : '';
        $styles .= !empty( $options['author_box_related_text_decoration'] ) ? 'text-decoration:'.$options['author_box_related_text_decoration'].';' : '';
        $styles .= !empty( $options['author_box_related_text_align'] ) ? 'text-align:'.$options['author_box_related_text_align'].';' : '';
        $styles .= !empty( $options['author_box_related_color'] ) ? 'color:'.$options['author_box_related_color'].';' : '';
        if ( !empty( $styles ) )
        {
            $css .= $box_id.' .m-a-box-related-entry-title, '.$box_id.' .m-a-box-related-entry-title a {' . $styles . '}';
        }
        if ( !empty( $options['author_box_custom_css'] ) )
        {
            $css .= $options['author_box_custom_css'];
        }
        return apply_filters( 'authorship/get_box_styles', $css, $options, $box_id );
    }
    public function update_font_path( $contents )
    {
        return str_replace( "url('../font/molongui-authorship-font.", "url('".MOLONGUI_AUTHORSHIP_URL."assets/font/molongui-authorship-font.", $contents );
    }
    public function filter_content( $content )
    {
        if ( !$this->should_add() )
        {
            return $content;
        }

        $post_id = Post::get_id();
        if ( empty( $post_id ) )
        {
            return $content;
        }

        $markup = $this->get_markup();
        if ( empty( $markup ) )
        {
            return $content;
        }

        global $multipage, $page, $numpages;
        $position = get_post_meta( $post_id, '_molongui_author_box_position', true );
        if ( empty( $position ) or $position === 'default' )
        {
            $position = Settings::get( 'author_box_position', 'below' );
        }

        /*!
         * FILTER HOOK
         * Allows overriding the configured author box position.
         *
         * @param string $position Configured position to display the author box on.
         * @param int    $post_id  The post ID.
         * @since 5.0.0
         */
        $position = apply_filters( 'molongui_authorship/author_box_position', $position, $post_id );
        switch ( $position )
        {
            case "both":

                if ( !$multipage )
                {
                    $content = $markup . $content . $markup;
                }
                elseif ( $page == 1 )
                {
                    $content = $markup . $content;
                }
                elseif ( $page == $numpages )
                {
                    $content .= $markup;
                }

                break;

            case "above":

                if ( !$multipage or ( $multipage and $page == 1 ) )
                {
                    $content = $markup . $content;
                }

                break;

            case "below":
            case "default":
            default:

                if ( !$multipage or ( $multipage and $page == $numpages ) )
                {
                    $content .= $markup;
                }

                break;
        }

        Debug::console_log( array
        (
            'post_id'      => $post_id,
            'box_position' => $position,
            'multipage'    => $multipage
        ),
            'Author box automatically added to the content:' );

        return $content;
    }
    private function should_add()
    {
        $add = false;

        if ( is_main_query() )
        {
            if ( is_single() or is_page() )
            {
                if ( in_the_loop() )
                {
                    $add = true;
                }
                else
                {
                    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
                    if ( array_search( 'get_the_block_template_html', array_column( $dbt, 'function' ) ) )
                    {
                        $add = true;
                        Debug::console_log(null, "Running outside the loop by a block-based theme. Author box added to the_content." );
                    }
                    else
                    {
                        Debug::console_log( null, "Author box not displayed outside the loop by default." );
                    }
                }
            }
            elseif ( wp_doing_ajax() )
            {
                $add = true;
            }
        }
        return apply_filters( 'molongui_authorship/add_author_box', $add );
    }
    public function get_markup()
    {
        if ( !$this->should_display() )
        {
            return '';
        }

        return self::markup();
    }
    public static function markup( $profiles = null, $options = array() )
    {
        if ( empty( $options ) )
        {
            $options = Settings::get();
        }

        if ( is_null( $profiles ) )
        {
            $post_id = Post::get_id();
            if ( empty( $post_id ) )
            {
                return '';
            }

            $post_authors = Post::get_authors( $post_id );
            if ( empty( $post_authors ) )
            {
                Debug::console_log( null, sprintf( "Author box not displayed: this post (#%s) has no authors.", $post_id ) );
                return '';
            }

            foreach ( $post_authors as $post_author )
            {
                $author = new Author( $post_author->id, $post_author->type );

                if ( 'hide' === $author->get_meta( 'box_display' ) )
                {
                    Debug::console_log( null, sprintf( "Author box not displayed: user configured the plugin to hide the author box for the %s author.", $post_author->ref ) );
                    continue;
                }

                if ( !empty( Settings::get( 'author_box_hide_if_empty_bio', false ) ) and empty( $author->get_bio() ) )
                {
                    Debug::console_log( null, "Author box not displayed: user configured the plugin to hide the author box when author bio is empty." );
                    continue;
                }

                $profiles[$post_author->ref] = $author->get_data();

                if ( $options['author_box_show_related_posts'] )
                {
                    $profiles[$post_author->ref]['posts'] = $author->get_posts( array
                    (
                        'fields'         => 'ids',
                        'post__not_in'   => array( $post_id ),
                        'post_type'      => explode( ",", Settings::get( 'author_box_related_posts_post_types', 'post' ) ),
                        'post_status'    => 'publish',
                        'posts_per_page' => Settings::get( 'author_box_related_posts_count', 4 ),
                        'order'          => Settings::get( 'author_box_related_posts_order', 'DESC' ),
                        'orderby'        => Settings::get( 'author_box_related_posts_orderby', 'date' ),
                    ));
                }
            }
        }

        /*!
         * FILTER HOOK
         * Filter hook to modify the list of authors displayed in author boxes.
         *
         * Depending on the user configuration, author profiles may be displayed in a single author box
         * or multiple author boxes (one per author). This filter allows developers to customize which
         * author profiles are used for displaying in the author boxes.
         *
         * @param  array $post_authors List of authors to display in author boxes.
         * @return array               Modified list of authors for the author boxes.
         * @since  5.0.0
         */
        $profiles = apply_filters( 'molongui_authorship/author_box_profiles', $profiles );
        if ( empty( $profiles ) )
        {
            return '';
        }
        if ( count( $profiles ) > 1 )
        {
            $multiple = true;
        }
        else
        {
            $multiple = false;
        }
        if ( Settings::get( 'compatibility_mode_browser', false ) and Settings::get( 'element_queries_enabled', false ) )
        {
            Assets::enqueue_element_queries();
        }
        Author_Box::enqueue_styles();

        ob_start();

        if ( $multiple and empty( $options['author_box_for_co_authors'] ) )
        {
            include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/html-multiauthor-layout.php';
        }
        elseif ( $multiple )
        {
            foreach ( $profiles as $profile )
            {
                include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/html-layout.php';
            }
        }
        else
        {
            $profile = reset( $profiles );
            include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/html-layout.php';
        }

        /*!
         * FILTER HOOK
         * Allows modifying the author box markup before it is returned.
         *
         * @param  string $markup The author box markup.
         * @return array          Modified author box markup.
         * @since  5.0.0
         */
        return apply_filters( 'molongui_authorship/author_box_markup', ob_get_clean(), $profiles );
    }
    private function should_display()
    {
        $display   = true;
        $post      = Post::get();
        $post_type = Post::retrieve_post_type( $post );
        if ( !in_array( $post_type, Settings::get_post_types_with_author_box() ) )
        {
            $display = false;
            Debug::console_log( null, sprintf(
                "Author box not displayed: current post type (%s) doesn't support the author box feature.",
                $post_type
            ));
        }
        elseif ( 'hide' === get_post_meta( $post->ID, '_molongui_author_box_display', true ) )
        {
            $display = false;
            Debug::console_log( null, sprintf(
                "Author box not displayed: current post (#%s) is configured to not display the author box.",
                $post->ID
            ));
        }
        elseif ( 'default' === get_post_meta( $post->ID, '_molongui_author_box_display', true )  )
        {
            if ( in_array( $post_type, Settings::get_post_types_with_author_box( 'manual' ) ) )
            {
                if ( !Settings::get( 'author_box_auto_display_override', true )
                     and !in_array( $post_type, Settings::get_post_types_with_author_box( 'auto' ) ) )
                {
                    $display = false;
                    Debug::console_log( null, sprintf(
                        "Author box not displayed: current post type (%s) is configured to display the author box only if the post (#%s) has the display setting set to 'Show'.",
                        $post_type,
                        $post->ID
                    ));
                }
            }
        }
        return apply_filters( 'molongui_authorship/display_author_box', $display );
    }
    public function render()
    {
        echo wp_kses_post( $this->get_markup() );
    }
    public function maybe_add_to_the_content()
    {
        /*!
         * FILTER HOOK
         * Allows preventing the author box to be added to the post content.
         *
         * Returning a FALSE value will effectively short-circuit the function, preventing the author box to be added to
         * the post content.
         *
         * @param bool $add Whether the author box should be added to the post content. Defaults to true.
         * @since 5.0.0
         */
        if ( apply_filters( 'molongui_authorship/add_author_box_to_content', true ) )
        {
            if ( self::hide() )
            {
                return;
            }

            $priority = apply_filters( 'molongui_authorship/author_box_hook_priority', 11 );
            if ( $priority <= 10 )
            {
                remove_filter( 'the_content', 'wpautop' );
                add_filter( 'the_content', 'wpautop', $priority - 1 );
            }
            add_filter( 'the_content', array( $this, 'add_to_the_content' ), $priority );
        }
    }
    public function add_to_the_content( $the_content )
    {
        $post_id = Post::get_id();
        if ( empty( $post_id ) )
        {
            return $the_content;
        }
        if ( !in_the_loop() )
        {
            $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
            if ( array_search( 'get_the_block_template_html', array_column( $dbt, 'function' ) ) )
            {
                Debug::console_log(null, "Running outside the loop by a block-based theme. Author box added to the_content." );
            }
            else
            {
                /*!
                 * FILTER HOOK
                 * Allows adding the author box to the post content when running outside the loop.
                 *
                 * @param bool  False by default.
                 * @since 5.0.4
                 */
                if ( apply_filters( 'molongui_authorship/add_author_box_to_content_outside_the_loop', false ) )
                {
                    Debug::console_log(null, "Running outside the loop. Author box forced to be added by the provided filter." );
                }
                else
                {
                    Debug::console_log(null, "Author box not added to the_content: Running outside the loop." );
                    return $the_content;
                }
            }
        }

        global $multipage, $page, $numpages;
        $position = get_post_meta( $post_id, '_molongui_author_box_position', true );
        if ( empty( $position ) or $position === 'default' )
        {
            $position = Settings::get( 'author_box_position', 'below' );
        }

        /*!
         * FILTER HOOK
         * Allows overriding the configured author box position.
         *
         * @param string $position Configured position to display the author box on.
         * @param int    $post_id  The post ID.
         * @since 5.0.0
         */
        $position = apply_filters( 'molongui_authorship/author_box_position', $position, $post_id );
        switch ( $position )
        {
            case "both":

                if ( !$multipage )
                {
                    $markup      = self::markup();
                    $the_content = $markup . $the_content . $markup;
                }
                elseif ( $page == 1 )
                {
                    $the_content = self::markup() . $the_content;
                }
                elseif ( $page == $numpages )
                {
                    $the_content .= self::markup();
                }
                break;

            case "above":

                if ( !$multipage or ( $multipage and $page == 1 ) )
                {
                    $the_content = self::markup() . $the_content;
                }
                break;

            case "below":
            case "default":
            default:

                if ( !$multipage or ( $multipage and $page == $numpages ) )
                {
                    $the_content .= self::markup();
                }
                break;
        }

        Debug::console_log( array( 'post_id' => $post_id, 'box_position' => $position, 'multipage' => $multipage ), 'Author box automatically added to the content:' );

        return $the_content;
    }
    public function prevent_autoadd()
    {
        $autoadd = false;
        if ( is_single() or is_page() )
        {
            if ( is_main_query() )
            {
                $autoadd = true;
            }
            else
            {
                Debug::console_log( null, "Author box not displayed: Not the main query." );
            }
        }
        elseif ( wp_doing_ajax() and is_main_query() )
        {
            $autoadd = true;
        }
        else
        {
            Debug::console_log( null, "Author box not displayed: This is not a post, a page or an AJAX request." );
        }

        return $autoadd;
    }
    public static function hide()
    {
        $hide      = false;
        $post      = Post::get();
        $post_type = Post::retrieve_post_type( $post );
        if ( !in_array( $post_type, Settings::get_post_types_with_author_box() ) )
        {
            $hide = true;
            Debug::console_log( null, sprintf( "Author box not displayed: current post type (%s) doesn't support the author box feature.", Post::retrieve_post_type( $post ) ) );
        }
        elseif ( 'hide' === get_post_meta( $post->ID, '_molongui_author_box_display', true ) )
        {
            $hide = true;
            Debug::console_log( null, sprintf( "Author box not displayed: current post (#%s) is configured to not display the author box.", $post->ID ) );
        }
        elseif ( 'default' === get_post_meta( $post->ID, '_molongui_author_box_display', true )  )
        {
            if ( in_array( $post_type, Settings::get_post_types_with_author_box( 'manual' ) ) )
            {
                if ( !Settings::get( 'author_box_auto_display_override', true )
                    and !in_array( $post_type, Settings::get_post_types_with_author_box( 'auto' ) ) )
                {
                    $hide = true;
                    Debug::console_log( null, sprintf( "Author box not displayed: current post type (%s) is configured to display the author box only if the post (#%s) has the display setting set to 'Show'.", Post::retrieve_post_type( $post ), $post->ID ) );
                }
            }
        }
        return apply_filters( 'molongui_authorship/hide_author_box', $hide );
    }
    public static function maybe_render()
    {
        if ( !self::hide() )
        {
            self::instance()->render();
        }
    }

} // class
Author_Box::instance();
