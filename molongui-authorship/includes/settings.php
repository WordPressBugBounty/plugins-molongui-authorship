<?php
/*!
 * This file contains the definition for the Settings class, which extends the core plugin settings functionalityand
 *  allows for additional handling and customization of the Molongui Authorship plugin options.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      4.8.6
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Admin\Admin_Post;
use Molongui\Authorship\Admin\Author_Box_Editor;
use Molongui\Authorship\Common\Modules\Settings\Control;
use Molongui\Authorship\Common\Modules\Settings\UI;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Settings extends \Molongui\Authorship\Common\Modules\Settings
{
    private $stylesheet = '/assets/js/options.xxxx.min.css';
    private $javascript = 'assets/js/options.cb9c.min.js';
    private $screen_id;
    use Singleton;
    public function __construct()
    {
        parent::__construct();

        $this->screen_id = ( !Settings::get( 'dashboard_settings_enabled', true ) or Settings::get( 'dashboard_authors_menu', true ) )
            ? 'authors_page_molongui-authorship'
            : 'admin_page_molongui-authorship';

        add_action( 'after_setup_theme', array( $this, 'add_image_sizes' ) );
        add_action( 'init', array( $this, 'init' ), 10 );
    }
    public function get_screen_id()
    {
        return $this->screen_id;
    }
    public function init()
    {
        add_filter( 'authorship/common_settings_section_id', array( $this, 'define_common_settings_section_id' ) );
        add_filter( 'authorship/common_settings_section_name', array( $this, 'define_common_settings_section_name' ) );
        add_filter( 'authorship/default_options', array( __CLASS__, 'set_defaults' ) );
        add_action( 'authorship/options', 'authorship_add_defaults' );
        add_filter( 'authorship/plugin_settings', array( $this, 'define_plugin_settings' ) );
        add_filter( 'authorship/validate_options', array( $this, 'validate_freemium' ), 10, 2 );
        add_filter( 'authorship/validate_options', array( $this, 'validate' ), 10, 2 );
        add_filter( 'authorship/cache', array( $this, 'object_cache_status' ) );
        add_filter( 'authorship/options/styles', array( $this, 'stylesheet' ) );
        add_filter( 'authorship/options/script', array( $this, 'javascript' ) );
        add_filter( "authorship/options/script_deps", array( $this, 'require_dependencies' ) );
        add_filter( 'authorship/options/enqueue_required_deps', array( $this, 'enqueue_dependencies' ) );
        add_filter( 'authorship/options/script_params', array( $this, 'javascript_params' ) );
        add_action( 'wp_ajax_social_profiles_ajax_suggest', array( $this, 'suggest_social_profiles' ) );
            add_action( "admin_print_footer_scripts-" . $this->screen_id, array( $this, 'author_selector_params' ) );
        add_action( 'wp_ajax_authorship_clear_cache_action', array( $this, 'clear_object_cache' ) );
        add_filter( 'authorship/export_options', array( $this, 'filter_export' ) );
        add_filter( 'authorship/validate_options', array( $this, 'keep_db_19_keys' ), 20, 2 );
        add_filter( 'authorship/validate_editor_options', array( $this, 'keep_db_19_keys' ), 20, 2 );
    }

public function define_common_settings_section_id()
{
    return 'general';

}
public function define_common_settings_section_name()
{
    return __( "General", 'molongui-common-framework' );
}
    public static function set_defaults( $fw_options )
    {
        return array_merge( $fw_options, array
        (
            'author_box_enabled'      => true,
            'author_box_post_types'   => 'post', // Data stored as a string with comma-separated items. No array!
            'guest_author_enabled'    => true,
            'guest_author_post_types' => 'post', // Data stored as a string with comma-separated items. No array!
            'co_authors_enabled'      => true,
            'co_authors_post_types'   => 'post', // Data stored as a string with comma-separated items. No array!
            'local_avatar_enabled'    => true,
            'user_extra_data_enabled' => true,

            'default_post_author_enabled' => false,
            'default_post_author'         => '',    // Data stored as a string with comma-separated items. No array!

            'user_roles_enabled'          => true,
            'user_roles'                  => "administrator,editor,author,contributor", // Data stored as a string with comma-separated items. No array!

            'contributors_writing_ad'     => false,

            'post_as_others'              => false,
            'hide_others_posts_enabled'   => false,
            'hide_others_posts'           => '',
            'byline_customization_enabled' => true,
            'co_authors_in_byline'         => true,
            'co_authors_in_byline_format'  => 'all',
            'co_authors_separators'        => false,
            'co_authors_separator'         => ',',
            'co_authors_last_separator'    => 'and',
            'byline_name_link'             => true,
            'byline_custom_prefix_suffix'  => false,
            'byline_prefix'                => '',
            'byline_suffix'                => '',
            'contributors_reading_ad'      => false,
            'author_box_customization_enabled'    => true,
            'author_box_position'                 => 'below',
            'author_box_auto_display_override'    => true,
            'author_box_auto_display_post_types'  => '',      // Data stored as a string with comma-separated items. No array!
            'author_box_hide_if_empty_bio'        => false,
            'author_box_hide_on_category_enabled' => false,
            'author_box_hide_on_category'         => '',
            'author_box_for_co_authors'           => true,
            'author_box_show_related_posts'       => true,
            'author_pages_enabled'             => true,
            'user_archive_enabled'             => true,
            'user_archive_include_pages'       => false,
            'user_archive_include_cpts'        => false,
            'user_archive_base'                => 'author',
            'user_archive_slug'                => false,
            'user_archive_noindex'             => false,
            'user_archive_template_checkbox'   => false,
            'user_archive_template'            => '',
            'user_archive_title_prefix'        => '',
            'user_archive_title_suffix'        => '',
            'guest_archive_include_pages'      => false,
            'guest_archive_include_cpts'       => false,
            'guest_archive_permalink_checkbox' => '',
            'guest_archive_permalink'          => '',
            'guest_archive_base'               => 'author',
            'guest_archive_noindex'            => false,
            'guest_archive_template_checkbox'  => false,
            'guest_archive_template'           => '',
            'guest_archive_title_prefix'       => '',
            'guest_archive_title_suffix'       => '',
            'search_results_enabled'   => false,
            'search_by_author_enabled' => false,
            'guests_in_search_enabled' => false,
            'author_data_encode_enabled' => false,
            'author_email_encoded'       => false,
            'author_phone_encoded'       => false,
            'social_profiles_enabled'  => true,
            'social_profiles'          => "facebook,youtube,whatsapp,instagram,tiktok,twitter,linkedin,soundcloud,spotify,skype,medium", // Data stored as a string with comma-separated items. No array!
            'social_profiles_nofollow' => false,
            'seo_settings_enabled'   => true,
            'schema_markup_enabled'  => true,
            'html_meta_enabled'      => true,
            'opengraph_meta_enabled' => true,
            'facebook_meta_enabled'  => true,
            'twitter_meta_enabled'   => true,
            'html_meta_coauthors'    => 'many',
            'rest_api_integration_enabled' => false,
            'rest_api_add_guests_authors'  => false,
            'rest_api_add_post_authors'    => false,
            'compatibility_mode_enabled'        => true,
            'compatibility_mode_themes'         => true,
            'compatibility_mode_plugins'        => true,
            'pll_translate_guests'              => true,
            'compatibility_mode_browser'        => false,
            'element_queries_enabled'           => false,
            'element_queries_cdn_compatibility' => false,
            'object_cache_enabled'              => true,
            'dashboard_settings_enabled'            => true,
            'dashboard_authors_menu'                => true,
            'dashboard_guest_authors_menu'          => false,
            'dashboard_authors_menu_position'       => 5,
            'dashboard_authors_menu_icon'           => 'ellipsis',
            'dashboard_guest_authors_menu_location' => 'top',
            'dashboard_posts_submenu'               => true,
            'dashboard_appearance_submenu'          => true,
            'dashboard_settings_submenu'            => true,
            'dashboard_author_name_action'          => 'filter',
            'author_box_header_title'            => '', // none. Author box header disabled.
            'author_box_header_url'              => '', // none
            'author_box_header_bottom_space'     => 20,
            'author_box_header_font_size'        => '', // inherit
            'author_box_header_line_height'      => '', // inherit
            'author_box_header_font_weight'      => '', // default = inherit
            'author_box_header_text_transform'   => '', // default = inherit
            'author_box_header_font_style'       => '', // default = inherit
            'author_box_header_text_decoration'  => '', // default = inherit
            'author_box_header_text_align'       => '', // default = inherit
            'author_box_header_color'            => '', // inherit
            'author_box_header_tag'              => 'h3',
            'author_box_avatar_show'                     => 1, // show
            'author_box_avatar_link'                     => true,
            'author_box_avatar_source'                   => 'local',
            'author_box_avatar_fallback'                 => 'gravatar',
            'author_box_avatar_default_gravatar'         => 'mp',
            'author_box_avatar_width'                    => 150,
            'author_box_avatar_height'                   => 150,
            'author_box_avatar_border_style'             => 'solid',
            'author_box_avatar_border_width'             => '2',
            'author_box_avatar_border_color'             => '#bfbfbf',
            'author_box_avatar_border_radius'            => '', // inherit
            'author_box_avatar_acronym_color'            => '#dd9933', // goldish
            'author_box_avatar_acronym_background_color' => '#1d2327', // dark gray
            'author_box_name_show'            => true,
            'author_box_name_font_size'       => 22,
            'author_box_name_line_height'     => '', // inherit
            'author_box_name_font_weight'     => '', // default = inherit
            'author_box_name_text_transform'  => '', // default = inherit
            'author_box_name_font_style'      => '', // default = inherit
            'author_box_name_text_decoration' => '', // default = inherit
            'author_box_name_text_align'      => '', // default = inherit
            'author_box_name_color'           => '', // inherit
            'author_box_name_link'            => 'archive',
            'author_box_name_underline'       => 'keep',
            'author_box_name_tag'             => 'h5',
            'author_box_meta_show'            => true,
            'author_box_meta_show_email'      => '0',
            'author_box_meta_show_phone'      => '0',
            'author_box_meta_font_size'       => 12,
            'author_box_meta_line_height'     => '', // inherit
            'author_box_meta_font_weight'     => '', // default = inherit
            'author_box_meta_text_transform'  => '', // default = inherit
            'author_box_meta_font_style'      => '', // default = inherit
            'author_box_meta_text_decoration' => '', // default = inherit
            'author_box_meta_text_align'      => '', // default = inherit
            'author_box_meta_color'           => '', // inherit
            'author_box_meta_divider'         => '|',
            'author_box_meta_divider_spacing' => '0.2',
            'author_box_meta_at'              => ( doing_action( 'init' ) or did_action( 'init' ) ) ? __( "at", 'molongui-authorship' ) : "at",
            'author_box_meta_web'             => ( doing_action( 'init' ) or did_action( 'init' ) ) ? __( "Website", 'molongui-authorship' ) : "Website",
            'author_box_meta_posts'           => ( doing_action( 'init' ) or did_action( 'init' ) ) ? __( "+ posts", 'molongui-authorship' ) : "+ posts",
            'author_box_meta_bio'             => ( doing_action( 'init' ) or did_action( 'init' ) ) ? __( "Bio ⮌", 'molongui-authorship' ) : "Bio",
            'author_box_bio_source'          => 'full',
            'author_box_bio_font_size'       => 14,
            'author_box_bio_line_height'     => '', // inherit
            'author_box_bio_font_weight'     => '', // default = inherit
            'author_box_bio_text_transform'  => '', // default = inherit
            'author_box_bio_font_style'      => '', // default = inherit
            'author_box_bio_text_decoration' => '', // default = inherit
            'author_box_bio_text_align'      => '', // default = inherit
            'author_box_bio_color'           => '', // inherit
            'author_box_social_show'      => true,
            'author_box_social_style'     => 'default',
            'author_box_social_font_size' => 20,
            'author_box_social_color'     => '#999999',
            'author_box_social_target'    => '_blank',
            'author_box_related_layout'          => 'layout-1',
            'author_box_related_show_empty'      => true,
            'author_box_related_none'            => ( doing_action( 'init' ) or did_action( 'init' ) ) ? __( "This author does not have any more posts", 'molongui-authorship' ) : "This author does not have any more posts",
            'author_box_related_orderby'         => 'date',
            'author_box_related_order'           => 'DESC',
            'author_box_related_count'           => 4,
            'author_box_related_post_types'      => "post", // Data stored as a string with comma-separated items. No array!
            'author_box_related_font_size'       => 14,
            'author_box_related_line_height'     => '', // inherit
            'author_box_related_font_weight'     => '', // default = inherit
            'author_box_related_text_transform'  => '', // default = inherit
            'author_box_related_font_style'      => '', // default = inherit
            'author_box_related_text_decoration' => '', // default = inherit
            'author_box_related_text_align'      => '', // default = inherit
            'author_box_related_color'           => '', // inherit
            'author_box_margin_top'    => '20px',
            'author_box_margin_right'  => '0',
            'author_box_margin_bottom' => '20px',
            'author_box_margin_left'   => '0',

            'author_box_border_top'    => '1px',
            'author_box_border_right'  => '0',
            'author_box_border_bottom' => '1px',
            'author_box_border_left'   => '0',
            'author_box_border_style'  => 'solid',
            'author_box_border_color'  => '#e8e8e8',
            'author_box_border_radius' => 0,

            'author_box_padding_top'    => '0',
            'author_box_padding_right'  => '0',
            'author_box_padding_bottom' => '0',
            'author_box_padding_left'   => '0',

            'author_box_width'            => 100,
            'author_box_background_color' => '#f7f8f9',

            'author_box_layout'                  => 'slim',
            'author_box_profile_title'           => ( doing_action( 'init' ) or did_action( 'init' ) ) ? __( "Author Profile", 'molongui-authorship' ) : "Author Profile",
            'author_box_related_title'           => ( doing_action( 'init' ) or did_action( 'init' ) ) ? __( "Related Posts", 'molongui-authorship' ) : "Related Posts",
            'author_box_profile_layout'          => 'layout-1',
            'author_box_profile_valign'          => 'center',
            'author_box_bottom_background_color' => '#e0e0e0',
            'author_box_bottom_border_style'     => 'solid',
            'author_box_bottom_border_width'     => '1',
            'author_box_bottom_border_color'     => '#b6b6b6',

            'author_box_shadow_color'    => '#ababab',
            'author_box_shadow_h_offset' => 10,
            'author_box_shadow_v_offset' => 10,
            'author_box_shadow_blur'     => 10,
            'author_box_shadow_spread'   => '',
            'author_box_shadow_inset'    => 0,

            'author_box_tabs_position'                => 'top-full',
            'author_box_tabs_color'                   => '#f4f4f4',
            'author_box_tabs_background_color'        => 'transparent',
            'author_box_tabs_text_color'              => 'inherit',
            'author_box_tabs_active_background_color' => '#efefef',
            'author_box_tabs_active_text_color'       => 'inherit',
            'author_box_tabs_active_border'           => 'top',
            'author_box_tabs_active_border_style'     => 'solid',
            'author_box_tabs_active_border_width'     => '4',
            'author_box_tabs_active_border_color'     => 'orange',
            'author_box_custom_css'       => '',
            'author_box_custom_css_class' => '',
        ));
    }
    public function define_plugin_settings()
    {
        $editor_url = Author_Box_Editor::url();
        $is_pro     = Plugin::has_pro();

        $options = array();
if ( apply_filters( 'molongui_authorship/show_get_started_options', false ) )
{
$options[] = array
(
'display' => true,
'type'    => 'section',
'id'      => 'get_started',
'name'    => __( "Getting Started", 'molongui-authorship' ),
);
$options[] = array
(
    'display' => true,
    'deps'    => '',
    'search'  => '',
    'type'    => 'header',
    'class'   => '',
    'id'      => 'first_steps_header',
    'label'   => __( "First Steps", 'molongui-authorship' ),
    'button'  => array(),
);
$options[] = array
(
    'display' => true,
    'deps'    => '',
    'search'  => '',
    'type'    => 'button',
    'class'   => 'is-compact',
    'default' => '',
    /*! // translators: %s: The plugin name. */
    'label'   => sprintf( __( "%sGo edit a post%sFind the panel labeled as %sContributors%s%sStart typing the name of the contributor you want to add and pick your selection%sSelect how they contributed to the post%sSave changes and that should be it!%sIf you need to add more contributor roles, switch to the Contributors tab or click %s.", 'molongui-authorship' ),
        '<ol><li>', '</li><li>', '<code>', '</code>', '</li><li>', '</li><li>', '</li><li>', '</li></ol>', '<a href="'.admin_url( 'edit-tags.php?taxonomy=contributor_role' ).'" target="_blank">here</a>' ),
    'button'  => array
    (
        'display'  => true,
        'type'     => 'link',
        'href'     => admin_url( 'edit.php' ),
        'id'       => 'edit_posts',
        'label'    => __( "Posts", 'molongui-authorship' ),
        'title'    => __( "View All Posts", 'molongui-authorship' ),
        'class'    => 'same-width',
        'disabled' => false,
    ),
);
}
        if ( apply_filters( 'molongui_authorship/show_general_options', true ) )
        {
$options[] = array
(
    'display' => true,
    'type'    => 'section',
    'id'      => 'general',
    'name'    => __( "General", 'molongui-authorship' ),
);

            $options[] = array
            (
                'display'  => true,
                'type'     => 'callback',
                'callback' => array( $this, 'get_general_section' ),
            );
        }
        if ( apply_filters( 'molongui_authorship/show_writing_options', true ) )
        {
$options[] = array
(
    'display' => true,
    'type'    => 'section',
    'id'      => 'writing',
    'name'    => __( "Writing" ),
);

            $options[] = array
            (
                'display'  => true,
                'type'     => 'callback',
                'callback' => array( $this, 'get_writing_section' ),
            );
        }
        if ( apply_filters( 'molongui_authorship/show_reading_options', true ) )
        {
$options[] = array
(
    'display' => true,
    'type'    => 'section',
    'id'      => 'reading',
    'name'    => __( "Reading" ),
);

            $options[] = array
            (
                'display'  => true,
                'type'     => 'callback',
                'callback' => array( $this, 'get_reading_section' ),
            );
        }
        if ( apply_filters( 'molongui_authorship/show_integrations_options', true ) )
        {
$options[] = array
(
    'display' => true,
    'type'    => 'section',
    'id'      => 'integrations',
    'name'    => __( "Integrations" ),
);

            $options[] = array
            (
                'display'  => true,
                'type'     => 'callback',
                'callback' => array( $this, 'get_integrations_section' ),
            );
        }
        if ( apply_filters( 'molongui_authorship/show_shortcode_options', true ) )
        {
$options[] = array
(
    'display' => true,
    'type'    => 'section',
    'id'      => 'shortcodes',
    'name'    => __( "Shortcodes", 'molongui-authorship' ),
);

            $options[] = array
            (
                'display'  => true,
                'type'     => 'callback',
                'callback' => array( $this, 'get_shortcodes_section' ),
            );
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'header',
    'class'    => '',
    'id'       => 'author_box_shortcodes_header',
    'label'    => __( "Author Box", 'molongui-authorship' ),
    'buttons'  => array(),
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_box',
    'title'    => '[molongui_author_box]',
    'desc'     => sprintf( __( "Displays an author box anywhere you want. You can customize which author information to show and how the displayed author box will look like by using additional attributes. All styling settings can be overridden. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_box/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_box/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'header',
    'class'    => '',
    'id'       => 'byline_shortcodes_header',
    'label'    => __( "Post Byline", 'molongui-authorship' ),
    'buttons'  => array(),
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_post_byline',
    'title'    => '[molongui_post_byline]',
    'desc'     => sprintf( __( "Displays the post's byline. Most themes display the byline just below the title. You can place it anywhere you want using this shortcode. What is more, customize it by prepending and/or appending any text you like. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_byline/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_byline/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_post_avatars',
    'title'    => '[molongui_post_avatars]',
    'desc'     => sprintf( __( "This shortcode allows you to show the avatars of all the authors of a post. You can place it anywhere you want using this shortcode. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/help/post_avatars/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_byline/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'header',
    'class'    => '',
    'id'       => 'author_list_shortcodes_header',
    'label'    => __( "Author Lists", 'molongui-authorship' ),
    'buttons'  => array(),
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_list',
    'title'    => '[molongui_author_list]',
    'desc'     => sprintf( __( "Displays a list of (all) authors in your site anywhere you want. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_list/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_list/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_select',
    'title'    => '[molongui_author_select]',
    'desc'     => sprintf( __( "Displays a dropdown select listing (all the) authors from your blog. This shortcode is intended for developers only. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui-author-select/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui-author-select/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'header',
    'class'    => '',
    'id'       => 'author_list_shortcodes_header',
    'label'    => __( "Author Posts", 'molongui-authorship' ),
    'buttons'  => array(),
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_posts',
    'title'    => '[molongui_author_posts]',
    'desc'     => sprintf( __( "Displays a list showing (all the) posts from any given author anywhere you want. Listed posts can be configured making use of the optional attributes this shortcode can take. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_posts/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_posts/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'header',
    'class'    => '',
    'id'       => 'author_data_shortcodes_header',
    'label'    => __( "Author Data", 'molongui-authorship' ),
    'buttons'  => array(),
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_name',
    'title'    => '[molongui_author_name]',
    'desc'     => sprintf( __( "Displays the name of the current post author(s) if no attributes are provided or the name of a given author if you provide the author ID and author type. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_name/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_name/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_slug',
    'title'    => '[molongui_author_slug]',
    'desc'     => sprintf( __( "Displays the slug of the current post author(s) if no attributes are provided or the slug of a given author if you provide the author ID and author type. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_slug/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_slug/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_url',
    'title'    => '[molongui_author_url]',
    'desc'     => sprintf( __( "Displays the url to the archive page of the current post author(s) if no attributes are provided or the url to the archive page of a given author if you provide the author ID and author type. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_url/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_url/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_bio',
    'title'    => '[molongui_author_bio]',
    'desc'     => sprintf( __( "Displays the bio of the current post author(s) if no attributes are provided or the bio of a given author if you provide the author ID and author type. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_bio/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_bio/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_mail',
    'title'    => '[molongui_author_mail]',
    'desc'     => sprintf( __( "Displays the email address of the current post author(s) if no attributes are provided or the email address of a given author if you provide the author ID and author type. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_mail/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_mail/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_meta',
    'title'    => '[molongui_author_meta]',
    'desc'     => sprintf( __( "Displays the any author meta data of the current post author(s) if no attributes are provided or any author meta data of a given author if you provide the author ID and author type. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_meta/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_meta/",
);
$options[] = array
(
    'display'  => true,
    'advanced' => false,
    'deps'     => '',
    'search'   => '',
    'type'     => 'notice',
    'class'    => '',
    'default'  => '',
    'id'       => 'shortcode_molongui_author_avatar',
    'title'    => '[molongui_author_avatar]',
    'desc'     => sprintf( __( "Displays the avatar of the current post author(s) if no attributes are provided or the avatar of a given author if you provide the author ID and author type. %sLearn more →%s", 'molongui-authorship' ), '<a href="https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_avatar/" target="_blank">', '</a>' ),
    'help'     => '',
    'link'     => "https://www.molongui.com/docs/molongui-authorship/shortcodes/molongui_author_avatar/",
);
        }
        if ( apply_filters( 'molongui_authorship/show_tools_options', true ) )
        {
$options[] = array
(
    'display' => true,
    'type'    => 'section',
    'id'      => 'tools',
    'name'    => __( 'Tools' ),
);

            $options[] = array
            (
                'display'  => true,
                'type'     => 'callback',
                'callback' => array( $this, 'get_tools_section' ),
            );
        }
if ( apply_filters( 'molongui_authorship/show_help_options', true ) )
{
    $options[] = array
    (
        'display' => true,
        'type'    => 'section',
        'id'      => 'help',
        'name'    => __( 'Help' ),
    );
}

        $options = apply_filters( 'molongui_authorship/after_plugin_settings', $options );

        return $options;
    }
    public function get_general_section()
    {
        ob_start();

        UI::toggle( array
        (
            'id'          => 'author_box_enabled',
            'value'       => self::get( 'author_box_enabled', true ),
            'label'       => __( "Enable Author Box", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => __( "An author box is a great way to credit authors by displaying their information on posts. Customize its appearance and choose specific post types for automatic or manual display. Disable this option only if you don't want to show any author boxes.", 'molongui-authorship' ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_author_box_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'guest_author_enabled',
            'value'       => self::get( 'guest_author_enabled', true ),
            'label'       => __( "Enable Guest Authors", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => __( "Allow guest authors to be credited on your site without needing a user account. Enabling this setting lets you add profiles for guest authors, showcasing their contributions on selected post types without granting them access to the WordPress Dashboard. Use this to highlight contributors who don't have a regular login, such as freelancers or occasional writers. If disabled, guest author profiles will not be available..", 'molongui-authorship' ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_guest_author_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'co_authors_enabled',
            'value'       => self::get( 'co_authors_enabled', true ),
            'label'       => __( "Enable Co-Authors", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => __( "Enable the option to credit multiple authors on a single post, making it easy to recognize collaborative work. When enabled, you can configure which post types support multiple authors, allowing you to assign joint credit on selected content types. If disabled, only one author will be associated with each post.", 'molongui-authorship' ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_co_authors_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'local_avatar_enabled',
            'value'       => self::get( 'local_avatar_enabled', true ),
            'label'       => __( "Enable Custom Author Avatars", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => __( "Allow custom profile images for authors, offering a personalized look that isn’t tied to Gravatar. Enabling this option lets you upload unique images for each author’s avatar, giving you greater control over visual branding and profile presentation on your site. If disabled, author avatars will rely on Gravatar images.", 'molongui-authorship' ),
            'content'     => '',
        ));

        UI::toggle( array
        (
            'id'          => 'user_extra_data_enabled',
            'value'       => self::get( 'user_extra_data_enabled', true ),
            'label'       => __( "Enable Enhanced Author Profile", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => __( "Add more fields to author profiles to display detailed information like telephone, job position, company, and social network links. This option allows you to provide richer author bios, enhancing credibility and connection with readers. When enabled, you can configure and showcase additional information for each author. If disabled, only basic profile information will be displayed.", 'molongui-authorship' ),
            'content'     => '',
        ));

        self::plugin_data_removal();

        return ob_get_clean();
    }
    public function get_writing_section()
    {
        ob_start();

        UI::toggle( array
        (
            'id'          => 'default_post_author_enabled',
            'value'       => self::get( 'default_post_author_enabled', false ),
            'label'       => __( "Change Default Author", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => __( "Set one or more default authors for new posts, ideal for sites where posts are commonly attributed to specific authors or teams. When enabled, a search box will let you select default authors, simplifying the assignment process. If disabled, WordPress will continue with its default behavior, assigning the current user as the author on new posts.", 'molongui-authorship' ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_default_author_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'user_roles_enabled',
            'value'       => self::get( 'user_roles_enabled', true ),
            'label'       => __( "Manage User Roles", 'molongui-authorship' ),
            'disabled'    => false, //apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'description' => __( "Control which User Roles can be assigned as authors for posts, providing flexibility in post attribution. This setting is especially useful for sites with custom roles or collaborative content workflows, allowing you to expand authoring capabilities beyond standard roles.", 'molongui-authorship' ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_user_roles_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'contributors_writing_ad',
            'value'       => self::get( 'contributors_writing_ad', false ),
            'label'       => __( "Enable Post Contributors", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => !is_plugin_active( 'molongui-post-contributors/molongui-post-contributors.php' ),
            'class'       => '',
            'description' => __( "Recognize and showcase the talents behind your content! Add custom attributions—reviewers, fact-checkers, editors, photographers, and more—and display them alongside the post author. Elevate transparency and give credit where it's due.", 'molongui-authorship' ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_contributors_writing_ad_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'post_as_others',
            'value'       => self::get( 'post_as_others', false ),
            'echo'        => true,
            'class'       => '',
            'disabled'    => false,
            'label'       => __( "Enable Posting as Other Authors", 'molongui-authorship' ),
            'description' => __( "Allow users without specific permissions to publish content on behalf of other authors. This setting is useful for collaborative sites where roles outside of editors or admins may need to assign posts to designated authors.", 'molongui-authorship' ),
            'content'     => '',
        ));

        UI::toggle( array
        (
            'id'          => 'hide_others_posts_enabled',
            'value'       => self::get( 'hide_others_posts_enabled', false ),
            'disabled'    => false, //apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'label'       => __( "Hide Posts From Other Authors", 'molongui-authorship' ),
            'description' => __( "Limit selected user roles to viewing only their own posts in the Dashboard, helping authors focus on their content and keeping their post lists concise and manageable. This setting is ideal for maintaining privacy and minimizing distractions in collaborative environments. If disabled, all users can view posts from other authors.", 'molongui-authorship' ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_hide_others_posts_hidden_content(),
            ),
        ));

        return ob_get_clean();
    }
    public function get_reading_section()
    {
        ob_start();

        UI::toggle( array
        (
            'id'          => 'byline_customization_enabled',
            'value'       => self::get( 'byline_customization_enabled', true ),
            'label'       => __( "Customize Post Byline", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s%s"
                , '<p>'
                , __( "The byline on a post gives the name of the people who contributed to write it.", 'molongui-authorship' )
                , '</p>'
            ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_post_byline_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'author_box_customization_enabled',
            'value'       => self::get( 'author_box_customization_enabled', true ),
            'label'       => __( "Customize Author Box Display", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s %s%s"
                , '<p>'
                , __( "Enable this option to access and adjust the author box display settings for posts across your site. Please note that some of these display settings might be overridden by specific configurations set at the individual post or author level.", 'molongui-authorship' )
                , '<a href="https://www.molongui.com/docs/molongui-authorship/author-box/display-settings/" target="_blank">Learn more →</a>'
                , '</p>'
            ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_author_box_customization_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'author_pages_enabled',
            'value'       => self::get( 'author_pages_enabled', true ),
            'label'       => __( "Enable Author Pages", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s %s%s"
                , '<p>'
                , __( "Enable this option to access and adjust the author box display settings for posts across your site. Please note that these display settings might be overridden by specific configurations set at the individual post or author level.", 'molongui-authorship' )
                , '<a href="https://www.molongui.com/docs/molongui-authorship/author-box/display-settings/" target="_blank">Learn more →</a>'
                , '</p>'
            ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_author_pages_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'search_results_enabled',
            'value'       => self::get( 'search_results_enabled', false ),
            'label'       => __( "Enable Enhanced Search Functionality", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s %s%s"
                , '<p>'
                , __( "Enable this option to activate advanced search options, including searching content by author name and displaying guest author archives in search results.", 'molongui-authorship' )
                , ''//'<a href="https://www.molongui.com/docs/molongui-authorship/author-box/display-settings/" target="_blank">Learn more →</a>'
                , '</p>'
            ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_search_results_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'author_data_encode_enabled',
            'value'       => self::get( 'author_data_encode_enabled', false ),
            'label'       => __( "Encode Sensitive Author Information", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s %s%s"
                , '<p>'
                , __( "Enable this option to encode sensitive information to make it unreadable to SPAM bots.", 'molongui-authorship' )
                , '<a href="https://www.molongui.com/docs/molongui-authorship/author-box/display-settings/" target="_blank">Learn more →</a>'
                , '</p>'
            ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_author_data_encode_hidden_content(),
            ),
        ));

        return ob_get_clean();
    }
    public function get_integrations_section()
    {
        ob_start();

        UI::toggle( array
        (
            'id'          => 'social_profiles_enabled',
            'value'       => self::get( 'social_profiles_enabled', true ),
            'label'       => __( "Enable Social Profiles", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable to configure social profiles for authors and select which social networks to include.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_social_profiles_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'seo_settings_enabled',
            'value'       => self::get( 'seo_settings_enabled', true ),
            'label'       => __( "Enable SEO Settings", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s %s%s"
                                , '<p>'
                                , __( "Enable to access settings for managing schema markup and meta tags to enhance SEO.", 'molongui-authorship' )
                                , '' //'<a href="https://www.molongui.com/docs/molongui-authorship/author-box/display-settings/" target="_blank">Learn more →</a>'
                                , '</p>'
                             ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_seo_settings_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'rest_api_integration_enabled',
            'value'       => self::get( 'rest_api_integration_enabled', false ),
            'label'       => __( "Enable REST API Integration", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s %s%s"
                                , '<p>'
                                , __( "Expose author data through the WordPress REST API, allowing seamless integration with external applications and tools.", 'molongui-authorship' )
                                , '' //'<a href="" target="_blank">Learn more →</a>'
                                , '</p>'
                             ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_rest_api_integration_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'compatibility_mode_enabled',
            'value'       => self::get( 'compatibility_mode_enabled', true ),
            'label'       => __( "Enable Compatibility Mode", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s %s%s"
                                , '<p>'
                                , __( "Enable to access settings for compatibility with themes and plugins.", 'molongui-authorship' )
                                , ''//'<a href="https://www.molongui.com/docs/molongui-authorship/author-box/display-settings/" target="_blank">Learn more →</a>'
                                , '</p>'
                             ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_compatibility_mode_hidden_content(),
            ),
        ));

        UI::toggle( array
        (
            'id'          => 'dashboard_settings_enabled',
            'value'       => self::get( 'dashboard_settings_enabled', true ),
            'label'       => __( "Customize Dashboard Integration", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => sprintf( "%s%s %s%s"
                                , '<p>'
                                , __( "Enable to access settings for customizing how the plugin integrates with the WordPress Dashboard, including menu placement and action link behavior.", 'molongui-authorship' )
                                , '' //'<a href="https://www.molongui.com/docs/molongui-authorship/author-box/display-settings/" target="_blank">Learn more →</a>'
                                , '</p>'
                             ),
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_dashboard_settings_hidden_content(),
            ),
        ));

        return ob_get_clean();
    }
    public function get_shortcodes_section()
    {
        ob_start();

        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'premium',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Need to display author info anywhere?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Upgrade to Pro for shortcode access—perfect for page builders like Elementor, Divi, Beaver, or Visual Composer!", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'shortcodes',
            'button'  => __( "Upgrade Now", 'molongui-authorship' ),
            'premium' => true,
        ));

        return ob_get_clean();
    }
    public function get_tools_section()
    {
        ob_start();

        do_action( 'molongui_authorship/before_tools_section' );

        UI::banner( array
        (
            'echo'   => true,
            'margin' => 0,
            'icon'   => 'wizard',
            'text'   => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Getting Started?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , sprintf( __( "We'll guide you through each step needed to get %s fully set up on your site.", 'molongui-authorship' ), MOLONGUI_AUTHORSHIP_TITLE )
                            , '</div>'
                        ),
            'link'   => admin_url( 'index.php?page=' . MOLONGUI_AUTHORSHIP_NAME . '-setup-wizard' ),
            'target' => '_self',
            'button' => __( "Launch Setup", 'molongui-authorship' ),
        ));

        do_action( 'molongui_authorship/before_data_migration_tools' );

        if ( has_action( 'molongui_authorship/data_migration_tools' ) )
        {
            UI::heading( array
            (
                'title' => __( "Data Migration", 'molongui-authorship' ),
            ));

            echo '<div class="molongui-ui-cards">';

            do_action( 'molongui_authorship/data_migration_tools' );

            echo '</div>';
        }

        do_action( 'molongui_authorship/before_plugin_settings_tools' );

        UI::heading( array
        (
            'title' => __( "Plugin Settings", 'molongui-authorship' ),
        ));

        self::plugin_settings_control();

        do_action( 'molongui_authorship/before_customization_tools' );

        UI::heading( array
        (
            'title' => __( "Custom CSS", 'molongui-authorship' ),
        ));

        self::custom_css_control();

        UI::heading( array
        (
            'title' => __( "Custom PHP", 'molongui-authorship' ),
        ));

        self::custom_php_control();

        UI::banner( array
        (
            'echo'   => true,
            'margin' => 0,
            'icon'   => 'support',
            'text'   => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Facing issues applying customizations?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Open a support ticket, and our team will assist you promptly. We're here to help!", 'molongui-authorship' )
                            , '</div>'
                        ),
            'link'   => esc_url( admin_url( 'admin.php?page=molongui-authorship&tab=help#molongui-help-ticket-form' ) ),
            'target' => '_self',
            'button' => __( "Request Help", 'molongui-authorship' ),
        ));

        do_action( 'molongui_authorship/before_guest_authors_tools' );

        UI::heading( array
        (
            'title' => __( "Guest Authors", 'molongui-authorship' ),
        ));

        echo '<div class="molongui-ui-cards">';

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Export", 'molongui-authorship' ),
            'description' => __( "Download a backup of guest authors profiles.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'export_guests',
                'label' => __( "Export", 'molongui-authorship' ),
                'link'  => '',
                'nonce' => 'molongui_export_guests',
            ),
            'echo'        => true,
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', 'premium' ),
            'class'       => '',
        ));

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Import", 'molongui-authorship' ),
            'description' => __( "Import a backup of guest authors profiles.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'     => 'import_guests',
                'label'  => __( "Import", 'molongui-authorship' ),
                'accept' => '.json',
            ),
            'echo'        => true,
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', 'premium' ),
            'class'       => '',
        ));

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Delete All", 'molongui-authorship' ),
            'description' => __( "Delete all guest authors <code>CAUTION!</code>", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'delete_guests',
                'label' => __( "Delete", 'molongui-authorship' ),
                'link'  => '',
            ),
            'echo'        => true,
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', 'premium' ),
            'class'       => '',
        ));

        echo '</div>';

        do_action( 'molongui_authorship/before_authorship_tools' );

        UI::heading( array
        (
            'title' => __( "Posts Authorship", 'molongui-authorship' ),
        ));

        echo '<div class="molongui-ui-cards">';

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Export", 'molongui-authorship' ),
            'description' => __( "Download a backup of post authorship metadata.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'export_authorship',
                'label' => __( "Export", 'molongui-authorship' ),
                'link'  => '',
            ),
            'echo'        => true,
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', 'premium' ),
            'class'       => '',
        ));

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Import", 'molongui-authorship' ),
            'description' => __( "Import a backup of post authorship metadata.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'     => 'import_authorship',
                'label'  => __( "Import", 'molongui-authorship' ),
                'accept' => '.json',
            ),
            'echo'        => true,
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', 'premium' ),
            'class'       => '',
        ));

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Reset Authorship", 'molongui-authorship' ),
            'description' => __( "Delete all post authorship metadata <code>CAUTION!</code>", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'delete_authorship',
                'label' => __( "Reset", 'molongui-authorship' ),
                'link'  => '',
            ),
            'echo'        => true,
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', 'premium' ),
            'class'       => '',
        ));

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Update Post Count", 'molongui-authorship' ),
            'description' => __( "Force a post count update.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'update_counters',
                'label' => __( "Update", 'molongui-authorship' ),
                'link'  => '',
            ),
            'echo'        => true,
            'disabled'    => false,
            'class'       => '',
        ));

        echo '</div>';

        do_action( 'molongui_authorship/before_other_tools' );

        UI::heading( array
        (
            'title' => __( "Additional Tools", 'molongui-authorship' ),
        ));

        echo '<div class="molongui-ui-cards">';

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Clear Cache", 'molongui-authorship' ),
            'description' => __( "Clear the object cache used by the plugin.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'clear-cache',
                'label' => __( "Clear", 'molongui-authorship' ),
                'link'  => '',
            ),
            'echo'        => true,
            'disabled'    => false,
            'class'       => '',
        ));

        echo '</div>';

        do_action( 'molongui_authorship/after_tools_section' );

        return ob_get_clean();
    }
    public function get_author_box_hidden_content()
    {
        ob_start();

        UI::checkbox_group( array
        (
            'id'          => 'author_box_post_types',
            'selected'    => self::get( 'author_box_post_types', 'post' ),
            'options'     => self::get_post_type_options(),
            'echo'        => true,
            'class'       => '',
            'title'       => __( "Select Post Types for Author Box", 'molongui-authorship' ),
            'help'        => __( "Choose the post types where you want the author box to be displayed automatically. Once selected, the author box will appear on all posts within these types without needing additional settings on each post.", 'molongui-authorship' ),
            'description' => '',
            'tooltip'     => __( "The free version supports author boxes on Posts and Pages only. <span class='highlighted'>Upgrade to Pro</span> to enable them on any Post Type.", 'molongui-authorship' ),
        ));
        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'premium',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Want to display the author box beyond posts and pages?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Upgrade now to enable it for additional Post Types.", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'authorBoxPostTypes',
            'button'  => '',
            'premium' => true,
        ));

        UI::banner( array
        (
            'echo'   => true,
            'margin' => 0,
            'icon'   => 'filter',
            'text'   => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Customize Your Author Box!</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Use the Author Box Editor to modify layout, colors, borders, and more.", 'molongui-authorship' )
                            , '</div>'
                        ),
            'link'   => Author_Box_Editor::url(),
            'button' => __( "Open Editor", 'molongui-authorship' ),
        ));

        return ob_get_clean();
    }
    public function get_guest_author_hidden_content()
    {
        ob_start();

        UI::checkbox_group( array
        (
            'id'          => 'guest_author_post_types',
            'selected'    => self::get( 'guest_author_post_types', 'post' ),
            'options'     => self::get_post_type_options(),
            'echo'        => true,
            'class'       => '',
            'title'       => __( "Select Post Types for Guest Authors", 'molongui-authorship' ),
            'help'        => __( "Choose the post types where you want to display guest authors. Once selected, you'll be able to assign guest author profiles to individual posts within these types, showcasing their contributions directly on the post.", 'molongui-authorship' ),
            'description' => '',
            'tooltip'     => __( "The free version supports guest authors on Posts and Pages only. <span class='highlighted'>Upgrade to Pro</span> to enable them on any Post Type.", 'molongui-authorship' ),
        ));

        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'premium',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Looking to assign guest authors beyond posts and pages?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Upgrade now to enable them for additional Post Types.", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'guestAuthorPostTypes',
            'button'  => '',
            'premium' => true,
        ));

        return ob_get_clean();
    }
    public function get_co_authors_hidden_content()
    {
        ob_start();

        UI::checkbox_group( array
        (
            'id'          => 'co_authors_post_types',
            'selected'    => self::get( 'co_authors_post_types', 'post' ),
            'options'     => self::get_post_type_options(),
            'echo'        => true,
            'class'       => '',
            'title'       => __( "Select Post Types for Co-Authors", 'molongui-authorship' ),
            'help'        => __( "Choose the post types where you want to allow multiple authors. Posts within these types can feature multiple author profiles, highlighting collaborative efforts and showcasing each contributor's role.", 'molongui-authorship' ),
            'description' => '',
            'tooltip'     => __( "The free version supports co-authors on Posts and Pages only. <span class='highlighted'>Upgrade to Pro</span> to enable them on any Post Type.", 'molongui-authorship' ),
        ));

        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'premium',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Need to add multiple authors beyond posts and pages?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Upgrade now to enable Co-Authors on additional Post Types.", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'coAuthorsPostTypes',
            'button'  => '',
            'premium' => true,
        ));

        return ob_get_clean();
    }
    public function get_default_author_hidden_content()
    {
        ob_start();
        ?>
        <div class="molongui-ui-checkbox-group__title" style="margin-bottom: 2em">
            <?php esc_html_e( "Select Default Authors for New Posts", 'molongui-authorship' ); ?>
            <?php UI::help_markup( __( "Choose one or more authors to be automatically assigned to new posts. This ensures consistent author attribution, streamlining the post creation process for teams or shared accounts.", 'molongui-authorship' ) ); ?>
        </div>
        <?php
        Admin_Post::author_selector( null, 'settings' );
        return ob_get_clean();
    }
    public function get_user_roles_hidden_content()
    {
        ob_start();

        UI::checkbox_group( array
        (
            'id'          => 'user_roles',
            'selected'    => self::get( 'user_roles', 'administrator,editor,author,contributor' ),
            'options'     => self::get_user_role_options(),
            'echo'        => true,
            'class'       => '',
            'title'       => __( "Select User Roles Eligible for Authoring Posts", 'molongui-authorship' ),
            'help'        => __( "Choose the user roles you want to assign as authors on posts. Expanding eligible roles provides flexibility in content management, making it easier to assign appropriate authorship across different types of contributors.", 'molongui-authorship' ),
            'description' => '',
            'tooltip'     => __( "Limited in Free Version. <span class='highlighted'>Upgrade to Pro</span> to customize User Role settings.", 'molongui-authorship' ),
        ));

        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'premium',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Selecting eligible User Roles is a premium feature</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Upgrade now to choose which User Roles can be assigned as post authors.", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'userRoles',
            'button'  => '',
            'premium' => true,
        ));

        return ob_get_clean();
    }
    public function get_contributors_writing_ad_hidden_content()
    {
        ob_start();

        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'download',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Custom attributions requires the Molongui Post Contributors plugin</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Install it <u>free</u> to start adding contributors to your posts.", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=molongui-post-contributors' ), 'install-plugin_molongui-post-contributors' ),
            'button'  => __( "Install Now", 'molongui-authorship' ),
            'premium' => false,
        ));

        return ob_get_clean();
    }
    public function get_hide_others_posts_hidden_content()
    {
        ob_start();

        UI::checkbox_group( array
        (
            'id'          => 'hide_others_posts',
            'selected'    => self::get( 'hide_others_posts', '' ),
            'options'     => self::get_user_role_options(),
            'echo'        => true,
            'class'       => '',
            'title'       => __( "Select User Roles with Restricted Post Views", 'molongui-authorship' ),
            'help'        => __( "Choose the user roles that will see only their own posts in the Dashboard. This restriction helps streamline content management by displaying only relevant posts to selected users.", 'molongui-authorship' ),
            'description' => '',
            'tooltip'     => __( "Limited in Free Version. <span class='highlighted'>Upgrade to Pro</span> to customize User Role settings.", 'molongui-authorship' ),
        ));

        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'premium',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Restricting post view by User Role is a premium feature</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Upgrade now to control which User Roles can see only their own posts in the Dashboard.", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'hideOthersPosts',
            'button'  => '',
            'premium' => true,
        ));

        return ob_get_clean();
    }
    public function get_post_byline_hidden_content()
    {
        ob_start();

        UI::checkbox( array
        (
            'id'         => 'co_authors_in_byline',
            'value'      => self::get( 'co_authors_in_byline', true ),
            'label'      => __( "Display co-authors in the post byline", 'molongui-authorship' ),
            'echo'       => true,
            'class'      => '',
            'help'       => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When a post is written by one single person, that person's name is the one displayed on the post byline. However, on co-authored posts you have the option to choose how author names are displayed.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_co_authors_in_byline_hidden_content(),
            ),
        ));
        UI::checkbox( array
        (
            'id'          => 'co_authors_separators',
            'value'       => self::get( 'co_authors_separators', false ),
            'label'       => __( "Define custom separators between co-authors", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Leave unchecked to separate co-author names with commas and use 'and' before the final co-author.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_co_authors_separators_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'byline_name_link',
            'value'       => self::get( 'byline_name_link', true ),
            'label'       => __( "Make author name link to their author page", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "By default, author names in bylines link to their archive pages. Uncheck this option to disable these links and display names as plain text.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'byline_custom_prefix_suffix',
            'value'       => self::get( 'byline_custom_prefix_suffix', false ),
            'label'       => __( "Define custom prefix and suffix", 'molongui-authorship' ),
            'class'       => '',
            'echo'        => true,
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Customize your post byline adding custom text before and after the post byline.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_byline_custom_prefix_suffix_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'contributors_reading_ad',
            'value'       => self::get( 'contributors_reading_ad', false ),
            'label'       => __( "Display additional post information", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Bylines usually provide additional information about when content was written or updated, categories or comments count.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_contributors_reading_ad_hidden_content(),
            ),
        ));

        return ob_get_clean();
    }
    public function get_co_authors_in_byline_hidden_content()
    {
        ob_start();

        UI::radio( array
        (
            'id'       => 'co_authors_in_byline_format',
            'selected' => self::get( 'co_authors_in_byline_format', 'all' ),
            'options'  => array
            (
                array
                (
                    'value'       => 'all',
                    'label'       => __( "Show All Co-Authors", 'molongui-authorship' ),
                    'description' => __( "John Doe, Emma Johnson, Isabella Brown, James Carter and Liam Thompson", 'molongui-authorship' ),
                    'disabled'    => false,
                ),
                array
                (
                    'value'       => '1',
                    'label'       => __( "Show Primary Author + Others Count", 'molongui-authorship' ),
                    'description' => __( "John Doe and 4 more", 'molongui-authorship' ),
                    'disabled'    => false,
                ),
                array
                (
                    'value'       => '2',
                    'label'       => __( "Show Primary Author, 1 Co-Author + Others", 'molongui-authorship' ),
                    'description' => __( "John Doe, Emma Johnson and 3 more", 'molongui-authorship' ),
                    'disabled'    => false,
                ),
                array
                (
                    'value'       => '3',
                    'label'       => __( "Show Primary Author, 2 Co-Authors + Others", 'molongui-authorship' ),
                    'description' => __( "John Doe, Emma Johnson, Isabella Brown and 2 more", 'molongui-authorship' ),
                    'disabled'    => false,
                ),
            ),
            'echo'     => true,
            'class'    => '',
            'title'    => __( "Choose how multiple authors are displayed in the post byline:", 'molongui-authorship' ),
            'tooltip'  => '',
        ));

        return ob_get_clean();
    }
    public function get_co_authors_separators_hidden_content()
    {
        ob_start();

        UI::input_text( array
        (
            'id'          => 'co_authors_separator',
            'value'       => self::get( 'co_authors_separator', ',' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'label'       => __( "Separator", 'molongui-authorship' ),
            'description' => __( "You can use any word, string or symbol except <code>?</code>, <code>/</code> and <code>*</code>. These characters will be removed if included.", 'molongui-authorship' ),
            'tooltip'     => __( "Setting not available in Free Version. <b>Upgrade</b> to Pro to customize co-author separators.", 'molongui-authorship' ),
        ));

        UI::input_text( array
        (
            'id'          => 'co_authors_last_separator',
            'value'       => self::get( 'co_authors_last_separator', 'and' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'label'       => __( "Last Separator", 'molongui-authorship' ),
            'description' => __( "You can use any word, string or symbol except <code>?</code>, <code>/</code> and <code>*</code>. These characters will be removed if included.", 'molongui-authorship' ),
            'tooltip'     => __( "Setting not available in Free Version. <b>Upgrade</b> to Pro to customize co-author separators.", 'molongui-authorship' ),
        ));

        return ob_get_clean();
    }
    public function get_byline_custom_prefix_suffix_hidden_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo' => true,
            'text' => __( "Upgrade today to gain access to these options and take full control of your post bylines", 'molongui-authorship' ),
            'link' => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'customCoAuthorsSeparators',
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::input_text( array
        (
            'id'          => 'byline_prefix',
            'value'       => self::get( 'byline_prefix', '' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'label'       => __( "Byline Prefix", 'molongui-authorship' ),
            'description' => __( "You can enter any text. HTML markup is accepted, so you can add your own styles and custom elements.", 'molongui-authorship' ),
            'tooltip'     => __( "Setting not available in Free Version. <b>Upgrade</b> to Pro to add a custom text at the beginning of your post byline.", 'molongui-authorship' ),
        ));

        UI::input_text( array
        (
            'id'          => 'byline_suffix',
            'value'       => self::get( 'byline_suffix', '' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'label'       => __( "Byline Suffix", 'molongui-authorship' ),
            'description' => __( "You can enter any text. HTML markup is accepted, so you can add your own styles and custom elements.", 'molongui-authorship' ),
            'tooltip'     => __( "Setting not available in Free Version. <b>Upgrade</b> to Pro to add a custom text at the beginning of your post byline.", 'molongui-authorship' ),
        ));

        UI::div_close( array
        (
            'id'    => '',
            'class' => '',
            'echo'  => true,
        ));

        return ob_get_clean();
    }
    public function get_contributors_reading_ad_hidden_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo'   => true,
            'keep'   => true,
            'icon'   => 'trumphet',
            'text'   => __( "Install Free the Molongui Post Contributors plugin and customize your post bylines further", 'molongui-authorship' ),
            'button' => __( "Install", 'molongui-authorship' ),
            'link'   => wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=molongui-post-contributors' ), 'install-plugin_molongui-post-contributors' ),
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::checkbox_group( array
        (
            'id'       => '',
            'selected' => 'authors',
            'options'  => array
            (
                array
                (
                    'value'    => 'authors',
                    'label'    => __( "Authors", 'molongui-authorship' ),
                    'tooltip'  => __( "<b>Molongui Post Contributors</b> allows you to hide the post author.", 'molongui-authorship' ),
                    'disabled' => true,
                ),
                array
                (
                    'value'    => 'contributors',
                    'label'    => __( "Contributors", 'molongui-authorship' ),
                    'tooltip'  => __( "<b>Molongui Post Contributors</b> allows you to add and display reviewers, fact-checkers and other contributors to your posts.", 'molongui-authorship' ),
                    'disabled' => true,
                ),
                array
                (
                    'value'    => 'avatars',
                    'label'    => __( "Avatars", 'molongui-authorship' ),
                    'tooltip'  => __( "Want to display avatars in your post byline? Install <b>Molongui Post Contributors</b>. It's free!", 'molongui-authorship' ),
                    'disabled' => true,
                ),
                array
                (
                    'value'    => 'publish',
                    'label'    => __( "Publish Date", 'molongui-authorship' ),
                    'tooltip'  => __( "Choose whether to display the date in which the post was published.", 'molongui-authorship' ),
                    'disabled' => true,
                ),
                array
                (
                    'value'    => 'update',
                    'label'    => __( "Update Date", 'molongui-authorship' ),
                    'tooltip'  => __( "Choose whether to display the date in which the post was last updated.", 'molongui-authorship' ),
                    'disabled' => true,
                ),
                array
                (
                    'value'    => 'categories',
                    'label'    => __( "Categories", 'molongui-authorship' ),
                    'tooltip'  => '',
                    'disabled' => true,
                ),
                array
                (
                    'value'    => 'tags',
                    'label'    => __( "Tags", 'molongui-authorship' ),
                    'tooltip'  => '',
                    'disabled' => true,
                ),
                array
                (
                    'value'    => 'comment_count',
                    'label'    => __( "Comment Count", 'molongui-authorship' ),
                    'tooltip'  => '',
                    'disabled' => true,
                ),
            ),
            'echo'     => true,
            'class'    => '',
            'title'    => __( "Choose what post information to display in the post byline:", 'molongui-authorship' ),
            'tooltip'  => __( "<b>Molongui Post Contributors</b> allows you to add contributors to your posts and display additional post information in your post byline.", 'molongui-authorship' ),

        ));

        UI::div_close( array
        (
            'id'    => '',
            'class' => '',
            'echo'  => true,
        ));

        return ob_get_clean();
    }
    public function get_author_box_customization_hidden_content()
    {
        ob_start();
        UI::select( array
        (
            'id'          => 'author_box_position',
            'selected'    => self::get( 'author_box_position', 'below' ),
            'options'     => array
            (
                array
                (
                    'value'    => 'above',
                    'label'    => __( "Above post content", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'below',
                    'label'    => __( "Below post content", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'both',
                    'label'    => __( "Above and below post content", 'molongui-authorship' ),
                    'disabled' => false,
                ),
            ),
            'label'       => __( "Author Box Position", 'molongui-authorship' ),
            'echo'        => true,
            'disabled'    => false,
            'class'       => '',
            'description' => /*sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, the author box will not appear for authors without a description.", 'molongui-authorship' )
                                , '</p>'
                             )*/'',
        ));

        UI::notice( array
        (
            'echo'   => true,
            'margin' => 0,
            'icon'   => 'filter',
            'text'   => sprintf( "%s%s%s%s%s%s"
                , '<div>'
                , __( "<b>Author Box Displayed Below Other Widgets?</b>", 'molongui-authorship' )
                , '</div>'
                , '<div>'
                , __( "Use the <code>'molongui_authorship/author_box_hook_priority'</code> filter to adjust its placement.", 'molongui-authorship' )
                , '</div>'
            ),
            'link'   => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'authorBoxHookPriority',
            'button' => __( "Learn More", 'molongui-authorship' ),
        ));

        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'bulb',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Want to display the author box in a different spot?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Upgrade to Pro and use the <code>[molongui_author_box]</code> shortcode.", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'authorBoxShortcode',
            'button'  => __( "Upgrade Now", 'molongui-authorship' ),
            'premium' => true,
        ));

        UI::checkbox( array
        (
            'id'          => 'author_box_auto_display_override',
            'value'       => self::get( 'author_box_auto_display_override', true ),
            'label'       => __( "Automatically Show the Author Box on Enabled Post Types", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => __( "Uncheck this option to manually select the post types where the author box will be displayed automatically. Leave it checked to display the author box on all enabled post types by default.", 'molongui-authorship' ),
            'description' => '',
            'content'     => array
            (
                'off' => $this->get_author_box_auto_display_post_types_hidden_content(),
                'on'  => '',
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'author_box_hide_if_empty_bio',
            'value'       => self::get( 'author_box_hide_if_empty_bio', false ),
            'label'       => __( "Hide Author Box When Bio Is Empty", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable this option to hide the author box when the author's description is empty. This helps maintain a clean appearance on posts where author information hasn't been provided.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'author_box_hide_on_category_enabled',
            'value'       => self::get( 'author_box_hide_on_category_enabled', false ),
            'label'       => __( "Hide Author Box For Selected Post Categories", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable this option to hide the author box on posts in specific categories. Once selected, you'll be able to choose which categories should suppress the author box, allowing for a cleaner look on content where author details may not be necessary.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_author_box_hide_on_category_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'author_box_for_co_authors',
            'value'       => self::get( 'author_box_for_co_authors', true ),
            'label'       => __( "Show Each Author in a Separate Box for Multi-Author Posts", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "By default, all authors are displayed in a single author box on multi-authored posts. If you check this option, each author will have a separate box, allowing individual author details to be highlighted separately. This can be useful if you want to give distinct visibility to each contributor on a post with multiple authors.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'author_box_show_related_posts',
            'value'       => self::get( 'author_box_show_related_posts', true ),
            'label'       => __( "Display Related Posts From The Same Author in The Author Box", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable this option to hide the author box on posts in specific categories. Once selected, you'll be able to choose which categories should suppress the author box, allowing for a cleaner look on content where author details may not be necessary.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_author_box_related_posts_hidden_content(),
            ),
        ));

        return ob_get_clean();
    }
public function get_author_box_position_hidden_content()
{
    ob_start();

    UI::checkbox( array
    (
        'id'          => 'author_box_display_both',
        'value'       => self::get( 'author_box_display_both', false ),
        'label'       => __( "Display the author box on both above and below post content", 'molongui-authorship' ),
        'echo'        => true,
        'class'       => '',
        'help'        => /*sprintf( "%s%s%s"
                            , '<p>'
                            , __( "When enabled, the author box will not appear for authors without a description.", 'molongui-authorship' )
                            , '</p>'
                         )*/'',
        'description' => '',
        'content'     => '',
    ));

    UI::select( array
    (
        'id'          => 'author_box_display_both',
        'selected'    => self::get( 'author_box_display_both', false ),
        'options'     => array
        (
            array
            (
                'value'    => 'above',
                'label'    => __( "Above post content", 'molongui-authorship' ),
                'disabled' => false,
            ),
            array
            (
                'value'    => 'below',
                'label'    => __( "Below post content", 'molongui-authorship' ),
                'disabled' => false,
            ),
            array
            (
                'value'    => 'both',
                'label'    => __( "Above and below post content", 'molongui-authorship' ),
                'disabled' => false,
            ),
        ),
        'label'       => __( "Author Box Position", 'molongui-authorship' ),
        'echo'        => true,
        'disabled'    => false,
        'class'       => '',
        'description' => /*sprintf( "%s%s%s"
                            , '<p>'
                            , __( "When enabled, the author box will not appear for authors without a description.", 'molongui-authorship' )
                            , '</p>'
                         )*/'',
    ));

    return ob_get_clean();
}
    public function get_author_box_auto_display_post_types_hidden_content()
    {
        ob_start();

        UI::checkbox_group( array
        (
            'id'       => 'author_box_auto_display_post_types',
            'selected' => self::get( 'author_box_auto_display_post_types', 'post' ),
            'options'  => self::get_post_type_options(),
            'echo'     => true,
            'class'    => '',
            'title'    => __( "Select Post Types for Automatic Display", 'molongui-authorship' ),
            'help'     => __( "Choose the post types where you want the author box to be displayed automatically. Once selected, the author box will appear on all posts within these types without needing additional settings on each post.", 'molongui-authorship' ),
        ));
        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'premium',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Looking to automatically display the author box beyond posts and pages?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Upgrade now to enable it for additional Post Types.", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'authorBoxAutoDisplayPostTypes',
            'button'  => '',
            'premium' => true,
        ));

        return ob_get_clean();
    }
    public function get_author_box_hide_on_category_hidden_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo' => true,
            'text' => __( "Upgrade today to gain access to this option and take full control of your author box", 'molongui-authorship' ),
            'link' => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'hideAuthorBoxOnCategory',
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::checkbox_group( array
        (
            'id'          => 'author_box_hide_on_category',
            'selected'    => self::get( 'author_box_hide_on_category', '' ),
            'options'     => $this->get_post_category_options(),
            'echo'        => true,
            'class'       => '',
            'title'       => __( "Select Post Categories to Hide the Author Box", 'molongui-authorship' ),
            'help'        => '',
            'description' => '',
            'tooltip'     => __( "<b>Upgrade</b> to unlock this premium setting and hide the author box on a post category basis.", 'molongui-authorship' ),
        ));

        UI::div_close( array
        (
            'id'    => '',
            'class' => '',
            'echo'  => true,
        ));

        return ob_get_clean();
    }
    public function get_author_box_related_posts_hidden_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo' => true,
            'text' => __( "Upgrade to customize default values and enhance your control over these settings", 'molongui-authorship' ),
            'link' => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'authorBoxRelatedPosts',
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::input_number( array
        (
            'id'          => 'author_box_related_posts_count',
            'value'       => self::get( 'author_box_related_posts_count', 4 ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => __( "Setting not available in Free Version. <b>Upgrade</b> to Pro to add a custom text at the beginning of your post byline.", 'molongui-authorship' ),
            'min'         => 1,
            'max'         => '',
            'label'       => __( "Posts To Display", 'molongui-authorship' ),
            'description' => '',
        ));

        UI::select( array
        (
            'id'          => 'author_box_related_posts_orderby',
            'selected'    => self::get( 'author_box_related_posts_orderby', 'date' ),
            'options'     => array
            (
                array
                (
                    'value'    => 'title',
                    'label'    => __( "Post Title", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'date',
                    'label'    => __( "Post Publish Date", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'modified',
                    'label'    => __( "Post Update Date", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'comment_count',
                    'label'    => __( "Post Comment Count", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'rand',
                    'label'    => __( "Random Order", 'molongui-authorship' ),
                    'disabled' => false,
                ),
            ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'label'       => __( "Order By", 'molongui-authorship' ),
            'description' => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Select the criteria to sort related entries by.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'tooltip'     => __( "Setting not available in Free Version. <b>Upgrade</b> to Pro to add a custom text at the beginning of your post byline.", 'molongui-authorship' ),
        ));

        UI::select( array
        (
            'id'          => 'author_box_related_posts_order',
            'selected'    => self::get( 'author_box_related_posts_order', 'DESC' ),
            'options'     => array
            (
                array
                (
                    'value'    => 'ASC',
                    'label'    => __( "Ascending", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'DESC',
                    'label'    => __( "Descending", 'molongui-authorship' ),
                    'disabled' => false,
                ),
            ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'label'       => __( "Order", 'molongui-authorship' ),
            'description' => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Select the order by which to list related entries.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'tooltip'     => __( "Setting not available in Free Version. <b>Upgrade</b> to Pro to add a custom text at the beginning of your post byline.", 'molongui-authorship' ),
        ));

        UI::checkbox_group( array
        (
            'id'       => 'author_box_related_posts_post_types',
            'selected' => self::get( 'author_box_related_posts_post_types', 'post' ),
            'options'  => self::get_post_type_options(),
            'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'     => true,
            'class'    => '',
            'title'    => __( "Post Types", 'molongui-authorship' ),
            'layout'   => 'inline',
        ));

        UI::div_close( array
        (
            'id'    => '',
            'class' => '',
            'echo'  => true,
        ));

        return ob_get_clean();
    }
public function get_author_pages_section()
{
    ob_start();

    return ob_get_clean();
}
    public function get_author_pages_hidden_content()
    {
        ob_start();

        UI::checkbox( array
        (
            'id'          => 'user_archive_enabled',
            'value'       => self::get( 'user_archive_enabled', true ),
            'label'       => __( "Enable Author Pages for WordPress Users", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Toggle author pages for WordPress users. Enable to customize author pages or disable to configure redirection settings.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => $this->get_author_pages_for_users_content(),
                'on'  => $this->get_author_pages_for_users_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'guest_archive_enabled',
            'value'       => self::get( 'guest_archive_enabled', false ),
            'label'       => __( "Enable Author Pages for Guest Authors", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable to activate author pages for guest authors, with settings to customize their archives. Disable to turn off guest author pages.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_author_pages_for_guests_hidden_content(),
            ),
        ));

        return ob_get_clean();
    }
    public function get_author_pages_for_users_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo' => true,
            'text' => __( "Disabling author pages is a premium feature. Upgrade to unlock this functionality", 'molongui-authorship' ),
            'link' => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'userArchiveRedirection',
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::select( array
        (
            'id'          => 'user_archive_redirect',
            'selected'    => self::get( 'user_archive_redirect', get_option( 'page_on_front' ) ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'label'       => __( "Redirect To", 'molongui-authorship' ),
            'description' => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Select the page to redirect users when trying to access an author page.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'options'     => self::get_wp_pages(),// Plugin::has_pro() ? self::get_wp_pages() : array( 'value' => get_option( 'page_on_front' ), 'label' => get_the_title( get_option( 'page_on_front' ) ), 'disabled' => false ),
        ));

        UI::select( array
        (
            'id'          => 'user_archive_redirect_status',
            'selected'    => self::get( 'user_archive_redirect_status', '301' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'label'       => __( "Status Code", 'molongui-authorship' ),
            'description' => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "The HTML status code to return upon redirection.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'options'     => array
            (
                array
                (
                    'value'    => '301',
                    'label'    => __( "301 - Moved Permanently", 'molongui-authorship' ),
                    'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
                ),
                array
                (
                    'value'    => '307',
                    'label'    => __( "307 - Temporary Redirect", 'molongui-authorship' ),
                    'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
                ),
                array
                (
                    'value'    => '308',
                    'label'    => __( "308 - Permanent Redirect", 'molongui-authorship' ),
                    'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
                ),
                array
                (
                    'value'    => '403',
                    'label'    => __( "403 - Forbidden", 'molongui-authorship' ),
                    'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
                ),
                array
                (
                    'value'    => '404',
                    'label'    => __( "404 - Not Found", 'molongui-authorship' ),
                    'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
                ),
            ),
        ));

        UI::div_close( array
        (
            'id'    => '',
            'class' => '',
            'echo'  => true,
        ));

        return ob_get_clean();
    }
    public function get_author_pages_for_users_hidden_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo' => true,
            'text' => __( "Upgrade today to unlock access to these premium features", 'molongui-authorship' ),
            'link' => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'userArchives',
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::checkbox( array
        (
            'id'          => 'user_archive_include_pages',
            'value'       => self::get( 'user_archive_include_pages', false ),
            'label'       => __( "Include Pages In The Author Archive", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "By default, only posts authored by the displayed author are listed. Enable this option to list pages too.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'user_archive_include_cpts',
            'value'       => self::get( 'user_archive_include_cpts', false ),
            'label'       => __( "Include Custom Post Types In The Author Archive", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable this option to make the author page list Post Types where the Co-Authors feature has been enabled.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'user_archive_base_checkbox',
            'value'       => self::get( 'user_archive_base_checkbox', true ),
            'label'       => __( "Change Permalink Base For Author Pages", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s %s%s"
                                , '<p>'
                                , sprintf( __( "By default, author archive pages are accessible at: %s.", 'molongui-authorship' ), '<code>'.get_site_url( null, '/author/username/' ).'</code>' )
                                , sprintf( __( "Use this setting to easily change that <code>author</code> part to whatever you like.", 'molongui-authorship' ), '<code>'.get_site_url( null, '/author/' ).'</code>' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_user_archive_base_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'user_archive_slug',
            'value'       => self::get( 'user_archive_slug', false ),
            'label'       => __( "Use The Author Display Name Instead of The Username in Author Permalinks", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable this option to control author permalinks using the <code>display_name</code> instead of the <code>username</code>. User display name can be easily edited on the edit user screen. Avoiding the use of usernames in permalinks helps to improve the security of your site.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        if ( version_compare( get_bloginfo( 'version' ), '5.7', '>' ) )
        {
            UI::checkbox( array
            (
                'id'          => 'user_archive_noindex',
                'value'       => self::get( 'user_archive_noindex', false ),
                'label'       => __( "Prevent Author Pages To Be Indexed By Search Engines", 'molongui-authorship' ),
                'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
                'echo'        => true,
                'class'       => '',
                'help'        => sprintf( "%s%s%s"
                    , '<p>'
                    , __( "This option adds a <code>noindex</code> robots tag to your user pages. If you have a SEO plugin, you might want to use it for this instead.", 'molongui-authorship' )
                    , '</p>'
                ),
                'description' => '',
                'content'     => '',
            ));
        }

        UI::checkbox( array
        (
            'id'          => 'user_archive_template_checkbox',
            'value'       => self::get( 'user_archive_template_checkbox', false ),
            'label'       => __( "Change Template For Author Pages", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , sprintf( __( "By default, WordPress loads the <code>author.php</code>, <code>archive.php</code> or <code>index.php</code> template to display author archives. Overrule that providing an existing alternative template to use instead.", 'molongui-authorship' ), '<code>'.get_site_url( null, '/author/username/' ).'</code>' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_user_archive_template_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'user_archive_title_checkbox',
            'value'       => self::get( 'user_archive_title_checkbox', true ),
            'label'       => __( "Customize Page Title For Author Pages", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , sprintf( __( "This option allows you to prepend and append custom strings to the page title. Might not work for all themes.", 'molongui-authorship' ), '<code>'.get_site_url( null, '/author/username/' ).'</code>' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_user_archive_title_hidden_content(),
            ),
        ));

        UI::div_close( array
        (
            'id'    => '',
            'class' => '',
            'echo'  => true,
        ));

        return ob_get_clean();
    }
    public function get_user_archive_base_hidden_content()
    {
        ob_start();

        UI::input_text( array
        (
            'id'          => 'user_archive_base',
            'value'       => self::get( 'user_archive_base', 'author' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => '',
            'label'       => __( "Custom Permalink Base", 'molongui-authorship' ),
            'description' => '',
        ));

        return ob_get_clean();
    }
    public function get_user_archive_template_hidden_content()
    {
        ob_start();

        UI::input_text( array
        (
            'id'          => 'user_archive_template',
            'value'       => self::get( 'user_archive_template', '' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => '',
            'label'       => __( "Custom Template Path", 'molongui-authorship' ),
            'description' => '',
        ));

        return ob_get_clean();
    }
    public function get_user_archive_title_hidden_content()
    {
        ob_start();

        UI::input_text( array
        (
            'id'          => 'user_archive_title_prefix',
            'value'       => self::get( 'user_archive_title_prefix', '' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => '',
            'label'       => __( "Author Page Title Prefix", 'molongui-authorship' ),
            'description' => __( "Content to add inline before the page title.", 'molongui-authorship' ),
        ));

        UI::input_text( array
        (
            'id'          => 'user_archive_title_suffix',
            'value'       => self::get( 'user_archive_title_suffix', '' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => '',
            'label'       => __( "Author Page Title Suffix", 'molongui-authorship' ),
            'description' => __( "Content to add inline after the page title.", 'molongui-authorship' ),
        ));

        return ob_get_clean();
    }
    public function get_author_pages_for_guests_hidden_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo' => true,
            'text' => __( "Guest author pages are a premium feature. Upgrade to unlock this functionality", 'molongui-authorship' ),
            'link' => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'guestArchives',
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::checkbox( array
        (
            'id'          => 'guest_archive_include_pages',
            'value'       => self::get( 'guest_archive_include_pages', false ),
            'label'       => __( "Include Pages In The Author Archive", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "By default, only posts authored by the displayed author are listed. Enable this option to list pages too.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'guest_archive_include_cpts',
            'value'       => self::get( 'guest_archive_include_cpts', false ),
            'label'       => __( "Include Custom Post Types In The Author Archive", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable this option to make the author page list Post Types where the Co-Authors feature has been enabled.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'guest_archive_permalink_checkbox',
            'value'       => self::get( 'guest_archive_permalink_checkbox', true ),
            'label'       => __( "Add Extra Component to Permalink Structure", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s %s%s"
                                , '<p>'
                                , __( "Easily change the permalink structure for guest author archives pages by adding a custom path before the permalink base.", 'molongui-authorship' )
                                , sprintf( __( "Something like %s", 'molongui-authorship' ), '<code>' . get_site_url( null, '/' ) . 'guest/author/john-doe</code>' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_guest_archive_permalink_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'guest_archive_base_checkbox',
            'value'       => self::get( 'guest_archive_base_checkbox', true ),
            'label'       => __( "Change Permalink Base For Guest Author Pages", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s %s%s"
                                , '<p>'
                                , sprintf( __( "By default, author archive pages are accessible at: %s.", 'molongui-authorship' ), '<code>'.get_site_url( null, '/author/slug/' ).'</code>' )
                                , sprintf( __( "Use this setting to easily change that <code>author</code> part to whatever you like.", 'molongui-authorship' ), '<code>'.get_site_url( null, '/author/' ).'</code>' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_guest_archive_base_hidden_content(),
            ),
        ));

        if ( version_compare( get_bloginfo( 'version' ), '5.7', '>' ) )
        {
            UI::checkbox( array
            (
                'id'          => 'guest_archive_noindex',
                'value'       => self::get( 'guest_archive_noindex', false ),
                'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
                'echo'        => true,
                'class'       => '',
                'label'       => __( "Prevent Guest Author Pages To Be Indexed By Search Engines", 'molongui-authorship' ),
                'help'        => sprintf( "%s%s%s"
                                    , '<p>'
                                    , __( "This option adds a <code>noindex</code> robots tag to your guest author pages preventing search engines to index them. If you have a SEO plugin, you might want to use it for this instead.", 'molongui-authorship' )
                                    , '</p>'
                                 ),
                'description' => '',
                'content'     => '',
            ));
        }

        UI::checkbox( array
        (
            'id'          => 'guest_archive_template_checkbox',
            'value'       => self::get( 'guest_archive_template_checkbox', false ),
            'label'       => __( "Change Template For Guest Author Pages", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                , '<p>'
                , sprintf( __( "By default, WordPress loads the <code>author.php</code>, <code>archive.php</code> or <code>index.php</code> template to display author archives. Overrule that providing an existing alternative template to use instead.", 'molongui-authorship' ), '<code>'.get_site_url( null, '/author/username/' ).'</code>' )
                , '</p>'
            ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_guest_archive_template_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'guest_archive_title_checkbox',
            'value'       => self::get( 'guest_archive_title_checkbox', true ),
            'label'       => __( "Customize Page Title For Guest Author Pages", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , sprintf( __( "This option allows you to prepend and append custom strings to the page title. Might not work for all themes.", 'molongui-authorship' ), '<code>'.get_site_url( null, '/author/username/' ).'</code>' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_guest_archive_title_hidden_content(),
            ),
        ));

        UI::div_close( array
        (
            'id'    => '',
            'class' => '',
            'echo'  => true,
        ));

        return ob_get_clean();
    }
    public function get_guest_archive_permalink_hidden_content()
    {
        ob_start();

        UI::input_text( array
        (
            'id'          => 'guest_archive_permalink',
            'value'       => self::get( 'guest_archive_permalink', '' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => '',
            'label'       => __( "Additional Permastruct", 'molongui-authorship' ),
            'description' => __( "Make sure not to overlap any existing URL on your site.", 'molongui-authorship' ),
        ));

        return ob_get_clean();
    }
    public function get_guest_archive_base_hidden_content()
    {
        ob_start();

        UI::input_text( array
        (
            'id'          => 'guest_archive_base',
            'value'       => self::get( 'guest_archive_base', 'author' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => '',
            'label'       => __( "Custom Permalink Base", 'molongui-authorship' ),
            'description' => '',
        ));

        return ob_get_clean();
    }
    public function get_guest_archive_template_hidden_content()
    {
        ob_start();

        UI::input_text( array
        (
            'id'          => 'guest_archive_template',
            'value'       => self::get( 'guest_archive_template', '' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => __( "Setting not available in Free Version. <b>Upgrade</b> to Pro to customize your author pages.", 'molongui-authorship' ),
            'label'       => __( "Custom Template Path", 'molongui-authorship' ),
            'description' => '',
        ));

        return ob_get_clean();
    }
    public function get_guest_archive_title_hidden_content()
    {
        ob_start();

        UI::input_text( array
        (
            'id'          => 'guest_archive_title_prefix',
            'value'       => self::get( 'guest_archive_title_prefix', '' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => '',
            'label'       => __( "Guest Page Title Prefix", 'molongui-authorship' ),
            'description' => __( "Content to add inline before the page title.", 'molongui-authorship' ),
        ));

        UI::input_text( array
        (
            'id'          => 'guest_archive_title_suffix',
            'value'       => self::get( 'guest_archive_title_suffix', '' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'tooltip'     => '',
            'label'       => __( "Guest Page Title Suffix", 'molongui-authorship' ),
            'description' => __( "Content to add inline after the page title.", 'molongui-authorship' ),
        ));

        return ob_get_clean();
    }
    public function get_search_results_hidden_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo' => true,
            'text' => __( "Upgrade today to gain access to these options and enhance your visitors experience", 'molongui-authorship' ),
            'link' => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'enhancedSearch',
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::checkbox( array
        (
            'id'          => 'search_by_author_enabled',
            'value'       => self::get( 'search_by_author_enabled', false ),
            'label'       => __( "Allow Searching Content In Your Site By Author Name", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable this setting to enhance user experience by allowing visitors to search content by author name. By default, WordPress search only examines post content. When this setting is enabled, searching by an author's name will return content authored by that individual.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'tooltip'     => apply_filters( 'molongui_authorship/disable_premium_setting', true ) ? __( "Upgrade to Pro to allow your visitors to search content in your site by typing the author name in the search box.", 'molongui-authorship' ) : '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'guests_in_search_enabled',
            'value'       => self::get( 'guests_in_search_enabled', false ),
            'label'       => __( "Show Guest Auhtor Archives in Search Results", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, searching for a guest author's name will include their author archive page in the search results.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'tooltip'     => apply_filters( 'molongui_authorship/disable_premium_setting', true ) ? __( "Upgrade to Pro to increase the visibility of your guest authors by including their archive pages in search results.", 'molongui-authorship' ) : '',
            'content'     => '',
        ));

        UI::div_close( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
        ));

        return ob_get_clean();
    }
    public function get_author_data_encode_hidden_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo' => true,
            'text' => __( "Upgrade today to gain access to these options and protect sensitive information", 'molongui-authorship' ),
            'link' => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'dataEncode',
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::checkbox( array
        (
            'id'          => 'author_email_encoded',
            'value'       => self::get( 'author_email_encoded', false ),
            'label'       => __( "Encode e-mail addresses to prevent them getting spammed", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => /*sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, the author box will not appear for authors without a description.", 'molongui-authorship' )
                                , '</p>'
                             )*/'',
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'author_phone_encoded',
            'value'       => self::get( 'author_phone_encoded', false ),
            'label'       => __( "Encode phone numbers to prevent them getting annoying scam calls and texts", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => /*sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, the author box will not appear for authors without a description.", 'molongui-authorship' )
                                , '</p>'
                             )*/'',
            'description' => '',
            'content'     => '',
        ));

        UI::div_close( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
        ));

        return ob_get_clean();
    }
    public function get_social_profiles_hidden_content()
    {
        ob_start();

        ?>
        <div class="molongui-ui-checkbox-group__title" style="margin-bottom: 1em">
            <?php esc_html_e( "Select Social Profiles", 'molongui-authorship' ); ?>
            <?php UI::help_markup( sprintf( '%s%s%s',
                                    __( "There are a ton of social profiles. To avoid clutter, select those you want to enable. You can select as many as you wish. And you can filter displayed list by typing the name of the network you are looking for. And if you find one missing, you can request us to include it.", 'molongui-authorship' )
                                    , '<hr>'
                                    , '<a href="https://www.molongui.com/docs/molongui-authorship/author-box/social-networks/" target="_blank">' . __( "Complete List of Available Social Profiles →", 'molongui-authorship' ) . '</a>'
            ) ); ?>
        </div>
        <?php
        self::social_profile_selector();

        UI::checkbox( array
        (
            'id'          => 'social_profiles_nofollow',
            'value'       => self::get( 'social_profiles_nofollow', false ),
            'label'       => __( "Add <code>nofollow</code> attribute to social profile links", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "The <code>nofollow</code> attribute instructs search engines not to pass link equity (ranking value) to the linked social network profiles. Adding nofollow can be useful when you want to avoid diluting your website's SEO value or if the links are considered untrusted or external promotional content. Use this setting to maintain control over your link strategy and ensure search engines focus on prioritizing your internal or other critical links.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::banner( array
        (
            'echo'    => true,
            'margin'  => 0,
            'icon'    => 'trumphet',
            'text'    => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Want to enable disabled profiles?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Upgrade to Pro and activate them all instantly!", 'molongui-authorship' )
                            , '</div>'
                         ),
            'link'    => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'socialProfiles',
            'button'  => __( "Upgrade Now", 'molongui-authorship' ),
            'premium' => true,
        ));

        return ob_get_clean();
    }
    public function get_seo_settings_hidden_content()
    {
        ob_start();

        UI::checkbox( array
        (
            'id'          => 'schema_markup_enabled',
            'value'       => self::get( 'schema_markup_enabled', true ),
            'label'       => __( "Add schema.org structured data to plugin content", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Enable this setting to automatically add structured data (schema.org markup) to the content generated by the plugin. Structured data helps search engines understand your content more effectively, improving its relevance and visibility in search results. By including schema.org metadata, your content becomes eligible for rich snippets, which can enhance how your website appears in search results with additional features like star ratings, author information, or event details. This can significantly improve click-through rates and boost your SEO performance.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'html_meta_enabled',
            'value'       => self::get( 'html_meta_enabled', true ),
            'label'       => __( "Add <code>name='author'</code> meta tag", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s"
                                , '<p>'
                                , __( "The Author meta tag specifies the author of the content, providing a clear attribution signal for search engines and other platforms. Including this tag can enhance the discoverability of author-specific content, improve content credibility, and support features like author-based search results and content curation by third-party applications. It's a simple yet effective way to reinforce author metadata for better web presence.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "<b>Enabling this setting might not be necessary if you are already using a dedicated SEO or schema plugin that handles author metadata. Be sure to check your existing setup to avoid redundancy.</b>", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'opengraph_meta_enabled',
            'value'       => self::get( 'opengraph_meta_enabled', true ),
            'label'       => __( "Add Opengraph meta tags", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s"
                                , '<p>'
                                , __( "OpenGraph tags are essential for optimizing how your content appears when shared on social media platforms like Facebook, Twitter, LinkedIn, and others. These tags allow you to control elements such as the title, description, image, and URL that appear in link previews, ensuring your posts look professional and engaging. By enhancing the visual appeal and context of shared links, OpenGraph meta tags can boost click-through rates and improve the reach and performance of your content on social media.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "<b>Enabling this setting might not be necessary if you are already using a dedicated SEO or schema plugin that handles author metadata. Be sure to check your existing setup to avoid redundancy.</b>", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'facebook_meta_enabled',
            'value'       => self::get( 'facebook_meta_enabled', true ),
            'label'       => __( "Add <code>property='article:author'</code> meta tag for Facebook", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s"
                                , '<p>'
                                , __( "This tag helps associate your articles with the author's Facebook profile, providing a direct connection between the content and its creator. This can enhance the credibility and visibility of your posts on Facebook, as well as enable features like author attribution in shared articles. It also helps build trust and engagement by allowing readers to explore more content from the author directly.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "<b>Enabling this setting might not be necessary if you are already using a dedicated SEO or schema plugin that handles author metadata. Be sure to check your existing setup to avoid redundancy.</b>", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'twitter_meta_enabled',
            'value'       => self::get( 'twitter_meta_enabled', true ),
            'label'       => __( "Add <code>name='twitter:creator'</code> meta tag for X", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s"
                                , '<p>'
                                , __( "This tag associates your content with the author's handle in X (formerly Twitter), ensuring proper attribution when your posts are shared on the platform. By adding this tag, you enhance your content's visibility, build credibility, and encourage audience engagement with the author's profile. It's an effective way to promote the creator's presence on X and drive more interactions with your posts.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "<b>Enabling this setting might not be necessary if you are already using a dedicated SEO or schema plugin that handles author metadata. Be sure to check your existing setup to avoid redundancy.</b>", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::select( array
        (
            'id'          => 'html_meta_coauthors',
            'selected'    => self::get( 'html_meta_coauthors', 'many' ),
            'options'     => array
            (
                array
                (
                    'value'    => 'many',
                    'label'    => __( "Add one author meta tag for each co-author", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'aio',
                    'label'    => __( "Add one single author meta tag with co-authors information", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'main',
                    'label'    => __( "Add author meta tag for the primary author only", 'molongui-authorship' ),
                    'disabled' => false,
                ),
            ),
            'label'       => __( "Co-Authored Posts", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "On co-authored posts, choose whether to include grouped author information in a single author meta tag or add individual author meta tags for each co-author.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
        ));

        return ob_get_clean();
    }
    public function get_rest_api_integration_hidden_content()
    {
        ob_start();

        UI::upgrade( array
        (
            'echo' => true,
            'text' => __( "REST API integration is a PRO feature. Upgrade now to unlock and access these settings", 'molongui-authorship' ),
            'link' => MOLONGUI_AUTHORSHIP_WEB . '/?utm_source=WordPress&utm_campaign=liteplugin&utm_medium=settings&utm_content=' . 'restApi',
        ));

        UI::div_open( array
        (
            'id'      => '',
            'class'   => '',
            'echo'    => true,
            'premium' => true,
        ));

        UI::checkbox( array
        (
            'id'          => 'rest_api_add_guests_authors',
            'value'       => self::get( 'rest_api_add_guests_authors', false ),
            'label'       => __( "Make the Guest Author object available through the WordPress REST API", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "The Guest Author object, a custom post type designed to store guest author profile information, will be available for retrieval and interaction by <code>REST API</code> clients, allowing seamless integration with external applications and tools.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'tooltip'     => !Plugin::has_pro() ? __( "This setting is available in the Pro version. <a href='" . MOLONGUI_AUTHORSHIP_WEB . "' class='highlighted' style='cursor:pointer;' target='_self'>Upgrade now</a> to unlock this feature and more premium options!", 'molongui-authorship' ) : '',
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'rest_api_add_post_authors',
            'value'       => self::get( 'rest_api_add_post_authors', false ),
            'label'       => __( "Add co-authors information to the post object returned by the WordPress REST API", 'molongui-authorship' ),
            'disabled'    => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When active, an <code>authors</code> field will be added to the <code>WP_Post</code> object, providing details about all authors (including co-authors) associated with a post. This allows external applications and tools to access comprehensive author data directly from the API.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'tooltip'     => !Plugin::has_pro() ? __( "This setting is available in the Pro version. <a href='" . MOLONGUI_AUTHORSHIP_WEB . "' class='highlighted' style='cursor:pointer;' target='_self'>Upgrade now</a> to unlock this feature and more premium options!", 'molongui-authorship' ) : '',
            'description' => '',
            'content'     => '',
        ));
        UI::div_close( array
        (
            'id'    => '',
            'class' => '',
            'echo'  => true,
        ));

        return ob_get_clean();
    }
    public function get_compatibility_mode_hidden_content()
    {
        ob_start();

        UI::checkbox( array
        (
            'id'          => 'compatibility_mode_themes',
            'value'       => self::get( 'compatibility_mode_themes', true ),
            'label'       => __( "Enable Theme Compatibility", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s%s%s%s%s%s"
                                , '<p>'
                                , __( "Some themes require tailored functions to ensure full compatibility with the plugin's features. Enabling this option ensures that additional compatibility adjustments are applied for themes that might otherwise cause layout or functionality issues. It is recommended to keep this setting enabled unless you are certain your theme does not require it.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "If you experience issues with the information displayed on your post bylines or anything related to author information, make sure this setting is enabled. If it already is and the issue persists, please open a support ticket with us so we can assist.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "In case of doubt, it's recommended to leave this setting active for the best experience.", 'molongui-authorship' )
                                , '</p>'
                                , '<hr>'
                                , '<a href="https://www.molongui.com/docs/molongui-authorship/compatibility/" target="_blank">' . __( "Learn more →", 'molongui-authorship' ) . '</a>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'compatibility_mode_plugins',
            'value'       => self::get( 'compatibility_mode_plugins', true ),
            'label'       => __( "Enable Plugin Compatibility", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s%s%s%s%s%s"
                                , '<p>'
                                , __( "Some third-party plugins require tailored functions to ensure full compatibility with the plugin's features. Enabling this option ensures that adjustments are applied for plugins that might otherwise cause conflicts or unexpected behavior.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "If you experience issues with author-related information or functionality when using other plugins, make sure this setting is enabled. If it already is and the issue persists, please open a support ticket with us so we can assist.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , '<b>' . __( "When in doubt, it's recommended to keep this setting enabled.", 'molongui-authorship' ) . '</b>'
                                , '</p>'
                                , '<hr>'
                                , '<a href="https://www.molongui.com/docs/molongui-authorship/compatibility/" target="_blank">' . __( "Learn more →", 'molongui-authorship' ) . '</a>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => self::get_compatibility_plugins_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'compatibility_mode_browser',
            'value'       => self::get( 'compatibility_mode_browser', false ),
            'label'       => __( "Enable Old Browser Compatibility", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s"
                                , '<p>'
                                , __( "<b>Enable this setting only if you are experiencing issues!</b>", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "Enable this setting to ensure compatibility with older browsers that may not fully support modern web standards. This option helps your content display correctly for users on outdated browser versions, improving accessibility and user experience across a wider audience.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => self::get_compatibility_browser_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'object_cache_enabled',
            'value'       => self::get( 'object_cache_enabled', true ),
            'label'       => __( "Use WordPress Object Cache", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s%s%s%s%s%s%s"
                                , '<p>'
                                , __( "<b>Disable this setting only if you are experiencing issues!</b>", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "The WordPress Object Cache is used to save on trips to the database. Having this setting enabled can reduce query time up to 94%. So sit is really advised to have it ON.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "By default, the object cache is non-persistent. This means that data stored in the cache resides in memory only and only for the duration of the request. Cached data will not be stored persistently across page loads unless you install a persistent caching plugin.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , '<b>' . __( "When in doubt, it's recommended to keep this setting enabled.", 'molongui-authorship' ) . '</b>'
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        UI::banner( array
        (
            'echo'   => true,
            'margin' => 0,
            'icon'   => 'support',
            'text'   => sprintf( "%s%s%s%s%s%s"
                            , '<div>'
                            , __( "<b>Still facing issues after applying compatibility fixes?</b>", 'molongui-authorship' )
                            , '</div>'
                            , '<div>'
                            , __( "Open a support ticket, and our team will assist you promptly. We're here to help!", 'molongui-authorship' )
                            , '</div>'
                        ),
            'link'   => esc_url( admin_url( 'admin.php?page=molongui-authorship&tab=help#molongui-help-ticket-form' ) ),
            'target' => '_self',
            'button' => __( "Open Ticket", 'molongui-authorship' ),
        ));

        return ob_get_clean();
    }
    public function get_compatibility_plugins_hidden_content()
    {
        ob_start();

        UI::checkbox( array
        (
            'id'          => 'pll_translate_guests',
            'value'       => self::get( 'pll_translate_guests', true ),
            'label'       => __( "Enable Polylang support for guest author languages and translations", 'molongui-authorship' ),
            'echo'        => is_plugin_active( 'polylang/polylang.php' ),
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s"
                                , '<p>'
                                , __( "By default, Guest Author profiles can be translated the same way as posts, allowing you to create a separate version of a guest author profile for each language.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "However, if you prefer to have a single version of each guest author profile shared across all languages, you can disable this feature by turning off this option.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        return ob_get_clean();
    }
    public function get_compatibility_browser_hidden_content()
    {
        ob_start();

        UI::checkbox( array
        (
            'id'          => 'element_queries_enabled',
            'value'       => self::get( 'element_queries_enabled', false ),
            'label'       => __( "Use a JavaScript-based solution to make the author box responsive <code>Enable only if experiencing issues</code>", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s%s%s%s"
                                , '<p>'
                                , __( "<b>Enable this setting only if you are experiencing issues!</b>", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "Enable this setting to use a JavaScript-based solution for making the author box responsive. This ensures that the author box adapts to different screen sizes across all devices, including older browsers that lack support for modern CSS features.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "<b>Enabling this setting applies to all visitors, meaning users with modern browsers may experience slightly slower performance due to additional files being loaded. Consider this trade-off before enabling.</b>", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => self::get_element_queries_hidden_content(),
            ),
        ));

        return ob_get_clean();
    }
    public function get_element_queries_hidden_content()
    {
        ob_start();

        UI::checkbox( array
        (
            'id'          => 'element_queries_cdn_compatibility',
            'value'       => self::get( 'element_queries_cdn_compatibility', false ),
            'label'       => __( "Enable CDN compatibility to fix author box layout and make it display nicely", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s%s%s"
                                , '<p>'
                                , __( "<b>Enable this setting only if you are experiencing issues!</b>", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "Is your author box layout messed up while using a CDN? When serving stylesheet files through a CDN, the author box layout may appear broken. Enabling this setting should resolve the issue.", 'molongui-authorship' )
                                , '</p>'
                                , '<hr>'
                                , '<a href="https://www.molongui.com/docs/molongui-authorship/troubleshooting/the-author-box-layout-is-being-displayed-oddly/" target="_blank">' . __( "Learn more →", 'molongui-authorship' ) . '</a>'
                             ),
            'description' => '',
            'content'     => '',
        ));

        return ob_get_clean();
    }
    public function get_dashboard_settings_hidden_content()
    {
        ob_start();

        UI::checkbox( array
        (
            'id'          => 'dashboard_authors_menu',
            'value'       => self::get( 'dashboard_authors_menu', true ),
            'label'       => __( "Add an <code>Authors</code> menu to the WordPress Dashboard", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, an <code>Authors</code> menu item will appear in the left-hand menu of your WordPress Dashboard. Changes will take effect after refreshing the page.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_dashboard_authors_menu_hidden_content(),
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'dashboard_guest_authors_menu',
            'value'       => self::get( 'dashboard_guest_authors_menu', false ),
            'label'       => __( "Add a <code>Guest Authors</code> menu to the WordPress Dashboard", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, a <code>Guest Authors</code> menu item will appear in the left-hand menu of your WordPress Dashboard. Changes will take effect after refreshing the page.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => $this->get_dashboard_guest_authors_menu_hidden_content(),
            ),
        ));
        UI::checkbox( array
        (
            'id'          => 'dashboard_posts_submenu',
            'value'       => self::get( 'dashboard_posts_submenu', true ),
            'label'       => __( "Add an <code>Authors</code> quick link to the <code>Posts</code> menu", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, an <code>Authors</code> submenu item will be added to the <code>Posts</code> menu. Changes will take effect after refreshing the page.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => '',
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'dashboard_appearance_submenu',
            'value'       => self::get( 'dashboard_appearance_submenu', true ),
            'label'       => __( "Add an <code>Author Box</code> quick link to the <code>Appearance</code> menu", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, an <code>Author Box</code> submenu item will be added to the <code>Appearance</code> menu. Changes will take effect after refreshing the page.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => '',
            ),
        ));

        UI::checkbox( array
        (
            'id'          => 'dashboard_settings_submenu',
            'value'       => self::get( 'dashboard_settings_submenu', true ),
            'label'       => __( "Add <code>Author Box</code>, and <code>Authors</code> quick links to the <code>Settings</code> menu", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, <code>Author Box</code>, and <code>Authors</code> quick links submenu items will be added to the <code>Settings</code> menu. Changes will take effect after refreshing the page.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => '',
            ),
        ));

        UI::select( array
        (
            'id'          => 'dashboard_author_name_action',
            'selected'    => self::get( 'dashboard_author_name_action', 'filter' ),
            'options'     => array
            (
                array
                (
                    'value'    => 'filter',
                    'label'    => __( "the posts screen filtered by author", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'edit',
                    'label'    => __( "the author-edit screen", 'molongui-authorship' ),
                    'disabled' => false,
                ),
            ),
            'label'       => __( "Author Name Links To", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s"
                                , '<p>'
                                , __( "On admin screens listing posts, you have a column labeled <code>Authors</code>. This setting allows you to configure what action to take when clicking on the author name displayed on that column.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "You can choose between keeping WordPress default behavior, which is to show a list of posts authored by that author or get redirected to the author edit screen.", 'molongui-authorship' )
                                , '</p>'
                             ),
            'description' => '',
        ));

        return ob_get_clean();
    }
    public function get_dashboard_authors_menu_hidden_content()
    {
        ob_start();

        UI::select( array
        (
            'id'          => 'dashboard_authors_menu_position',
            'selected'    => self::get( 'dashboard_authors_menu_position', 5 ),
            'options'     => array
            (
                array
                (
                    'value'    => '0',
                    'label'    => __( "At the top of the menu", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '2',
                    'label'    => __( "Below the 'Dashboard' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '5',
                    'label'    => __( "Below the 'Posts' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '10',
                    'label'    => __( "Below the 'Media' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '20',
                    'label'    => __( "Below the 'Pages' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '25',
                    'label'    => __( "Below the 'Comments' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '60',
                    'label'    => __( "Below the 'Appearance' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '65',
                    'label'    => __( "Below the 'Plugins' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '70',
                    'label'    => __( "Below the 'Users' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '75',
                    'label'    => __( "Below the 'Tools' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '80',
                    'label'    => __( "Below the 'Settings' menu item", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => '9999',
                    'label'    => __( "At the bottom of the menu", 'molongui-authorship' ),
                    'disabled' => false,
                ),
            ),
            'label'       => __( "Menu Position", 'molongui-authorship' ),
            'echo'        => true,
            'disabled'    => false,
            'class'       => '',
            'description' => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Changes will take effect after refreshing the page.", 'molongui-authorship' )
                                , '</p>'
                             ),
        ));

        UI::select( array
        (
            'id'          => 'dashboard_authors_menu_icon',
            'selected'    => self::get( 'dashboard_authors_menu_icon', 'ellipsis' ),
            'options'     => array
            (
                array
                (
                    'value'    => 'edit',
                    'label'    => __( "Pencil", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'editor-textcolor',
                    'label'    => __( "Letter 'A' for Authors", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'groups',
                    'label'    => __( "People", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'businessperson',
                    'label'    => __( "Business Person", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'businessman',
                    'label'    => __( "Business Man", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'businesswoman',
                    'label'    => __( "Business Woman", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'id',
                    'label'    => __( "Card ID", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'id-alt',
                    'label'    => __( "Card ID (alt)", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'book',
                    'label'    => __( "Book", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'book-alt',
                    'label'    => __( "Book (alt)", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'microphone',
                    'label'    => __( "Microphone", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'yes',
                    'label'    => __( "Check", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'yes-alt',
                    'label'    => __( "Circled Check", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'ellipsis',
                    'label'    => __( "Ellipsis", 'molongui-authorship' ),
                    'disabled' => false,
                ),
            ),
            'label'       => __( "Menu Icon", 'molongui-authorship' ),
            'echo'        => true,
            'disabled'    => false,
            'class'       => '',
            'description' => sprintf( "%s%s%s"
                                , '<p>'
                                , __( "Changes will take effect after refreshing the page.", 'molongui-authorship' )
                                , '</p>'
                             ),
        ));

        return ob_get_clean();
    }
    public function get_dashboard_guest_authors_menu_hidden_content()
    {
        ob_start();

        UI::select( array
        (
            'id'          => 'dashboard_guest_authors_menu_location',
            'selected'    => self::get( 'dashboard_guest_authors_menu_location', 'top' ),
            'options'     => array
            (
                array
                (
                    'value'    => 'top',
                    'label'    => __( "New top level menu", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'users.php',
                    'label'    => __( "Under the Users menu", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'edit.php',
                    'label'    => __( "Under the Posts menu", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'edit.php?post_type=page',
                    'label'    => __( "Under the Pages menu", 'molongui-authorship' ),
                    'disabled' => false,
                ),
            ),
            'label'       => __( "Menu Location", 'molongui-authorship' ),
            'echo'        => true,
            'disabled'    => false,
            'class'       => '',
            'description' => /*sprintf( "%s%s%s"
                                , '<p>'
                                , __( "When enabled, the author box will not appear for authors without a description.", 'molongui-authorship' )
                                , '</p>'
                             )*/'',
        ));

        return ob_get_clean();
    }
    public function validate_freemium( $options, $current )
    {
        $post_types = array();
        if ( isset( $options['author_box_post_types'] ) )
        {
            $_post_types = explode( ",", $options['author_box_post_types'] );

            $post_types = array_filter( $_post_types, function( $item )
            {
                return in_array( $item, array( 'page', 'post' ) );
            });
        }
        $options['author_box_post_types'] = implode(',', $post_types );
        $post_types = array();
        if ( isset( $options['author_box_auto_display_post_types'] ) )
        {
            $_post_types = explode( ",", $options['author_box_auto_display_post_types'] );

            $post_types = array_filter( $_post_types, function( $item )
            {
                return in_array( $item, array( 'page', 'post' ) );
            });
        }
        $options['author_box_auto_display_post_types'] = implode(',', $post_types );
        $post_types = array();
        if ( isset( $options['guest_author_post_types'] ) )
        {
            $_post_types = explode( ",", $options['guest_author_post_types'] );

            $post_types = array_filter( $_post_types, function( $item )
            {
                return in_array( $item, array( 'page', 'post' ) );
            });
        }
        $options['guest_author_post_types'] = implode(',', $post_types );
        $post_types = array();
        if ( isset( $options['co_authors_post_types'] ) )
        {
            $_post_types = explode( ",", $options['co_authors_post_types'] );

            $post_types = array_filter( $_post_types, function( $item )
            {
                return in_array( $item, array( 'page', 'post' ) );
            });
        }
        $options['co_authors_post_types'] = implode(',', $post_types );
        $options['user_roles_enabled']                  = true;
        $options['user_roles']                          = "administrator,editor,author,contributor";
        $options['hide_others_posts']                   = '';
        $options['byline_prefix']                       = '';
        $options['byline_suffix']                       = '';
        $options['author_box_hide_on_category']         = '';
        $options['author_box_related_posts_count']      = 4;
        $options['author_box_related_posts_orderby']    = 'date';
        $options['author_box_related_posts_order']      = 'DESC';
        $options['author_box_related_posts_post_types'] = 'post';
        $options['user_archive_title_prefix']           = '';
        $options['user_archive_title__suffix']          = '';

        return $options;
    }
    public function validate( $options, $current )
    {
        if ( isset( $options['molongui_post_authors'] ) )
        {
            $options['default_post_author'] = $options['molongui_post_authors'];
            unset( $options['molongui_post_authors'] );
        }
        unset( $options['molongui_post_authors_nonce'] );
        if ( empty( $options['author_box_show_related_posts'] ) )
        {
            $options['author_box_show_related_posts'] = 0;
        }
        if ( isset( $options['co_authors_separator'] ) )
        {
            $options['co_authors_separator'] = str_replace( array( "*", "?", "/" ), "", trim( $options['co_authors_separator'] ) );
        }
        if ( isset( $options['co_authors_last_separator'] ) )
        {
            $options['co_authors_last_separator'] = str_replace( array( "*", "?", "/" ), "", trim( $options['co_authors_last_separator'] ) );
        }

        return $options;
    }
    public function clear_object_cache()
    {
        check_ajax_referer( 'authorship_clear_cache_nonce', 'nonce', true );
        Cache::clear( 'posts' );
        Cache::clear( 'users' );
        Cache::clear( 'guests' );
        echo json_encode( true );
        wp_die();
    }
    public function object_cache_status()
    {
        return self::is_enabled( 'cache' );
    }
    public function stylesheet( $file )
    {
        if ( file_exists( MOLONGUI_AUTHORSHIP_DIR . $this->stylesheet ) )
        {
            $file = MOLONGUI_AUTHORSHIP_FOLDER . '/' . $this->stylesheet;
        }

        return $file;
    }
    public function javascript( $file )
    {
        if ( file_exists( MOLONGUI_AUTHORSHIP_DIR . $this->javascript ) )
        {
            $file = MOLONGUI_AUTHORSHIP_FOLDER . '/' . $this->javascript;
        }

        return $file;
    }
    public function require_dependencies( $deps )
    {
        Assets::register_typeahead();

        $deps = array( 'jquery', 'molongui-typeahead', 'jquery-ui-sortable' );

        return array_unique( $deps );
    }
    public function enqueue_dependencies()
    {
    }
    public function javascript_params( $fw_params )
    {
        $ajax_suggest_link = add_query_arg( array
        (
            'action'    => 'social_profiles_ajax_suggest',
            'post_type' => rawurlencode( get_post_type() ),
        ), wp_nonce_url( 'admin-ajax.php', 'molongui-social-profile-search', 'molongui-social-profile-search-nonce' ) );
        $params = apply_filters( "authorship/options/params", array
        (
            'is_premium' => false,
            'debug_mode' => Debug::is_enabled(),
            100 => __( "Are you sure?", 'molongui-authorship' ),
            101 => __( "Disabling author box styles requires you to provide your own CSS rules. If you are not an skilled developer, we strongly advise you not to proceed.", 'molongui-authorship' ),
            102 => __( "Disabled", 'molongui-authorship' ),
            103 => __( "Remember to provide your own styles. To do so you can use the 'Additional CSS' setting available on the WordPress Customizer or a child theme.", 'molongui-authorship' ),
            200 => wp_create_nonce( 'authorship_clear_cache_nonce' ),
            201 => __( "Clear Cache", 'molongui-authorship' ),
            202 => __( "WordPress object cache is used to speed things up. Please confirm you want to go ahead and clear it.", 'molongui-authorship' ),
            203 => __( "Cancel", 'molongui-authorship' ),
            204 => __( "OK", 'molongui-authorship' ),
            205 => __( "Cleared!", 'molongui-authorship' ),
            206 => __( "Object cache used by Molongui Authorship has been cleared successfully", 'molongui-authorship' ),
            207 => __( "Error", 'molongui-authorship' ),
            208 => __( "Something went wrong and cache clean up failed. Please refresh this page and try again.", 'molongui-authorship' ),
            209 => __( "Something went wrong and couldn't connect to the server. Please, try again.", 'molongui-authorship' ),
            300 => wp_create_nonce( 'molongui_authorship_post_count_updater_nonce' ),
            301 => __( "Count Update", 'molongui-authorship' ),
            302 => __( "Forcing an update on post counters is a task that runs in the background and might take a (long) while to complete. Please confirm you want to go ahead.", 'molongui-authorship' ),
            303 => __( "Cancel", 'molongui-authorship' ),
            304 => __( "OK", 'molongui-authorship' ),
            305 => __( "Error", 'molongui-authorship' ),
            306 => __( "You have the WP_Cron disabled, so counters update failed. Please enable the WP_Cron in your wp-config.php file.", 'molongui-authorship' ),
            307 => __( "Something went wrong and counters update failed. Please refresh this page and try again.", 'molongui-authorship' ),
            308 => __( "Running...", 'molongui-authorship' ),
            309 => __( "The post count update is running in the background. You will receive a notice about the update status. You can close this window now.", 'molongui-authorship' ),
            310 => __( "Something went wrong and couldn't connect to the server. Please, try again.", 'molongui-authorship' ),
            311 => 'molongui_authorship_update_post_count',
            400 => __( "Doing it wrong!", 'molongui-authorship' ),
            401 => __( "You cannot disable both 'Authors' and 'Molongui' menus. One of them must be displayed so you have access to the plugin settings page.", 'molongui-authorship' ),
            'premium_profile_warning'     => esc_html__( "Selected profile is exclusive to premium users. Upgrade to Pro to enable it.", 'molongui-authorship' ),
            'confirm_profile_deletion'    => esc_html__( "Are you sure you want to disable this social profile?", 'molongui-authorship' ),
            'drag_to_reorder'             => esc_html__( "Drag this social profile to reorder", 'molongui-authorship' ),
            'delete_profile'              => esc_html__( "Remove", 'molongui-authorship' ),
            'move_profile_up'             => esc_html__( "Move up", 'molongui-authorship' ),
            'move_profile_down'           => esc_html__( "Move down", 'molongui-authorship' ),
            'ajax_suggest_link'           => $ajax_suggest_link,
            'social_search_min_length'    => apply_filters( 'molongui_authorship/social_search_min_length', 2 ),
            'social_search_results_limit' => apply_filters( 'molongui_authorship/social_search_results_limit', 60 ),
            'no_suggestions_found'        => wp_kses_post( apply_filters( 'molongui_authorship/social_search_no_results_message', __( "No matching social network found. <b><a href='https://www.molongui.com/help/support/' target='_blank' style='text-decoration:underline;color:inherit'>Let us know</a>, and we'll work on adding it for you!</b></b>", 'molongui-authorship' ) ) ),
        ));

        return $fw_params + $params;
    }
    public function suggest_social_profiles()
    {
        if ( !WP::verify_nonce( 'molongui-social-profile-search', 'molongui-social-profile-search-nonce', 'get' ) )
        {
            echo __( '<span class="ac_error" style="color:white"><strong>ERROR</strong>: Invalid nonce. Reload the page.</span>', 'molongui-authorship' );
            wp_die();
        }

        $search = isset( $_REQUEST['term'] ) ? sanitize_text_field( strtolower( $_REQUEST['term'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( !empty( $search ) )
        {
            $ignore = !empty( $_REQUEST['existing_profiles'] ) ? sanitize_text_field( $_REQUEST['existing_profiles'] ) : array();

            if ( !empty( $ignore ) )
            {
                $ignore = array_map( 'sanitize_text_field', explode( ',', $ignore ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            }

            $available_profiles = Social::get( 'all', array(), true );
            $matches = Helpers::array_find_partial_matches( array_keys( $available_profiles ), $search );
            $profiles = array_diff( $matches, $ignore );

            $suggestions = array();
            foreach ( $profiles as $profile )
            {
                $suggestions[] = array
                (
                    'id'       => $available_profiles[$profile]['id'],
                    'icon'     => $available_profiles[$profile]['icon'],
                    'name'     => esc_html( $available_profiles[$profile]['label'] ),
                    'disabled' => $available_profiles[$profile]['disabled'],
                    'premium'  => $available_profiles[$profile]['disabled'] ? '<small><code>' . __( "PRO", 'molongui-authorship' ) . '</code></small>' : '',
                );
            }

            wp_send_json( $suggestions );
        }
        else
        {
            wp_send_json( array() );
        }

        wp_die();
    }
    public function author_selector_params()
    {
        $params = Admin_Post::post_script_params();

        ?>
        <script type='text/javascript'>const molongui_authorship_edit_post_params = <?php echo json_encode( $params ); ?>;</script>
        <?php
    }
    public function filter_export( $options )
    {
        unset( $options[MOLONGUI_AUTHORSHIP_PREFIX.'_cache_posts'] );
        unset( $options[MOLONGUI_AUTHORSHIP_PREFIX.'_cache_users'] );
        unset( $options[MOLONGUI_AUTHORSHIP_PREFIX.'_cache_guests'] );

        return $options;
    }
    public function keep_db_19_keys( $options, $current )
    {
        $needed = array
        (
            'author_box_meta_color'              => 'meta_text_color',
            'author_box_meta_at'                 => 'at',
            'author_box_bio_source'              => 'bio_field',
            'author_box_related_show'            => 'show_related',
            'author_box_related_layout'          => 'related_layout',
            'author_box_related_none'            => 'no_related_posts',
            'author_box_related_font_size'       => 'related_text_size',
            'author_box_related_font_style'      => 'related_text_style',
            'author_box_layout'                  => 'box_layout',
            'author_box_profile_valign'          => 'profile_valign',
            'author_box_bottom_background_color' => 'bottom_background_color',
            'author_box_bottom_border_style'     => 'bottom_border_style',
            'author_box_bottom_border_width'     => 'bottom_border_width',
            'author_box_bottom_border_color'     => 'bottom_border_color',
        );
        foreach ( $needed as $new_key => $old_key )
        {
            if ( isset( $options[$new_key] ) )
            {
                $options[$old_key] = $options[$new_key];
            }
            elseif ( isset( $current[$new_key] ) )
            {
                $options[$old_key] = $current[$new_key];
            }
        }

        return $options;
    }
    public function add_image_sizes()
    {
        if ( apply_filters( 'authorship/add_image_size/skip', false ) )
        {
            return;
        }
        add_theme_support( 'post-thumbnails' );
        add_image_size( 'authorship-box-avatar', 150, 150, true );
        add_image_size( 'authorship-box-related', 70, 70, true );
        $options = self::get();
        if ( ( !empty( $options['author_box_avatar_width'] ) and $options['author_box_avatar_width'] != 150 ) or ( !empty( $options['author_box_avatar_height'] ) and $options['author_box_avatar_height'] != 150 ) )
        {
            add_image_size( 'authorship-custom-avatar', $options['author_box_avatar_width'], $options['author_box_avatar_height'], true );
        }
        do_action( 'authorship/add_image_size' );
    }
    public static function url( $tab = '' )
    {
        $url = 'admin.php?page=molongui-authorship';
        $tab = empty( $tab ) ? '' : '&tab='.$tab;

        return admin_url( $url.$tab );
    }
    public static function is_guest_author_enabled()
    {
        return !empty( self::get( 'guest_author_enabled', false ) );
    }
    public static function is_guest_author_enabled_on_post_type( $post_type = null )
    {
        if ( self::is_guest_author_enabled() )
        {
            if ( is_null( $post_type ) )
            {
                $post_type = Post::get_post_type();

                if ( empty( $post_type ) )
                {
                    return false;
                }
            }

            $post_types_enabled = explode( ',', self::get( 'guest_author_post_types', "" ) );

            if ( in_array( $post_type, $post_types_enabled ) )
            {
                return true;
            }
        }

        return false;
    }
    public static function is_co_authors_enabled()
    {
        return !empty( self::get( 'co_authors_enabled', false ) );
    }
    public static function is_co_authors_enabled_on_post_type( $post_type = null )
    {
        if ( self::is_co_authors_enabled() )
        {
            if ( is_null( $post_type ) )
            {
                $post_type = Post::get_post_type();

                if ( empty( $post_type ) )
                {
                    return false;
                }
            }

            $post_types_enabled = explode( ',', self::get( 'co_authors_post_types', "" ) );

            if ( in_array( $post_type, $post_types_enabled ) )
            {
                return true;
            }
        }

        return false;
    }
    public static function enabled_post_types( $feature = 'all', $query = 'ids' )
    {
        $guest_author_post_types = $co_authors_post_types = array();

        if ( self::is_guest_author_enabled() )
        {
            $guest_author_post_types = explode( ',', self::get( 'guest_author_post_types', "" ) );
        }

        if ( self::is_co_authors_enabled() )
        {
            $co_authors_post_types = explode( ',', self::get( 'co_authors_post_types', "" ) );
        }

        switch ( $feature )
        {
            case 'guest-author':
                $post_types = $guest_author_post_types;
                break;

            case 'co-authors':
                $post_types = $co_authors_post_types;
                break;

            default:
                $post_types = array_unique( array_merge( $guest_author_post_types, $co_authors_post_types ) );
                break;
        }

        if ( 'ids' === $query )
        {
            return $post_types;
        }

        $existing_post_types = Post::get_post_types( 'all', 'objects', false );
        $_post_types         = array();

        foreach ( $post_types as $post_type )
        {
            if ( !empty( $existing_post_types[$post_type] ) )
            {
                $_post_types[$post_type] = array
                (
                    'id'       => $existing_post_types[$post_type]->name,
                    'label'    => $existing_post_types[$post_type]->labels->name,
                    'singular' => $existing_post_types[$post_type]->labels->singular_name,
                );
            }
        }

        return $_post_types;
    }
    public static function enabled_screens()
    {
        $screens = array();
        $post_types = self::enabled_post_types();

        if ( !empty( $post_types ) )
        {
            foreach ( $post_types as $post_type )
            {
                $screens[] = $post_type;
                $screens[] = 'edit-'.$post_type;
            }
        }

        return $screens;
    }
    public static function enabled_user_roles()
    {
        $user_roles = array( 'administrator', 'editor', 'author', 'contributor' );

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @deprecated 5.0.8
         */
        if ( apply_filters( 'molongui_authorship/apply_filters_deprecated', true ) )
        {
            $user_roles = apply_filters_deprecated( 'authorship/user/roles', array( $user_roles ), '5.0.8' );
        }

        return $user_roles;
    }
    public static function is_enabled( $feature = null )
    {
        if ( empty( $feature ) )
        {
            return false;
        }

        $options  = self::get();
        $features = array
        (
            'author-box'    => 'author_box_enabled',
            'co-authors'    => 'co_authors_enabled',
            'guest-author'  => 'guest_author_enabled',
            'local-avatar'  => 'local_avatar_enabled',
            'user-profile'  => 'user_extra_data_enabled',
            'author-search' => 'search_by_author_enabled',
            'guest-search'  => 'guests_in_search_enabled',
            'cache'         => 'object_cache_enabled',
            'microdata'     => 'schema_markup_enabled',
            'theme-compat'  => 'compatibility_mode_themes',
            'plugin-compat' => 'compatibility_mode_plugins',
            'author-in-api' => 'rest_api_add_post_authors',
            'guest-in-api'  => 'rest_api_add_guests_authors',
        );

        return !empty( $options[$features[$feature]] );
    }
    public static function display_contributors_ad()
    {
        ?>
            <div>
                <img src="https://ps.w.org/molongui-post-contributors/assets/banner-772x250.png">
                <p><?php printf( __( "Need to add custom attributions to your posts? The %sMolongui Post Contributors%s plugin allows you to add contributors to your posts and display them towards the post author. Reviewers, fact-checkers, editors, photographers, whatever you need to acknowledge.", 'molongui-authorship' ), '<strong>', '</strong>' ); ?></p>
                <p><?php _e( "Install now to acknowledge every contribution and take your articles to the next level.", 'molongui-authorship' ); ?></p>
                <p><?php printf( __( "It's %sfree%s!", 'molongui-authorship' ), '<strong>', '</strong>' ); ?></p>
                <a class="button button-secondary" href="<?php echo wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=molongui-post-contributors' ), 'install-plugin_molongui-post-contributors' ); ?>"><?php _e( "Install Now", 'molongui-authorship' ); ?></a>
            </div>
        <?php
    }
    public static function get_post_type_options()
    {
        $options    = array();
        $post_types = Post::get_post_types( 'all', 'objects', false );

        foreach( $post_types as $post_type )
        {
            $options[] = array
            (
                'id'       => $post_type->name, // Backwards compat. Remove in the future.
                'value'    => $post_type->name,
                'label'    => $post_type->labels->name,
                'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', !in_array( $post_type->name, array( 'post', 'page' ) ) ),
            );
        }

        return $options;
    }
    public static function get_post_category_options()
    {
        $options    = array();
        $categories = get_categories( array
        (
            'hide_empty' => false,
        ));

        foreach( $categories as $category )
        {
            $options[] = array
            (
                'value'    => $category->slug ,
                'label'    => $category->name,
                'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            );
        }

        return $options;
    }
    public static function get_user_role_options()
    {
        $options = array();
        foreach ( get_editable_roles() as $role_key => $role_data )
        {
            $options[$role_key] = array
            (
                'id'       => esc_attr( $role_key ), // Backwards compatibility. todo: Replace 'id' usage with 'value'
                'value'    => esc_attr( $role_key ),
                'label'    => esc_html( translate_user_role( $role_data['name'] ) ),
                'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
                'premium'  => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
            );
        }
        $options['molongui_no_role'] = array
        (
                'id'       => 'molongui_no_role',
                'value'    => 'molongui_no_role',
                'label'    => __( "&mdash; No role &mdash;", 'molongui-authorship' ),//__( '&mdash; No role for this site &mdash;' ),
                'disabled' => apply_filters( 'molongui_authorship/disable_premium_setting', true ),
        );

        return $options;
    }
    public static function get_post_types_with_author_box( $display = 'all', $type = 'all', $select = false )
    {
        $post_types = array();

        switch ( $display )
        {
            case 'auto':
                $post_types = !empty( Settings::get( 'author_box_auto_display_post_types', '' ) )
                    ? array_map( 'trim', explode( ',', Settings::get( 'author_box_auto_display_post_types', '' ) ) )
                    : array();
                break;

            case 'manual':
                $post_types = !empty( Settings::get( 'author_box_post_types', '' ) )
                    ? array_map( 'trim', explode( ',', Settings::get( 'author_box_post_types', '' ) ) )
                    : array();
                break;

            case 'all':
                if ( !Settings::get( 'author_box_auto_display_override', true ) )
                {
                    $auto_post_types = !empty( Settings::get( 'author_box_auto_display_post_types', '' ) )
                        ? array_map( 'trim', explode( ',', Settings::get( 'author_box_auto_display_post_types', '' ) ) )
                        : array();
                    $post_types = array_merge( $post_types, $auto_post_types );
                }

                $manual_post_types = !empty( Settings::get( 'author_box_post_types', '' ) )
                    ? array_map( 'trim', explode( ',', Settings::get( 'author_box_post_types', '' ) ) )
                    : array();
                $post_types = array_unique( array_merge( $post_types, $manual_post_types ) );
                break;
        }

        if ( empty( $post_types ) )
        {
            return array();
        }

        $array = $opts = array();

        foreach ( Post::get_post_types( $type, 'objects', false ) as $post_type_name => $post_type_object )
        {
            if ( in_array( $post_type_name, $post_types ) )
            {
                $array[] = $post_type_name;
                $opts[]  = array
                (
                    'id'       => $post_type_name,
                    'label'    => $post_type_object->labels->name,
                    'singular' => $post_type_object->labels->singular_name
                );
            }
        }

        return ( $select ? $opts : $array );
    }
    public static function get_wp_pages()
    {
        $args = array
        (
            'child_of'    => 0,
            'sort_order'  => 'ASC',
            'sort_column' => 'post_title',
            'authors'     => '',
            'parent'      => -1,
            'number'      => 0,
            'offset'      => 0,
            'post_type'   => 'page',
            'post_status' => 'publish',
        );
        $wp_pages = get_pages( $args );

        $options = array();

        foreach ( $wp_pages as $wp_page )
        {
            $options[] = array
            (
                'value'    => $wp_page->ID,
                'label'    => $wp_page->post_title,
                'disabled' => false,
            );
        }

        return $options;
    }
    public static function social_profile_selector()
    {
        include MOLONGUI_AUTHORSHIP_DIR . 'views/admin/html-social-profile-selector.php';
    }

} // class
Settings::instance();
