<?php
/*!
 * Database Update Functions.
 *
 * This file contains the DB_Update class, which holds all the functions required for updating the database schema
 * related to the Molongui Authorship plugin.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      1.3.0
 */

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class DB_Update
{
    public function db_update_24()
    {
        $options = get_option( 'molongui_authorship_options', array() );
        add_option( 'molongui_authorship_options_23', $options, '', false );
        delete_option( 'molongui_authorship_options_backup_19' );
        $renames = array
        (
            'author_box'                    => 'author_box_enabled',
            'box_post_types_manual'         => 'author_box_post_types',
            'box_post_types_auto'           => 'author_box_auto_display_post_types',
            'guest_authors'                 => 'guest_author_enabled',
            'post_types'                    => 'guest_author_post_types',
            'enable_multi_authors'          => 'co_authors_enabled',
            'enable_local_avatars'          => 'local_avatar_enabled',
            'enable_user_profiles'          => 'user_extra_data_enabled',
            'hide_if_no_bio'                => 'author_box_hide_if_empty_bio',
            'box_position'                  => 'author_box_position',
            'author_box_related_show'       => 'author_box_show_related_posts',
            'social_networks'               => 'social_profiles',
            'add_nofollow'                  => 'social_profiles_nofollow',
            'box_schema'                    => 'schema_markup_enabled',
            'add_html_meta'                 => 'html_meta_enabled',
            'add_opengraph_meta'            => 'opengraph_meta_enabled',
            'add_facebook_meta'             => 'facebook_meta_enabled',
            'add_twitter_meta'              => 'twitter_meta_enabled',
            'multi_author_meta'             => 'html_meta_coauthors',
            'enable_theme_compat'           => 'compatibility_mode_themes',
            'enable_plugin_compat'          => 'compatibility_mode_plugins',
            'enable_cdn_compat'             => 'element_queries_cdn_compatibility',
            'enable_cache'                  => 'object_cache_enabled',
            'authors_menu'                  => 'dashboard_authors_menu',
            'guests_menu'                   => 'dashboard_guest_authors_menu',
            'guests_menu_level'             => 'dashboard_guest_authors_menu_location',
            'posts_submenu'                 => 'dashboard_posts_submenu',
            'appearance_submenu'            => 'dashboard_appearance_submenu',
            'settings_submenu'              => 'dashboard_settings_submenu',
            'author_name_action'            => 'dashboard_author_name_action',
        );

        foreach ( $renames as $old => $new )
        {
            $options[$new] = isset( $options[$old] ) ? $options[$old] : '';
            unset( $options[$old] );
        }

        $options['co_authors_post_types'] = $options['guest_author_post_types'];

        $uninstall = 'files';
        if ( empty( $options['keep_config'] ) )
        {
            $uninstall .= ',config';
        }
        if ( empty( $options['keep_data'] ) )
        {
            $uninstall .= ',data';
        }
        $options['uninstall'] = $uninstall;
        $options['uninstall_enabled'] = !( $uninstall === 'files' );
        unset( $options['keep_config'] );
        unset( $options['keep_data'] );

        $options['default_post_author_enabled'] = false;
        $options['default_post_author']         = '';
        $options['user_roles_enabled']          = true;
        $options['hide_others_posts_enabled']   = false;
        if ( 'main' === $options['byline_multiauthor_display'] )
        {
            $options['co_authors_in_byline']        = false;
            $options['co_authors_in_byline_format'] = '';
        }
        else
        {
            $options['co_authors_in_byline']        = true;
            $options['co_authors_in_byline_format'] = $options['byline_multiauthor_display'];
        }
        unset( $options['byline_multiauthor_display'] );
        $options['co_authors_separators']      = false;
        $options['co_authors_separator']       = '';
        $options['co_authors_last_separator']  = '';

        if ( !empty( $options['byline_multiauthor_separator'] ) )
        {
            $options['co_authors_separators'] = true;
            $options['co_authors_separator']  = $options['byline_multiauthor_separator'];
        }

        if ( !empty( $options['byline_multiauthor_last_separator'] ) )
        {
            $options['co_authors_separators']     = true;
            $options['co_authors_last_separator'] = $options['byline_multiauthor_last_separator'];
        }

        unset( $options['byline_multiauthor_separator'] );
        unset( $options['byline_multiauthor_last_separator'] );

        $options['author_box_customization_enabled'] = $options['author_box_enabled'];

        $options['author_box_auto_display_override'] = false;
        if ( $options['author_box_auto_display_post_types'] === $options['author_box_post_types'] )
        {
            $options['author_box_auto_display_override'] = true;
        }
        elseif ( empty( $options['author_box_post_types'] ) )
        {
            $options['author_box_auto_display_override'] = true;
            $options['author_box_post_types'] = $options['author_box_auto_display_post_types'];
        }

        $options['author_box_hide_on_category_enabled'] = false;

        if ( isset( $options['box_layout_multiauthor'] ) )
        {
            switch ( $options['box_layout_multiauthor'] )
            {
                case 'individual':
                    $options['author_box_for_co_authors'] = '1';
                    break;

                case 'default':
                default:
                    $options['author_box_for_co_authors'] = '';
                    break;
            }
        }
        unset( $options['box_layout_multiauthor'] );

        $options['author_pages_enabled']            = true;
        $options['user_archive_template_checkbox']  = true;
        $options['guest_archive_template_checkbox'] = false;

        $options['search_results_enabled'] = false;

        $options['author_data_encode_enabled'] = false;

        $options['social_profiles_enabled'] = true;

        $options['seo_settings_enabled'] = true;

        if ( !empty( $options['enable_guests_in_api'] ) or !empty( $options['enable_authors_in_api'] ) or !empty( $options['rest_api_add_guests_authors'] ) or !empty( $options['rest_api_add_post_authors'] ) )
        {
            $options['rest_api_integration_enabled'] = true;
        }
        else
        {
            $options['rest_api_integration_enabled'] = false;
        }

        if ( !empty( $options['compatibility_mode_themes'] ) or !empty( $options['compatibility_mode_plugins'] ) or !empty( $options['object_cache_enabled'] ) )
        {
            $options['compatibility_mode_enabled'] = true;
        }
        else
        {
            $options['compatibility_mode_enabled'] = false;
        }

        $options['compatibility_mode_browser'] = false;
        $options['element_queries_enabled']    = false;

        unset( $options['assets_cdn'] );

        $options['dashboard_settings_enabled'] = true;

        if ( !isset( $options['dashboard_guest_authors_menu_location'] ) )
        {
            $options['dashboard_guest_authors_menu_location'] = 'top';
        }

        unset( $options['molongui_menu'] );

        if ( !empty( $options['hide_elements'] ) )
        {
            $options['custom_css'] .= sprintf( '%s%s%s%s'
                , "\n\n"
                , "/* MOLONGUI AUTHORSHIP - Hide undesired elements on the frontend. */"
                , "\n"
                , $options['hide_elements'] . "{ display: none; visibility: hidden; opacity: 0; height: 0; margin: 0; padding: 0 }"
            );
        }
        unset( $options['hide_elements'] );

        if ( !empty( $options['box_hook_priority'] ) and '11' != $options['box_hook_priority'] )
        {
            $options['custom_php'] .= sprintf( '%s%s%s%s'
                , "\n\n"
                , "// MOLONGUI AUTHORSHIP - Push the author box between widgets."
                , "\n"
                , "add_filter( 'molongui_authorship/author_box_hook_priority', function(){ return ".$options['box_hook_priority']."; } );"
            );
        }
        unset( $options['box_hook_priority'] );

        if ( empty( $options['enable_author_box_styles'] ) )
        {
            $options['custom_php'] .= sprintf( '%s%s%s%s'
                , "\n\n"
                , "// MOLONGUI AUTHORSHIP - Prevent default author box styles to be loaded."
                , "\n"
                , "add_filter( 'molongui_authorship/load_author_box_styles', '__return_false' );"
            );
        }
        unset( $options['enable_author_box_styles'] );
        if ( empty( $options['byline_multiauthor_link'] ) )
        {
            if ( !isset( $options['custom_php'] ) )
            {
                $options['custom_php'] = '';
            }

            $options['custom_php'] .= sprintf( '%s%s%s%s'
                , "\n\n"
                , "// MOLONGUI AUTHORSHIP - Prevent co-authors to link to their author pages."
                , "\n"
                , "add_filter( 'molongui_authorship/link_coauthor_name', '__return_false' );"
            );
        }
        unset( $options['byline_multiauthor_link'] );

        $options['author_box_name_show'] = true;

        update_option( 'molongui_authorship_options', $options, true );
    }
    public function db_update_23()
    {
        $options = get_option( 'molongui_authorship_options', array() );

        if ( !isset( $options['author_box_meta_show_email'] ) )
        {
            $options['author_box_meta_show_email'] = '0';
        }

        if ( !isset( $options['author_box_meta_show_phone'] ) )
        {
            $options['author_box_meta_show_phone'] = '0';
        }

        update_option( 'molongui_authorship_options', $options, true );
    }
    public function db_update_22()
    {
        $options = get_option( 'molongui_authorship_options', array() );

        if ( !empty( $options['author_box_avatar_link'] ) and 'custom' == $options['author_box_avatar_link'] )
        {
            $options['author_box_avatar_link'] = 'website';
        }

        if ( !empty( $options['author_box_name_link'] ) and 'custom' == $options['author_box_name_link'] )
        {
            $options['author_box_name_link'] = 'website';
        }

        update_option( 'molongui_authorship_options', $options, true );
    }
    public function db_update_21()
    {
        $options = get_option( 'molongui_authorship_options', array() );

        if ( !empty( $options['author_box_avatar_link'] ) and 1 == $options['author_box_avatar_link'] )
        {
            $options['author_box_avatar_link'] = 'archive';
        }

        if ( !empty( $options['author_box_name_link'] ) and 1 == $options['author_box_name_link'] )
        {
            $options['author_box_name_link'] = 'archive';
        }

        update_option( 'molongui_authorship_options', $options, true );
    }
    public function db_update_20()
    {
        $now = get_option( 'molongui_authorship_installation', array() );

        if ( !empty( $now ) )
        {
            $new = array
            (
                'timestamp' => isset( $now['install_date'] ) ? $now['install_date'] : '',
                'version'   => isset( $now['install_version'] ) ? $now['install_version'] : '',
            );
            update_option( 'molongui_authorship_install', $new, false );
            delete_option( 'molongui_authorship_installation' );
        }
        $options = get_option( 'molongui_authorship_options', array() );
        add_option( 'molongui_authorship_options_backup_19', $options, '', false );
        $options = $this->_options_update_20( $options );
        if ( false )
        {
            unset( $options['headline'] );
            unset( $options['show_headline'] );
            unset( $options['headline_text_size'] );
            unset( $options['box_headline_tag'] );

            unset( $options['show_avatar'] );
            unset( $options['avatar_src'] );
            unset( $options['avatar_local_fallback'] );
            unset( $options['avatar_border_style'] );
            unset( $options['avatar_border_width'] );
            unset( $options['avatar_border_color'] );
            unset( $options['avatar_style'] );

            unset( $options['show_meta'] );
            unset( $options['meta_text_size'] );
            unset( $options['meta_text_case'] );
            unset( $options['meta_text_style'] );
            unset( $options['meta_text_align'] );
            unset( $options['meta_separator'] );
            unset( $options['web'] );
            unset( $options['more_posts'] );
            unset( $options['bio'] );
            unset( $options['bio_text_size'] );
            unset( $options['bio_line_height'] );
            unset( $options['bio_text_case'] );
            unset( $options['bio_text_style'] );
            unset( $options['bio_text_align'] );
            unset( $options['bio_text_color'] );
            unset( $options['show_empty_related'] );
            unset( $options['related_orderby'] );
            unset( $options['related_order'] );
            unset( $options['related_items'] );
            unset( $options['related_post_types'] );
            unset( $options['related_text_case'] );
            unset( $options['related_text_color'] );

            unset( $options['box_width'] );
            unset( $options['box_background'] );
            unset( $options['box_margin'] );
            unset( $options['box_border'] );
            unset( $options['box_border_width'] );
            unset( $options['box_border_style'] );
            unset( $options['box_border_color'] );
            unset( $options['box_shadow'] );
            unset( $options['profile_layout'] );
            unset( $options['profile_title'] );
            unset( $options['related_title'] );
            unset( $options['tabs_position'] );
            unset( $options['tabs_background'] );
            unset( $options['tabs_color'] );
            unset( $options['tabs_active_color'] );
            unset( $options['tabs_text_color'] );
            unset( $options['tabs_border'] );
            unset( $options['tabs_border_style'] );
            unset( $options['tabs_border_width'] );
            unset( $options['tabs_border_color'] );
            unset( $options['about_the_author'] );
            unset( $options['related_posts'] );
            unset( $options['box_class'] );
        }
        update_option( 'molongui_authorship_options', $options, true );
    }
    public function _options_update_20( $options )
    {
        if ( empty( $options ) or !is_array( $options ) )
        {
            return $options;
        }
        if ( isset( $options['headline'] ) and !empty( $options['show_headline'] ) )
        {
            $options['author_box_header_title'] = $options['headline'];
        }
        $options['author_box_header_bottom_space'] = 20;
        if ( isset( $options['headline_text_size'] ) )
        {
            $options['author_box_header_font_size'] = $options['headline_text_size'];
        }
        if ( !empty( $options['headline_text_style'] ) )
        {
            foreach ( explode( ',', $options['headline_text_style'] ) as $style )
            {
                $options['author_box_header_font_weight'] = in_array( $style, array( 'normal', 'bold' ) ) ? $style : '';
                $options['author_box_header_font_style'] = in_array( $style, array( 'normal', 'italic' ) ) ? $style : '';
                $options['author_box_header_text_decoration'] = in_array( $style, array( 'underline', 'overline' ) ) ? $style : '';
            }
        }
        if ( isset( $options['headline_text_case'] ) )
        {
            $options['author_box_header_text_transform'] = $options['headline_text_case'];
        }
        if ( isset( $options['headline_text_align'] ) )
        {
            $options['author_box_header_text_align'] = $options['headline_text_align'];
        }
        if ( isset( $options['headline_text_color'] ) )
        {
            $options['author_box_header_color'] = $options['headline_text_color'];
        }
        if ( isset( $options['box_headline_tag'] ) )
        {
            $options['author_box_header_tag'] = $options['box_headline_tag'];
        }
        if ( isset( $options['show_avatar'] ) )
        {
            if ( empty( $options['show_avatar'] ) )
            {
                $options['author_box_avatar_show'] = '0';
            }
            else
            {
                $options['author_box_avatar_show'] = $options['show_avatar'];
            }
        }
        if ( isset( $options['avatar_link_to_archive'] ) )
        {
            $options['author_box_avatar_link'] = 'archive';
        }
        else
        {
            $options['author_box_avatar_link'] = 'none';
        }
        if ( isset( $options['avatar_width'] ) )
        {
            $options['author_box_avatar_width'] = $options['avatar_width'];
        }
        if ( isset( $options['avatar_height'] ) )
        {
            $options['author_box_avatar_height'] = $options['avatar_height'];
        }
        if ( isset( $options['avatar_src'] ) )
        {
            $options['author_box_avatar_source'] = $options['avatar_src'];
        }
        if ( isset( $options['avatar_local_fallback'] ) )
        {
            $options['author_box_avatar_fallback'] = $options['avatar_local_fallback'];
        }
        if ( isset( $options['avatar_default_gravatar'] ) )
        {
            $options['author_box_avatar_default_gravatar'] = $options['avatar_default_gravatar'];
        }
        if ( isset( $options['avatar_style'] ) )
        {
            switch ( $options['avatar_style'] )
            {
                case 'none'   : $options['author_box_avatar_border_radius'] =  0; break;
                case 'squared': $options['author_box_avatar_border_radius'] = 12; break;
                case 'circled': $options['author_box_avatar_border_radius'] = 50; break;
            }
        }
        if ( isset( $options['avatar_border_style'] ) )
        {
            $options['author_box_avatar_border_style'] = $options['avatar_border_style'];
        }
        if ( isset( $options['avatar_border_width'] ) )
        {
            $options['author_box_avatar_border_width'] = $options['avatar_border_width'];
        }
        if ( isset( $options['avatar_border_color'] ) )
        {
            $options['author_box_avatar_border_color'] = $options['avatar_border_color'];
        }
        if ( isset( $options['avatar_text_color'] ) )
        {
            $options['author_box_avatar_acronym_color'] = $options['avatar_text_color'];
        }
        if ( isset( $options['avatar_bg_color'] ) )
        {
            $options['author_box_avatar_acronym_background_color'] = $options['avatar_border_color'];
        }
        if ( isset( $options['name_text_size'] ) )
        {
            $options['author_box_name_font_size'] = $options['name_text_size'];
        }
        if ( isset( $options['name_text_case'] ) )
        {
            $options['author_box_name_text_transform'] = $options['name_text_case'];
        }
        if ( isset( $options['name_text_style'] ) )
        {
            foreach ( explode( ',', $options['name_text_style'] ) as $style )
            {
                $options['author_box_name_font_weight'] = in_array( $style, array( 'normal', 'bold' ) ) ? $style : '';
                $options['author_box_name_font_style'] = in_array( $style, array( 'normal', 'italic' ) ) ? $style : '';
                $options['author_box_name_text_decoration'] = in_array( $style, array( 'underline', 'overline' ) ) ? $style : '';
            }
        }
        if ( isset( $options['name_text_align'] ) )
        {
            $options['author_box_name_text_align'] = $options['name_text_align'];
        }
        if ( isset( $options['name_text_color'] ) )
        {
            $options['author_box_name_color'] = $options['name_text_color'];
        }
        if ( isset( $options['name_link_to_archive'] ) )
        {
            $options['author_box_name_link'] = 'archive';
        }
        else
        {
            $options['author_box_name_link'] = 'none';
        }
        if ( isset( $options['name_inherited_underline'] ) )
        {
            $options['author_box_name_underline'] = $options['name_inherited_underline'];
        }
        if ( isset( $options['box_author_name_tag'] ) )
        {
            $options['author_box_name_tag'] = $options['box_author_name_tag'];
        }
        if ( isset( $options['show_meta'] ) )
        {
            if ( empty( $options['show_meta'] ) )
            {
                $options['author_box_meta_show'] = '0';
            }
            else
            {
                $options['author_box_meta_show'] = $options['show_meta'];
            }
        }
        if ( isset( $options['meta_text_size'] ) )
        {
            $options['author_box_meta_font_size'] = $options['meta_text_size'];
        }
        if ( isset( $options['meta_text_case'] ) )
        {
            $options['author_box_meta_text_transform'] = $options['meta_text_case'];
        }
        if ( isset( $options['meta_text_style'] ) )
        {
            foreach ( explode( ',', $options['meta_text_style'] ) as $style )
            {
                $options['author_box_meta_font_weight'] = in_array( $style, array( 'normal', 'bold' ) ) ? $style : '';
                $options['author_box_meta_font_style'] = in_array( $style, array( 'normal', 'italic' ) ) ? $style : '';
                $options['author_box_meta_text_decoration'] = in_array( $style, array( 'underline', 'overline' ) ) ? $style : '';
            }
        }
        if ( isset( $options['meta_text_align'] ) ) $options['author_box_meta_text_align'] = $options['meta_text_align'];
        if ( isset( $options['meta_text_color'] ) ) $options['author_box_meta_color'] = $options['meta_text_color'];
        if ( isset( $options['meta_separator'] ) ) $options['author_box_meta_divider'] = $options['meta_separator'];
        if ( isset( $options['at'] ) ) $options['author_box_meta_at'] = $options['at'];
        if ( isset( $options['web'] ) ) $options['author_box_meta_web'] = $options['web'];
        if ( isset( $options['more_posts'] ) ) $options['author_box_meta_posts'] = $options['more_posts'];
        if ( isset( $options['bio'] ) ) $options['author_box_meta_bio'] = $options['bio'];
        if ( isset( $options['bio_field'] ) ) $options['author_box_bio_source'] = $options['bio_field'];
        if ( isset( $options['bio_text_size'] ) )
        {
            $options['author_box_bio_font_size'] = $options['bio_text_size'];

            if ( isset( $options['bio_line_height'] ) )
            {
                $options['author_box_bio_line_height'] = $options['bio_text_size'] * $options['bio_line_height'];
            }
        }
        if ( isset( $options['bio_text_case'] ) ) $options['author_box_bio_text_transform'] = $options['bio_text_case'];
        if ( isset( $options['bio_text_style'] ) )
        {
            foreach ( explode( ',', $options['meta_text_style'] ) as $style )
            {
                $options['author_box_bio_font_weight'] = in_array( $style, array( 'normal', 'bold' ) ) ? $style : '';
                $options['author_box_bio_font_style'] = in_array( $style, array( 'normal', 'italic' ) ) ? $style : '';
                $options['author_box_bio_text_decoration'] = in_array( $style, array( 'underline', 'overline' ) ) ? $style : '';
            }
        }
        if ( isset( $options['bio_text_align'] ) ) $options['author_box_bio_text_align'] = $options['bio_text_align'];
        if ( isset( $options['bio_text_color'] ) ) $options['author_box_bio_color'] = $options['bio_text_color'];
        if ( isset( $options['show_icons'] ) )
        {
            if ( empty( $options['show_icons'] ) ) $options['author_box_social_show'] = '0';
            else $options['author_box_social_show'] = $options['show_icons'];
        }
        if ( isset( $options['icons_style'] ) ) $options['author_box_social_style'] = $options['icons_style'];
        if ( isset( $options['icons_size'] ) ) $options['author_box_social_font_size'] = $options['icons_size'];
        if ( isset( $options['icons_color'] ) ) $options['author_box_social_color'] = $options['icons_color'];
        if ( isset( $options['social_link_target'] ) ) $options['author_box_social_target'] = '_self' === $options['social_link_target'] ? false : true;
        if ( isset( $options['show_related'] ) )
        {
            if ( empty( $options['show_related'] ) ) $options['author_box_related_show'] = '0';
            else $options['author_box_related_show'] = $options['show_related'];
        }
        if ( isset( $options['related_layout'] ) ) $options['author_box_related_layout'] = $options['related_layout'];
        if ( isset( $options['show_empty_related'] ) )
        {
            if ( empty( $options['show_empty_related'] ) ) $options['author_box_related_show_empty'] = '0';
            else $options['author_box_related_show_empty'] = $options['show_empty_related'];
        }
        if ( isset( $options['no_related_posts'] ) ) $options['author_box_related_none'] = $options['no_related_posts'];
        if ( isset( $options['related_orderby'] ) ) $options['author_box_related_orderby'] = $options['related_orderby'];
        if ( isset( $options['related_order'] ) ) $options['author_box_related_order'] = $options['related_order'];
        if ( isset( $options['related_items'] ) ) $options['author_box_related_count'] = $options['related_items'];
        if ( isset( $options['related_post_types'] ) ) $options['author_box_related_post_types'] = $options['related_post_types'];
        if ( isset( $options['related_text_size'] ) ) $options['author_box_related_font_size'] = $options['related_text_size'];
        if ( isset( $options['related_text_case'] ) ) $options['author_box_related_text_transform'] = $options['related_text_case'];
        if ( isset( $options['related_text_style'] ) )
        {
            foreach ( explode( ',', $options['related_text_style'] ) as $style )
            {
                $options['author_box_related_font_weight'] = in_array( $style, array( 'normal', 'bold' ) ) ? $style : '';
                $options['author_box_related_font_style'] = in_array( $style, array( 'normal', 'italic' ) ) ? $style : '';
                $options['author_box_related_text_decoration'] = in_array( $style, array( 'underline', 'overline' ) ) ? $style : '';
            }
        }
        if ( isset( $options['related_text_color'] ) ) $options['author_box_related_font_style'] = $options['related_text_color'];
        if ( isset( $options['author_box_width'] ) ) $options['author_box_width'] = $options['box_width'];

        if ( isset( $options['box_background'] ) ) $options['author_box_background_color'] = $options['box_background'];

        if ( isset( $options['box_margin'] ) )
        {
            $options['author_box_margin_top']    = $options['box_margin'].'px';
            $options['author_box_margin_right']  = 0;
            $options['author_box_margin_bottom'] = $options['box_margin'].'px';
            $options['author_box_margin_left']   = 0;
        }

        if ( isset( $options['box_border'] ) )
        {
            if ( !isset( $options['box_border_width'] ) ) $options['box_border_width'] = 0;

            switch ( $options['box_border'] )
            {
                case 'none':
                    $options['author_box_border_top']    = '0';
                    $options['author_box_border_right']  = '0';
                    $options['author_box_border_bottom'] = '0';
                    $options['author_box_border_left']   = '0';
                    break;

                case 'horizontals':
                    $options['author_box_border_top']    = $options['box_border_width'].'px';
                    $options['author_box_border_right']  = '0';
                    $options['author_box_border_bottom'] = $options['box_border_width'].'px';
                    $options['author_box_border_left']   = '0';
                    break;

                case 'verticals':
                    $options['author_box_border_top']    = '0';
                    $options['author_box_border_right']  = $options['box_border_width'].'px';
                    $options['author_box_border_bottom'] = '0';
                    $options['author_box_border_left']   = $options['box_border_width'].'px';
                    break;

                case 'left':
                    $options['author_box_border_top']    = '0';
                    $options['author_box_border_right']  = '0';
                    $options['author_box_border_bottom'] = '0';
                    $options['author_box_border_left']   = $options['box_border_width'].'px';
                    break;

                case 'top':
                    $options['author_box_border_top']    = $options['box_border_width'].'px';
                    $options['author_box_border_right']  = '0';
                    $options['author_box_border_bottom'] = '0';
                    $options['author_box_border_left']   = '0';
                    break;

                case 'right':
                    $options['author_box_border_top']    = '0';
                    $options['author_box_border_right']  = $options['box_border_width'].'px';
                    $options['author_box_border_bottom'] = '0';
                    $options['author_box_border_left']   = '0';
                    break;

                case 'bottom':
                    $options['author_box_border_top']    = '0';
                    $options['author_box_border_right']  = '0';
                    $options['author_box_border_bottom'] = $options['box_border_width'].'px';
                    $options['author_box_border_left']   = '0';
                    break;

                case 'all':
                default:
                    $options['author_box_border_top']    = $options['box_border_width'].'px';
                    $options['author_box_border_right']  = $options['box_border_width'].'px';
                    $options['author_box_border_bottom'] = $options['box_border_width'].'px';
                    $options['author_box_border_left']   = $options['box_border_width'].'px';
                    break;
            }
        }

        if ( isset( $options['box_border_style'] ) )
        {
            $options['author_box_border_style'] = $options['box_border_style'];
        }
        if ( isset( $options['box_border_color'] ) )
        {
            $options['author_box_border_color'] = $options['box_border_color'];
        }

        if ( isset( $options['box_shadow'] ) )
        {
            switch ( $options['box_shadow'] )
            {
                case 'left': // box-shadow: -10px 10px 10px #ababab;
                    $options['author_box_shadow_h_offset'] = '-10';
                    $options['author_box_shadow_v_offset'] = '10';
                    $options['author_box_shadow_blur']     = '10';
                    $options['author_box_shadow_spread']   = '0';
                    $options['author_box_shadow_color']    = '#ababab';
                    $options['author_box_shadow_inset']    = 0;
                    break;

                case 'right': // box-shadow: 10px 10px 10px #ababab;
                    $options['author_box_shadow_h_offset'] = '10';
                    $options['author_box_shadow_v_offset'] = '10';
                    $options['author_box_shadow_blur']     = '10';
                    $options['author_box_shadow_spread']   = '0';
                    $options['author_box_shadow_color']    = '#ababab';
                    $options['author_box_shadow_inset']    = 0;
                    break;

                default:
                case 'none': // box-shadow: none;
                    $options['author_box_shadow_h_offset'] = '';
                    $options['author_box_shadow_v_offset'] = '';
                    $options['author_box_shadow_blur']     = '';
                    $options['author_box_shadow_spread']   = '';
                    $options['author_box_shadow_color']    = '';
                    $options['author_box_shadow_inset']    = '';
                    break;
            }
        }

        if ( isset( $options['box_layout'] ) )
        {
            $options['author_box_layout'] = $options['box_layout'];

            if ( 'tabbed' === $options['box_layout'] )
            {
                if ( isset( $options['about_the_author'] ) ) $options['author_box_profile_title'] = $options['about_the_author'];
                if ( isset( $options['related_posts'] ) ) $options['author_box_related_title'] = $options['related_posts'];
            }
            else
            {
                if ( isset( $options['profile_title'] ) ) $options['author_box_profile_title'] = $options['profile_title'];
                if ( isset( $options['related_title'] ) ) $options['author_box_related_title'] = $options['related_title'];
            }
        }
        if ( isset( $options['profile_layout'] ) ) $options['author_box_profile_layout'] = $options['profile_layout'];
        if ( isset( $options['profile_valign'] ) ) $options['author_box_profile_valign'] = $options['profile_valign'];
        if ( isset( $options['bottom_background_color'] ) ) $options['author_box_bottom_background_color'] = $options['bottom_background_color'];
        if ( isset( $options['bottom_border_style'] ) ) $options['author_box_bottom_border_style'] = $options['bottom_border_style'];
        if ( isset( $options['bottom_border_width'] ) ) $options['author_box_bottom_border_width'] = $options['bottom_border_width'];
        if ( isset( $options['bottom_border_color'] ) ) $options['author_box_bottom_border_color'] = $options['bottom_border_color'];

        if ( isset( $options['tabs_position'] ) ) $options['author_box_tabs_position'] = $options['tabs_position'];
        if ( isset( $options['tabs_background'] ) ) $options['author_box_tabs_background_color'] = $options['tabs_background'];
        if ( isset( $options['tabs_color'] ) ) $options['author_box_tabs_color'] = $options['tabs_color'];
        if ( isset( $options['tabs_active_color'] ) ) $options['author_box_tabs_active_background_color'] = $options['tabs_active_color'];
        if ( isset( $options['tabs_text_color'] ) )
        {
            $options['author_box_tabs_text_color'] = $options['tabs_text_color'];
            $options['author_box_tabs_active_text_color'] = $options['tabs_text_color'].'80'; // Add 50% opacity removed from CSS rules
        }
        if ( isset( $options['tabs_border'] ) ) $options['author_box_tabs_active_border'] = $options['tabs_border'];
        if ( isset( $options['tabs_border_style'] ) ) $options['author_box_tabs_active_border_style'] = $options['tabs_border_style'];
        if ( isset( $options['tabs_border_width'] ) ) $options['author_box_tabs_active_border_width'] = $options['tabs_border_width'];
        if ( isset( $options['tabs_border_color'] ) ) $options['author_box_tabs_active_border_color'] = $options['tabs_border_color'];

        if ( isset( $options['box_class'] ) ) $options['author_box_custom_css_class'] = $options['box_class'];

        return $options;
    }
    public function db_update_19()
    {
        $main     = get_option( 'molongui_authorship_main', array() );
        $box      = get_option( 'molongui_authorship_box', array() );
        $byline   = get_option( 'molongui_authorship_byline', array() );
        $archives = get_option( 'molongui_authorship_archives', array() );
        $seo      = get_option( 'molongui_authorship_seo', array() );
        $compat   = get_option( 'molongui_authorship_compat', array() );
        $options  = array_merge( $main, $box, $byline, $archives, $seo, $compat );
        $options['author_box'] = $options['enable_author_boxes'];
        $options['guest_authors'] = $options['enable_guest_authors'];
        $options['guest_pages'] = $options['guest_archive_enabled'];
        $options['guests_menu_level'] = $options['guest_menu_item_level'];
        $options['box_hook_priority'] = $options['order'];
        $options['box_layout_multiauthor'] = $options['multiauthor_box_layout'];
        $options['box_layout'] = $options['layout'];
        $options['box_position'] = $options['position'];
        $options['box_schema'] = $options['enable_author_box_schema'];
        switch( $options['display'] )
        {
            case 'posts':
                $options['box_post_types_auto']   = 'post';
                $options['box_post_types_manual'] = '';
                break;
            case 'pages':
                $options['box_post_types_auto']   = 'page';
                $options['box_post_types_manual'] = '';
                break;
            case 'show':
                $options['box_post_types_auto']   = $options['post_types'];
                $options['box_post_types_manual'] = '';
                break;
            case 'hide':
                $options['box_post_types_auto']   = '';
                $options['box_post_types_manual'] = $options['post_types'];
                break;
        }
        $options['guests_menu']         = ( 'top' === $options['guests_menu_level'] ? false : true );
        $options['authors_menu']        = true;
        $options['molongui_menu']       = false; // Actually not necessary if set to 'false'
        $options['posts_submenu']       = true;
        $options['settings_submenu']    = true;
        $options['appearance_submenu']  = true;
        $options['assets_cdn']          = true;
        unset( $options['enable_author_boxes'] );
        unset( $options['enable_guest_authors'] );
        unset( $options['guest_archive_enabled'] );
        unset( $options['display'] );
        unset( $options['guest_menu_item_level'] );
        unset( $options['order'] );
        unset( $options['multiauthor_box_layout'] );
        unset( $options['layout'] );
        unset( $options['enable_author_box_schema'] );
        update_option( 'molongui_authorship_options', $options, true );
        delete_option( 'molongui_authorship_main' );
        delete_option( 'molongui_authorship_box' );
        delete_option( 'molongui_authorship_byline' );
        delete_option( 'molongui_authorship_archives' );
        delete_option( 'molongui_authorship_seo' );
        delete_option( 'molongui_authorship_compat' );
        if ( $options['guest_authors'] or $options['enable_multi_authors'] )
        {
            add_option( 'molongui_authorship_update_post_authors', true, '', true );
        }
    }
    public function db_update_18()
    {
        $strings = get_option( 'molongui_authorship_strings', array() );
        $box     = get_option( 'molongui_authorship_box', array() );
        foreach ( $strings as $key => $value )
        {
            $box[$key] = $value;
        }
        delete_option( 'molongui_authorship_strings' );
        update_option( 'molongui_authorship_box' , $box, true );
    }
    public function db_update_17()
    {
        $strings = get_option( 'molongui_authorship_strings', array() );
        $box     = get_option( 'molongui_authorship_box', array() );
        $box['no_related_posts'] = $strings['no_related_posts'];
        unset( $strings['no_related_posts'] );
        update_option( 'molongui_authorship_strings', $strings, true );
        update_option( 'molongui_authorship_box' , $box, true );
    }
    public function db_update_16()
    {
        $main = get_option( 'molongui_authorship_main', array() );
        $box  = get_option( 'molongui_authorship_box', array() );
        if ( !function_exists( 'get_editable_roles' ) ) require_once ABSPATH . 'wp-admin/includes/user.php';
        $ur = array();
        $user_roles = get_editable_roles();
        foreach ( $user_roles as $id => $user_role )
        {
            if ( !empty( $main['user_role_'.$id] ) ) $ur[] = $id;
            if ( isset( $main['user_role_'.$id] ) )  unset( $main['user_role_'.$id] );
        }
        $main['user_roles'] = implode ( ",", $ur );
        $sn = array();

        if ( file_exists( MOLONGUI_AUTHORSHIP_DIR . '/config/social.php' ) )
        {
            $social_networks = include MOLONGUI_AUTHORSHIP_DIR . '/config/social.php';
            foreach ( $social_networks as $id => $social_network )
            {
                if ( !empty( $box['show_'.$id] ) ) $sn[] = $id;
                if ( isset( $box['show_'.$id] ) )  unset( $box['show_'.$id] );
            }
            $main['social_networks'] = implode ( ",", $sn );
        }
        $pt = array();
        $post_types = Post::get_post_types();
        foreach ( $post_types as $post_type )
        {
            if ( !empty( $main['extend_to_'.$post_type] ) ) $pt[] = $post_type;
            if ( isset(  $main['extend_to_'.$post_type] ) ) unset( $main['extend_to_'.$post_type] );
        }
        $main['post_types'] = implode ( ",", $pt );
        $related = array();
        foreach ( $post_types as $post_type )
        {
            if ( !empty( $box['related_post_type_'.$post_type] ) ) $related[] = $post_type;
            if ( isset( $box['related_post_type_'.$post_type] ) )  unset( $box['related_post_type_'.$post_type] );
        }
        $box['related_post_types'] = implode ( ",", $related );
        $cats = array();
        foreach ( get_categories() as $category )
        {
            if ( !empty( $box['hide_on_category_'.$category->slug] ) )
            {
                $cats[] = $category->slug;
            }

            if ( isset( $box['hide_on_category_'.$category->slug] ) )
            {
                unset( $box['hide_on_category_'.$category->slug] );
            }
        }
        $box['hide_on_categories'] = implode ( ",", $cats );
        $box['related_orderby'] = $box['related_order_by'];
        unset( $box['related_order_by'] );
        $main['encode_email'] = $box['encode_email'];
        $main['encode_phone'] = $box['encode_phone'];
        unset( $box['encode_email'], $box['encode_phone'] );
        update_option( 'molongui_authorship_main', $main, true );
        update_option( 'molongui_authorship_box' , $box, true  );
    }
    public function db_update_15()
    {
        global $wpdb;

        $wpdb->query( "DELETE FROM {$wpdb->prefix}usermeta WHERE meta_key LIKE 'molongui_author_%_count';" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE '_molongui_guest_author_%_count';" );
        add_option( 'molongui_authorship_update_post_counters', true, '', true );
    }
    public function db_update_14()
    {
        global $wpdb;

        $wpdb->query( "UPDATE {$wpdb->prefix}posts SET post_type = 'guest_author' WHERE post_type = 'molongui_guestauthor';" );
        add_option( 'molongui_authorship_update_post_counters', true, '', true );
        $box_settings      = (array) get_option( 'molongui_authorship_box', array() );
        $byline_settings   = (array) get_option( 'molongui_authorship_byline', array() );
        $authors_settings  = (array) get_option( 'molongui_authorship_authors', array() );
        $archives_settings = (array) get_option( 'molongui_authorship_archives', array() );
        $advanced_settings = (array) get_option( 'molongui_authorship_advanced', array() );
        $box_settings['show_meta']               = true;
        $box_settings['avatar_src']              = 'local';
        $box_settings['avatar_local_fallback']   = 'gravatar';
        $box_settings['avatar_default_gravatar'] = ( !empty( $box_settings['avatar_default_img'] ) and $box_settings['avatar_default_img'] == 'blank' ) ? $box_settings['avatar_default_img'] : 'mp';
        $box_settings['avatar_text_color']       = !empty( $box_settings['acronym_text_color'] ) ? $box_settings['acronym_text_color'] : '#dd9933';
        $box_settings['avatar_bg_color']         = !empty( $box_settings['acronym_bg_color'] ) ? $box_settings['acronym_bg_color'] : '#000000';
        $box_settings['encode_email']            = !empty( $advanced_settings['encode_email'] ) ? true : false;
        $box_settings['encode_phone']            = !empty( $advanced_settings['encode_phone'] ) ? true : false;
        unset( $box_settings['avatar_default_img'] );
        unset( $box_settings['acronym_text_color'] );
        unset( $box_settings['acronym_bg_color'] );
        $byline = array
        (
            'byline_multiauthor_display'        => !empty( $byline_settings['byline_multiauthor_display'] ) ? $byline_settings['byline_multiauthor_display'] : 'all',
            'byline_multiauthor_separator'      => !empty( $byline_settings['byline_multiauthor_separator'] ) ? $byline_settings['byline_multiauthor_display'] : '',
            'byline_multiauthor_last_separator' => !empty( $byline_settings['byline_multiauthor_last_separator'] ) ? $byline_settings['byline_multiauthor_last_separator'] : '',
            'byline_name_link'                  => ( !empty( $byline_settings['byline_multiauthor_link'] ) and $byline_settings['byline_multiauthor_link'] == 'disabled' ) ? '' : true,
            'byline_multiauthor_link'           => ( !empty( $byline_settings['byline_multiauthor_link'] ) and $byline_settings['byline_multiauthor_link'] == 'main' ) ? '' : true,
            'byline_prefix'                     => !empty( $byline_settings['byline_modifier_before'] ) ? $byline_settings['byline_modifier_before'] : '',
            'byline_suffix'                     => !empty( $byline_settings['byline_modifier_after'] ) ? $byline_settings['byline_modifier_after'] : '',
            'enable_byline_template_tags'       => false,
        );
        unset( $box_settings['byline_automatic_integration'] );
        unset( $box_settings['byline_modifier_before'] );
        unset( $box_settings['byline_modifier_after'] );
        $archives_settings['guest_archive_title_prefix'] = '';
        $archives_settings['guest_archive_title_suffix'] = '';
        $archives_settings['user_archive_title_prefix']  = '';
        $archives_settings['user_archive_title_suffix']  = '';
        $main = array
        (
            'enable_multi_authors'    => true,
            'enable_guest_authors'    => !empty( $authors_settings['enable_guest_authors_feature'] ) ? true : false,
            'enable_author_boxes'     => true,
            'enable_local_avatars'    => true,
            'enable_user_profiles'    => true,
            'enable_search_by_author' => !empty( $authors_settings['enable_search_by_author']  ) ? true : false,
            'enable_guests_in_search' => !empty( $authors_settings['include_guests_in_search'] ) ? true : false,
            'extend_to_post' => !empty( $advanced_settings['extend_to_post'] ) ? true : false ,
            'extend_to_page' => !empty( $advanced_settings['extend_to_page'] ) ? true : false ,
            'guest_menu_item_level' => !empty( $authors_settings['guest_menu_item_level'] ) ? $authors_settings['guest_menu_item_level'] : 'top',
            'keep_config' => !empty( $advanced_settings['keep_config'] ) ? true : false,
            'keep_data'   => !empty( $advanced_settings['keep_data']   ) ? true : false,
        );
        $seo = array
        (
            'add_html_meta'            => !empty( $advanced_settings['add_opengraph_meta'] ) ? true : false, // Assume the user wants the same config than OpenGraph meta.
            'add_opengraph_meta'       => !empty( $advanced_settings['add_opengraph_meta'] ) ? true : false,
            'add_facebook_meta'        => !empty( $advanced_settings['add_facebook_meta']  ) ? true : false,
            'add_twitter_meta'         => !empty( $advanced_settings['add_facebook_meta']  ) ? true : false, // Assume the user wants the same config than Facebook meta.
            'multi_author_meta'        => 'many',
            'enable_author_box_schema' => true,
            'add_nofollow'             => !empty( $advanced_settings['add_nofollow'] ) ? true : false,
            'box_headline_tag'         => !empty( $advanced_settings['box_headline_tag']    ) ? $advanced_settings['box_headline_tag'] : 'h3',
            'box_author_name_tag'      => !empty( $advanced_settings['box_author_name_tag'] ) ? $advanced_settings['box_author_name_tag'] : 'h5',
        );
        $compat = array
        (
            'enable_theme_compat'   => true,
            'enable_plugin_compat'  => true,
            'enable_guests_in_api'  => false,
            'hide_elements'         => !empty( $advanced_settings['hide_elements'] ) ? $advanced_settings['hide_elements'] : '',
            'enable_sc_text_widget' => !empty( $advanced_settings['enable_sc_text_widgets'] ) ? true : false,
        );
        unset( $advanced_settings['encode_email'] );
        unset( $advanced_settings['encode_phone'] );
        unset( $advanced_settings['keep_config'] );
        unset( $advanced_settings['keep_data'] );
        unset( $advanced_settings['add_opengraph_meta'] );
        unset( $advanced_settings['add_facebook_meta'] );
        unset( $advanced_settings['add_google_meta'] );
        unset( $advanced_settings['add_nofollow'] );
        unset( $advanced_settings['box_headline_tag'] );
        unset( $advanced_settings['box_author_name_tag'] );
        unset( $advanced_settings['hide_elements'] );
        unset( $advanced_settings['enable_sc_text_widgets'] );
        unset( $advanced_settings['plugin_version'] );
        $main = array_merge( $main, $advanced_settings );
        update_option( 'molongui_authorship_box', $box_settings, true );
        update_option( 'molongui_authorship_byline', $byline, true );
        update_option( 'molongui_authorship_archives', $archives_settings, true );
        update_option( 'molongui_authorship_main', $main, true );
        update_option( 'molongui_authorship_seo', $seo, true );
        update_option( 'molongui_authorship_compat', $compat, true );
        delete_option( 'molongui_authorship_authors' );
        delete_option( 'molongui_authorship_advanced' );
    }
    public function db_update_13()
    {
        $transients = array
        (
            'molongui-authorship-install-notice'         => 'install-notice-dismissal',
            'molongui-authorship-whatsnew-notice'        => 'whatsnew-notice-dismissal',
            'molongui-authorship-upgrade-notice'         => 'upgrade-notice-dismissal',
            'molongui-authorship-rate-notice'            => 'rate-notice-dismissal',
            'molongui-authorship-renew-license-notice'   => 'renew-license-notice-dismissal',
            'molongui-authorship-expired-license-notice' => 'expired-license-notice-dismissal',
        );

        foreach ( $transients as $transient_name => $key )
        {
            $value = get_site_transient( $transient_name );
            if ( $value )
            {
                $notices = get_option( 'molongui_authorship_notices', array() );
                if ( !$notices ) $notices = array();
                $notices[$key] = $value;
                update_option( 'molongui_authorship_notices', $notices, true );
            }
            delete_site_transient( $transient_name );
        }
    }
    public function db_update_12()
    {
        global $wpdb;
        $guests = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->prefix}posts WHERE post_type = 'molongui_guestauthor';" );
        foreach ( $guests as $guest )
        {
            $wpdb->query( "INSERT INTO {$wpdb->prefix}postmeta (post_id, meta_key, meta_value) SELECT '{$guest->ID}', '_molongui_guest_author_display_name', '{$guest->post_title}' WHERE NOT EXISTS (SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id = '{$guest->ID}' and meta_key = '_molongui_guest_author_display_name');" );
        }
    }
    public function db_update_11()
    {
        global $wpdb;
        $guest_settings = get_option( 'molongui_authorship_guest', array() );
        delete_option( 'molongui_authorship_guest' );
        if ( did_action( 'authorship_pro/loaded' ) and $guest_settings['include_guests_in_search'] )
        {
            $guest_settings['include_guests_in_search'] = true;
        }
        else
        {
            $guest_settings['include_guests_in_search'] = false;
        }
        $author_settings = array
        (
            'enable_guest_authors_feature' => $guest_settings['enable_guest_authors_feature'],
            'enable_search_by_author'      => false,
            'include_guests_in_search'     => $guest_settings['include_guests_in_search'],
            'guest_menu_item_level'        => $guest_settings['guest_menu_item_level'],
        );
        add_option( 'molongui_authorship_authors', $author_settings, '', true );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_box_display' WHERE meta_key = '_molongui_guest_author_hide_box';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}usermeta SET meta_key = 'molongui_author_box_display' WHERE meta_key = 'molongui_author_hide_box';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_value = 'hide' WHERE meta_key = '_molongui_guest_author_box_display' AND meta_value = '1';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}usermeta SET meta_value = 'hide' WHERE meta_key = 'molongui_author_box_display' AND meta_value = '1';" );
        $box_settings = get_option( 'molongui_authorship_box', array() );
        $display = array
        (
            '1'     => 'show',
            'posts' => 'posts',
            'pages' => 'pages',
            '0'     => 'hide',
        );
        $box_settings['display'] = $display[$box_settings['display']];
        update_option( 'molongui_authorship_box', $box_settings, true );
    }
    public function db_update_10()
    {
        global $wpdb;
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_value = '1' WHERE meta_key = '_molongui_guest_author_show_mail' AND meta_value = 'yes';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_value = '1' WHERE meta_key = '_molongui_guest_author_show_phone' AND meta_value = 'yes';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_show_meta_mail' WHERE meta_key = '_molongui_guest_author_show_mail';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_show_meta_phone' WHERE meta_key = '_molongui_guest_author_show_phone';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_web' WHERE meta_key = '_molongui_guest_author_link';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}usermeta SET meta_value = '1' WHERE meta_key = 'molongui_author_show_mail' AND meta_value = 'yes';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}usermeta SET meta_value = '1' WHERE meta_key = 'molongui_author_show_phone' AND meta_value = 'yes';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}usermeta SET meta_key = 'molongui_author_show_meta_mail' WHERE meta_key = 'molongui_author_show_mail';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}usermeta SET meta_key = 'molongui_author_show_meta_phone' WHERE meta_key = 'molongui_author_show_phone';" );
        if ( is_admin() )
        {
            require_once( ABSPATH . 'wp-includes/pluggable.php' );
            $users = get_users();
            foreach ( $users as $user )
            {
                $molongui_link = get_user_meta( $user->ID, 'molongui_author_link', true );
                if ( $molongui_link and empty( $user->user_url ) ) wp_update_user( array( 'ID' => $user->ID, 'user_url' => $molongui_link ) );
                delete_user_meta( $user->ID, 'molongui_author_link' );
            }
        }
        $box_settings = get_option( 'molongui_authorship_box', array() );
        unset( $box_settings['show_mail'] );
        unset( $box_settings['show_web'] );
        update_option( 'molongui_authorship_box', $box_settings, true );
    }
    public function db_update_9()
    {
        $box_settings = get_option( 'molongui_authorship_box', array() );
        $box_settings['show_mail'] = '0';
        update_option( 'molongui_authorship_box', $box_settings, true );
    }
    public function db_update_8()
    {
        $box_settings = get_option( 'molongui_authorship_box', array() );
        if ( isset( $box_settings['box_border_style'] ) and $box_settings['box_border_style'] == 'none' )
        {
            $box_settings['box_border']       = 'none';
            $box_settings['box_border_style'] = 'solid';
        }
        elseif ( isset( $box_settings['box_border_style'] ) )
        {
            $box_settings['box_border']       = 'all';
        }
        else
        {
            $box_settings['box_border']       = 'none';
            $box_settings['box_border_style'] = 'solid';
        }
        $profile = array
        (
            'layout-1' => 'layout-1',
            'layout-2' => 'layout-2',
            'layout-3' => 'layout-1',
            'layout-4' => 'layout-7',
            'layout-5' => 'layout-8',
        );
        $box_settings['profile_layout'] = $profile[$box_settings['layout']];
        $layout = array
        (
            'layout-1' => 'slim',
            'layout-2' => 'slim',
            'layout-3' => 'tabbed',
            'layout-4' => 'slim',
            'layout-5' => 'slim',
        );
        $box_settings['layout'] = $layout[$box_settings['layout']];
        update_option( 'molongui_authorship_box', $box_settings, true );
    }
    public function db_update_7() {}
    public function db_update_6()
    {
        $main_settings     = get_option( 'molongui_authorship_main', array() );
        $box_settings      = get_option( 'molongui_authorship_box', array() );
        $advanced_settings = get_option( 'molongui_authorship_advanced', array() );
        $text_styles = array
        (
            'normal'   => 'normal',
            'bold'     => 'bold',
            'italics'  => 'italic',
            'itbo'     => 'italic,bold',
        );
        $font_sizes = array
        (
            'biggest'  => 26,
            'bigger'   => 22,
            'big'      => 18,
            'normal'   => 14,
            'small'    => 12,
            'smaller'  => 11,
            'smallest' => 10,
        );
        $border_widths = array
        (
            'none'     => 0,
            'thin'     => 1,
            'thick'    => 2,
            'thicker'  => 3,
            'thickest' => 4,
        );
        $box_settings['headline_text_style'] = $text_styles[$box_settings['headline_style']];
        $box_settings['bio_text_style']      = $text_styles[$box_settings['bio_style']];
        $box_settings['headline_text_size']  = $font_sizes[$box_settings['headline_size']];
        $box_settings['name_text_size']      = $font_sizes[$box_settings['name_size']];
        $box_settings['meta_text_size']      = $font_sizes[$box_settings['meta_size']];
        $box_settings['bio_text_size']       = $font_sizes[$box_settings['bio_size']];
        $box_settings['icons_size']          = $font_sizes[$box_settings['icons_size']];
        $box_settings['box_border_width']    = $border_widths[$box_settings['box_border']];
        $box_settings['bottom_border_width'] = $border_widths[$box_settings['bottom_border']];
        $box_settings['avatar_border_width'] = $border_widths[$box_settings['img_border']];
        $box_settings['avatar_style']        = $box_settings['img_style'];
        $box_settings['avatar_border_color'] = $box_settings['img_border_color'];
        $box_settings['avatar_default_img']  = $box_settings['img_default'];
        unset( $box_settings['headline_style'] );
        unset( $box_settings['bio_style'] );
        unset( $box_settings['headline_size'] );
        unset( $box_settings['name_size'] );
        unset( $box_settings['meta_size'] );
        unset( $box_settings['bio_size'] );
        unset( $box_settings['box_border'] );
        unset( $box_settings['bottom_border'] );
        unset( $box_settings['img_border'] );
        unset( $box_settings['img_style'] );
        unset( $box_settings['img_border_color'] );
        unset( $box_settings['img_default'] );
        $name_link = array
        (
            'link'   => 'keep',
            'nolink' => 'remove',
        );
        if ( $box_settings['name_link'] == 'link' ) $box_settings['name_text_style'] = 'underline';
        $box_settings['name_inherited_underline'] = $name_link[$box_settings['name_link']];
        unset( $box_settings['name_link'] );
        $advanced_settings['add_opengraph_meta'] = $main_settings['add_opengraph_meta'];
        $advanced_settings['add_google_meta']    = $main_settings['add_google_meta'];
        $advanced_settings['add_facebook_meta']  = $main_settings['add_facebook_meta'];
        unset ( $main_settings['add_opengraph_meta'] );
        unset ( $main_settings['add_google_meta'] );
        unset ( $main_settings['add_facebook_meta'] );
        $guest_settings = array();
        $guest_settings['include_guests_in_search'] = $main_settings['include_guests_in_search'];
        $guest_settings['guest_menu_item_level']    = ( $main_settings['admin_menu_level'] == 'true' ? 'top' : $main_settings['admin_menu_level'] );
        unset ( $main_settings['include_guests_in_search'] );
        unset ( $main_settings['admin_menu_level'] );
        $archives_settings = array();
        $archives_settings['guest_archive_enabled']   = $main_settings['enable_guest_archive'];
        $archives_settings['guest_archive_permalink'] = $main_settings['guest_archive_permalink'];
        $archives_settings['guest_archive_base']      = $main_settings['guest_archive_slug'];
        $archives_settings['guest_archive_tmpl']      = $main_settings['guest_archive_tmpl'];
        unset ( $main_settings['enable_guest_archive'] );
        unset ( $main_settings['guest_archive_permalink'] );
        unset ( $main_settings['guest_archive_slug'] );
        unset ( $main_settings['guest_archive_tmpl'] );
        $byline_settings = array();
        $byline_settings['byline_automatic_integration'] = $main_settings['byline_auto_update'];
        $byline_settings['byline_multiauthor_display']   = $main_settings['by_line'];
        $byline_settings['byline_multiauthor_link']      = $main_settings['by_line_link'];
        unset ( $main_settings['by_line'] );
        unset ( $main_settings['by_line_link'] );
        unset ( $main_settings['byline_auto_update'] );
        $box_settings['multiauthor_box_layout'] = $main_settings['multiauthor_layout'];
        unset ( $main_settings['multiauthor_layout'] );
        $box_settings['bottom_background_color'] = $box_settings['bottom_bg'];
        $box_settings['headline_text_align']     = $box_settings['headline_align'];
        $box_settings['headline_text_color']     = $box_settings['headline_color'];
        $box_settings['name_text_color']         = $box_settings['name_color'];
        $box_settings['meta_text_style']         = $box_settings['meta_style'];
        $box_settings['meta_text_color']         = $box_settings['meta_color'];
        $box_settings['bio_text_align']          = $box_settings['bio_align'];
        $box_settings['bio_text_color']          = $box_settings['bio_color'];
        unset( $box_settings['bottom_bg'] );
        unset( $box_settings['headline_align'] );
        unset( $box_settings['headline_color'] );
        unset( $box_settings['name_color'] );
        unset( $box_settings['meta_style'] );
        unset( $box_settings['meta_color'] );
        unset( $box_settings['bio_align'] );
        unset( $box_settings['bio_color'] );
        $layout = array
        (
            'default'     => 'layout-1',
            'default-rtl' => 'layout-2',
            'tabbed'      => 'layout-3',
            'ribbon'      => 'layout-4',
            'ribbon-rtl'  => 'layout-5',
        );
        $box_settings['layout'] = $layout[$box_settings['layout']];
        $box_settings = array_merge( $box_settings, $main_settings );
        update_option( 'molongui_authorship_byline', $byline_settings, true );
        update_option( 'molongui_authorship_box', $box_settings, true );
        update_option( 'molongui_authorship_guest', $guest_settings, true );
        update_option( 'molongui_authorship_archives', $archives_settings, true );
        update_option( 'molongui_authorship_advanced', $advanced_settings, true );
        delete_option( 'molongui_authorship_main' );
    }
    public function db_update_5()
    {
        $users = get_users();

        foreach ( $users as $user )
        {
            if ( $bio = get_user_meta( $user->ID, 'molongui_author_bio', true ) ) update_user_meta( $user->ID, 'description', $bio );
            delete_user_meta( $user->ID, 'molongui_author_bio' );
        }
    }
    public function db_update_4()
    {
        global $wpdb;
        $main_settings = get_option( 'molongui_authorship_main', array() );
        $advanced_settings = array(
            'extend_to_post'         => '1',
            'extend_to_page'         => '1',
            'enable_sc_text_widgets' => $main_settings['enable_sc_text_widgets'],
            'keep_config'            => $main_settings['keep_config'],
            'keep_data'              => $main_settings['keep_data'],
        );
        update_option( 'molongui_authorship_advanced', $advanced_settings, true );
        unset( $main_settings['enable_sc_text_widgets'] );
        unset( $main_settings['keep_config'] );
        unset( $main_settings['keep_data'] );
        update_option( 'molongui_authorship_main', $main_settings, true );
        $rows = $wpdb->get_results( "SELECT meta_id, post_id, meta_value FROM {$wpdb->prefix}postmeta WHERE meta_id IN (SELECT meta_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_molongui_guest_author_id' AND meta_value IS NOT NULL)" );
        foreach ( $rows as $row )
        {
            $author_value = 'guest-'.$row->meta_value;
            $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_author', meta_value = '{$author_value}' WHERE meta_id = {$row->meta_id};" );
            $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_main_author', meta_value = '{$author_value}' WHERE meta_key = '_molongui_guest_author' AND post_id = {$row->post_id};" );
        }
        $rows = $wpdb->get_results( "SELECT meta_id, post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_molongui_guest_author' AND meta_value = '0'" );
        foreach ( $rows as $row )
        {
            $author_id    = $wpdb->get_col( "SELECT post_author FROM {$wpdb->prefix}posts WHERE ID = {$row->post_id}" );
            $author_value = 'user-'.$author_id[0];
            $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_author', meta_value = '{$author_value}' WHERE meta_id = {$row->meta_id};" );
            $wpdb->query( "INSERT INTO {$wpdb->prefix}postmeta (post_id, meta_key, meta_value) VALUES ({$row->post_id}, '_molongui_main_author', '{$author_value}');" );
        }
        $wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key = '_molongui_guest_author';" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key = '_molongui_guest_author_id';" );
    }
    public function db_update_3()
    {
        global $wpdb;
        $settings = get_option( 'molongui_authorship_main', array() );
        $main_settings = array(
            'show_related'            => $settings['show_related'],
            'related_order_by'        => $settings['related_order_by'],
            'related_order'           => $settings['related_order'],
            'related_items'           => $settings['related_items'],
            'enable_guest_archive'    => $settings['enable_guest_archives'],
            'guest_archive_permalink' => $settings['guest_archive_permalink'],
            'guest_archive_slug'      => $settings['guest_archive_slug'],
            'guest_archive_tmpl'      => $settings['guest_archive_tmpl'],
            'show_facebook'           => $settings['show_fb'],
            'show_twitter'            => $settings['show_tw'],
            'show_linkedin'           => $settings['show_in'],
            'show_googleplus'         => $settings['show_gp'],
            'show_youtube'            => $settings['show_yt'],
            'show_pinterest'          => $settings['show_pi'],
            'show_tumblr'             => $settings['show_tu'],
            'show_instagram'          => $settings['show_ig'],
            'show_slideshare'         => $settings['show_ss'],
            'show_xing'               => $settings['show_xi'],
            'show_renren'             => $settings['show_re'],
            'show_vk'                 => $settings['show_vk'],
            'show_flickr'             => $settings['show_fl'],
            'show_vine'               => $settings['show_vi'],
            'show_meetup'             => $settings['show_me'],
            'show_weibo'              => $settings['show_we'],
            'show_deviantart'         => $settings['show_de'],
            'show_stubmleupon'        => $settings['show_st'],
            'show_myspace'            => $settings['show_my'],
            'show_yelp'               => $settings['show_ye'],
            'show_mixi'               => $settings['show_mi'],
            'show_soundcloud'         => $settings['show_so'],
            'show_lastfm'             => $settings['show_la'],
            'show_foursquare'         => $settings['show_fo'],
            'show_spotify'            => $settings['show_sp'],
            'show_vimeo'              => $settings['show_vm'],
            'show_dailymotion'        => $settings['show_dm'],
            'show_reddit'             => $settings['show_rd'],
            'enable_sc_text_widgets'  => $settings['enable_sc_text_widgets'],
            'add_opengraph_meta'      => $settings['add_opengraph_meta'],
            'add_google_meta'         => $settings['add_google_meta'],
            'add_facebook_meta'       => $settings['add_facebook_meta'],
            'admin_menu_level'        => $settings['admin_menu_level'],
            'keep_config'             => $settings['keep_config'],
            'keep_data'               => $settings['keep_data'],
        );
        update_option( 'molongui_authorship_main', $main_settings, true );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_googleplus' WHERE meta_key = '_molongui_guest_author_gplus';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}usermeta SET meta_key = 'molongui_author_googleplus' WHERE meta_key = 'molongui_author_gplus';" );
    }
    public function db_update_2()
    {
        global $wpdb;
        $settings = get_option( 'molongui_authorship_config', array() );
        $main_settings = array
        (
            'show_related'       => $settings['molongui_authorship_related_show'],
            'related_order_by'   => $settings['molongui_authorship_related_order_by'],
            'related_order'      => $settings['molongui_authorship_related_order'],
            'related_items'      => $settings['molongui_authorship_related_items'],
            'show_tw'            => $settings['molongui_authorship_show_social_networks_tw'],
            'show_fb'            => $settings['molongui_authorship_show_social_networks_fb'],
            'show_in'            => $settings['molongui_authorship_show_social_networks_in'],
            'show_gp'            => $settings['molongui_authorship_show_social_networks_gp'],
            'show_yt'            => $settings['molongui_authorship_show_social_networks_yt'],
            'show_pi'            => $settings['molongui_authorship_show_social_networks_pi'],
            'show_tu'            => $settings['molongui_authorship_show_social_networks_tu'],
            'show_ig'            => $settings['molongui_authorship_show_social_networks_ig'],
            'show_xi'            => $settings['molongui_authorship_show_social_networks_xi'],
            'show_re'            => $settings['molongui_authorship_show_social_networks_re'],
            'show_vk'            => $settings['molongui_authorship_show_social_networks_vk'],
            'show_fl'            => $settings['molongui_authorship_show_social_networks_fl'],
            'show_vi'            => $settings['molongui_authorship_show_social_networks_vi'],
            'show_me'            => $settings['molongui_authorship_show_social_networks_me'],
            'show_we'            => $settings['molongui_authorship_show_social_networks_we'],
            'show_de'            => $settings['molongui_authorship_show_social_networks_de'],
            'show_st'            => $settings['molongui_authorship_show_social_networks_st'],
            'show_my'            => $settings['molongui_authorship_show_social_networks_my'],
            'show_ye'            => $settings['molongui_authorship_show_social_networks_ye'],
            'show_mi'            => $settings['molongui_authorship_show_social_networks_mi'],
            'show_so'            => $settings['molongui_authorship_show_social_networks_so'],
            'show_la'            => $settings['molongui_authorship_show_social_networks_la'],
            'show_fo'            => $settings['molongui_authorship_show_social_networks_fo'],
            'show_sp'            => $settings['molongui_authorship_show_social_networks_sp'],
            'show_vm'            => $settings['molongui_authorship_show_social_networks_vm'],
            'add_opengraph_meta' => $settings['molongui_authorship_add_opengraph_meta'],
            'add_google_meta'    => $settings['molongui_authorship_add_google_meta'],
            'add_facebook_meta'  => $settings['molongui_authorship_add_facebook_meta'],
            'admin_menu_level'   => $settings['molongui_authorship_admin_menu_level'],
            'keep_config'        => $settings['molongui_authorship_keep_config'],
            'keep_data'          => $settings['molongui_authorship_keep_data'],
        );

        $box_settings = array
        (
            'display'             => $settings['molongui_authorship_display'],
            'position'            => $settings['molongui_authorship_position'],
            'hide_if_no_bio'      => $settings['molongui_authorship_hide_if_no_bio'],
            'layout'              => ( $settings['molongui_authorship_layout'] == 'layout-1' ? 'ribbon' : ( $settings['molongui_authorship_layout'] == 'layout-1-rtl' ? 'ribbon-rtl' : $settings['molongui_authorship_layout'] ) ),
            'box_shadow'          => $settings['molongui_authorship_box_shadow'],
            'box_border'          => $settings['molongui_authorship_box_border'],
            'box_border_color'    => $settings['molongui_authorship_box_border_color'],
            'box_background'      => $settings['molongui_authorship_box_background'],
            'img_style'           => $settings['molongui_authorship_img_style'],
            'img_default'         => $settings['molongui_authorship_img_default'],
            'name_size'           => $settings['molongui_authorship_name_size'],
            'name_color'          => $settings['molongui_authorship_name_color'],
            'meta_size'           => $settings['molongui_authorship_meta_size'],
            'meta_color'          => $settings['molongui_authorship_meta_color'],
            'bio_size'            => $settings['molongui_authorship_bio_size'],
            'bio_color'           => $settings['molongui_authorship_bio_color'],
            'bio_align'           => $settings['molongui_authorship_bio_align'],
            'bio_style'           => $settings['molongui_authorship_bio_style'],
            'show_icons'          => $settings['molongui_authorship_icons_show'],
            'icons_size'          => $settings['molongui_authorship_icons_size'],
            'icons_color'         => $settings['molongui_authorship_icons_color'],
            'icons_style'         => $settings['molongui_authorship_icons_style'],
            'bottom_bg'           => $settings['molongui_authorship_bottom_bg'],
            'bottom_border'       => $settings['molongui_authorship_bottom_border'],
            'bottom_border_color' => $settings['molongui_authorship_bottom_border_color'],
        );
        add_option( 'molongui_authorship_main', $main_settings, '', true );
        add_option( 'molongui_authorship_box', $box_settings, '', true );
        delete_option( 'molongui_authorship_config' );
        delete_option( 'molongui_authorship_deactivate_checkbox' );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = 'molongui_guest_author_blog' WHERE meta_key = 'molongui_guest_author_link';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_id' WHERE meta_key = 'molongui_guest_author_id';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_mail' WHERE meta_key = 'molongui_guest_author_mail';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_link' WHERE meta_key = 'molongui_guest_author_blog';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_job' WHERE meta_key = 'molongui_guest_author_job';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_company' WHERE meta_key = 'molongui_guest_author_company';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_company_link' WHERE meta_key = 'molongui_guest_author_company_link';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_twitter' WHERE meta_key = 'molongui_guest_author_twitter';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_facebook' WHERE meta_key = 'molongui_guest_author_facebook';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_linkedin' WHERE meta_key = 'molongui_guest_author_linkedin';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_gplus' WHERE meta_key = 'molongui_guest_author_gplus';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_youtube' WHERE meta_key = 'molongui_guest_author_youtube';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_pinterest' WHERE meta_key = 'molongui_guest_author_pinterest';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_tumblr' WHERE meta_key = 'molongui_guest_author_tumblr';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_instagram' WHERE meta_key = 'molongui_guest_author_instagram';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_xing' WHERE meta_key = 'molongui_guest_author_xing';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_renren' WHERE meta_key = 'molongui_guest_author_renren';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_vkg' WHERE meta_key = 'molongui_guest_author_vk';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_flickr' WHERE meta_key = 'molongui_guest_author_flickr';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_vine' WHERE meta_key = 'molongui_guest_author_vine';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_meetup' WHERE meta_key = 'molongui_guest_author_meetup';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_weibo' WHERE meta_key = 'molongui_guest_author_weibo';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_deviantart' WHERE meta_key = 'molongui_guest_author_deviantart';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_stumbleupon' WHERE meta_key = 'molongui_guest_author_stumbleupon';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_myspace' WHERE meta_key = 'molongui_guest_author_myspace';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_yelp' WHERE meta_key = 'molongui_guest_author_yelp';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_mixi' WHERE meta_key = 'molongui_guest_author_mixi';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_soundcloud' WHERE meta_key = 'molongui_guest_author_soundcloud';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_lastfm' WHERE meta_key = 'molongui_guest_author_lastfm';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_foursquare' WHERE meta_key = 'molongui_guest_author_foursquare';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_spotify' WHERE meta_key = 'molongui_guest_author_spotify';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_guest_author_vimeo' WHERE meta_key = 'molongui_guest_author_vimeo';" );
        $wpdb->query( "UPDATE {$wpdb->prefix}postmeta SET meta_key = '_molongui_author_box_display' WHERE meta_key = 'molongui_author_box_display';" );
    }
}
