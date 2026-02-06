<?php
/*!
 * Provides the functionality for managing the Author Box Editor in the admin area.
 *
 * This file is responsible for adding the menu option, rendering the customization screen, saving and validating
 * settings, and previewing the Author Box.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/admin
 * @since      5.0.0
 */

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Author;
use Molongui\Authorship\Author_Box;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Author_Box_Editor
{
    static  $javascript     = '/assets/js/editor.3189.min.js';
    static  $stylesheet     = '';
    private $stylesheet_ltr = '/assets/css/editor.4e0e.min.css';
    private $stylesheet_rtl = '/assets/css/editor-rtl.1979.min.css';
    private $screen_id;
    use Singleton;
    public function __construct()
    {
        $this->screen_id = Settings::get( 'dashboard_authors_menu' ) ? 'authors_page_author-box-editor' : 'admin_page_author-box-editor';
        if ( Settings::is_enabled( 'author-box' ) )
        {
            $this->set_assets();
            add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
            add_filter( 'authorship/editor_script_params', array( $this, 'editor_script_params' ) );
            add_action( "admin_print_footer_scripts-" . $this->screen_id, array( $this, 'author_selector_params' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'register_styles' ) );
            add_filter( "authorship/editor_extra_styles", array( $this, 'extra_styles' ) );
            add_action( 'admin_menu', array( $this, 'add_authors_menu_item' ), PHP_INT_MAX );
            add_action( 'admin_menu', array( $this, 'add_theme_menu_item' ) );
            add_action( 'admin_menu', array( $this, 'add_settings_menu_item' ), PHP_INT_MAX );
            add_action( 'admin_print_footer_scripts', array( $this, 'add_style_element' ), PHP_INT_MAX );
            add_action( 'wp_ajax_authorship_box_preview_action', array( $this, 'preview_action' ) );
            add_filter( 'molongui_authorship/author_box_profile_markup', array( $this, 'preview_profile_layout' ), 10, 3 );
            add_filter( 'molongui_authorship/author_box_related_markup', array( $this, 'preview_related_layout' ), 10, 3 );
            add_action( 'wp_ajax_authorship_save_editor_options', array( $this, 'save' ) );
            add_filter( 'authorship/validate_editor_options', array( $this, 'validate_spacing_options' ), 10, 2 );
            add_filter( 'authorship/validate_editor_options', array( $this, 'validate_premium_options' ), 10, 2 );
            add_filter( 'authorship/get_options', array( $this, 'validate_saved_options' ), 10, 1 );
        }
    }
    public function get_screen_id()
    {
        return $this->screen_id;
    }
    public function set_assets()
    {
        self::$stylesheet = MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? $this->stylesheet_rtl : $this->stylesheet_ltr );
        self::$stylesheet = apply_filters( 'authorship/editor/styles', self::$stylesheet );
        self::$javascript = MOLONGUI_AUTHORSHIP_FOLDER . self::$javascript;
        self::$javascript = apply_filters( 'authorship/editor/script', self::$javascript );
    }
    public function register_scripts()
    {
        Assets::register_typeahead();
        Assets::register_script( self::$javascript, 'editor' );
    }
    public static function enqueue_scripts()
    {
        Assets::enqueue_typeahead();
        Assets::enqueue_script( self::$javascript, 'editor', true );
    }
    public function editor_script_params()
    {
        $params = array
        (
        );
        return apply_filters( 'authorship/editor/script_params', $params );
    }
    public function author_selector_params()
    {
        $params = Admin_Post::post_script_params();

        ?>
        <script type='text/javascript'>const molongui_authorship_edit_post_params = <?php echo json_encode( $params ); ?>;</script>
        <?php
    }
    public function register_styles()
    {
        $deps = array();

        Assets::register_style( self::$stylesheet, 'editor', $deps );
    }
    public static function enqueue_styles()
    {
        Assets::enqueue_style( self::$stylesheet, 'editor', true );
    }
    public function extra_styles()
    {
        $css = '';
        $css .= '';
        return apply_filters( 'authorship/editor/extra_styles', $css );
    }
    public function add_authors_menu_item()
    {
        if ( Settings::get( 'author_box_enabled', true ) )
        {
            add_submenu_page
            (
                'authors',
                _x( "Author Box Editor", "Authors submenu title", 'molongui-authorship' ),
                _x( "Author Box Editor", "Authors submenu title", 'molongui-authorship' ),
                'manage_options',
                'author-box-editor',
                array( $this, 'render' ),
                2
            );
        }
    }
    public function add_theme_menu_item()
    {
        $display = Settings::get( 'dashboard_appearance_submenu', true );
        if ( $display and Settings::is_enabled( 'author-box' ) )
        {
            add_theme_page
            (
                '',
                _x( "Author Box", "Theme submenu title", 'molongui-authorship' ),
                'manage_options',
                self::url(),
                '',
                5
            );
        }
    }
    public function add_settings_menu_item()
    {
        $display = Settings::get( 'dashboard_settings_submenu', true );
        if ( $display and Settings::is_enabled( 'author-box' ) )
        {
            add_options_page
            (
                '',
                _x( "Author Box", "Settings submenu title", 'molongui-authorship' ),
                'manage_options',
                self::url(),
                '',
                4 // Position: Added below the "Reading" core item
            );
        }
    }
    public function render()
    {
        include MOLONGUI_AUTHORSHIP_DIR . 'views/admin/html-page-editor.php';
    }
    public function add_style_element()
    {
        ?>
        <div id="m-editor-live-preview__changes"></div>
        <?php
    }
    public function preview_action()
    {
        check_ajax_referer( 'authorship_box_editor_nonce', 'nonce', true );

        add_filter( 'molongui_authorship/is_author_box_editor', '__return_true' );

        $pick    = sanitize_text_field( $_POST['author'] );
        $options = $_POST['options'];
        $profile = array();
        $saved   = Settings::get();
        $options = array_merge( $saved, $options );
        add_filter( 'authorship/get_options', function() use ( $options )
        {
            return $options;
        });

        if ( empty( $pick ) )
        {
            if ( !empty( $options['author_box_bio_source'] ) and 'short' === $options['author_box_bio_source'] )
            {
                add_filter( 'molongui_authorship/author/dummy_defaults', function( $defaults )
                {
                    $defaults['meta']['description'] = $defaults['meta']['short_description'];
                    return $defaults;
                } );
            }
            $profile['dummy-0'] = new Author( 0, 'dummy' );
        }
        else
        {
            $_pick          = explode( '-', $pick );
            $profile[$pick] = new Author( $_pick[1], $_pick[0] );
        }

        $markup = Author_Box::markup( $profile, $options );

        remove_filter( 'molongui_authorship/is_author_box_editor', '__return_true' );
        echo $markup;
        wp_die();
    }
    public function preview_profile_layout( $output, $options, $profile )
    {
        if ( !self::is_editor() )
        {
            return $output;
        }

        $add_microdata = false;

        ob_start();
        switch ( substr( $options['author_box_profile_layout'], 7 ) )
        {
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
                include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/profile/html-layout-1.php';
                break;

            case '7':
            case '8': ?>

                <div class="m-a-box-content-top"></div><!-- End of .m-a-box-content-top -->

                <div class="m-a-box-content-middle">
                    <!-- Author picture -->
                    <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-avatar.php'; ?>

                    <!-- Author data -->
                    <div class="m-a-box-item m-a-box-data">

                        <!-- Author name -->
                        <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-name.php'; ?>

                        <!-- Author metadata -->
                        <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-meta.php'; ?>

                        <!-- Author bio -->
                        <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-bio.php'; ?>

                        <?php if ( $options['author_box_layout'] == 'slim' and !empty( $options['author_box_show_related_posts'] ) ) : ?>

                            <!-- Author related posts -->
                            <div class="m-a-box-related" data-related-layout="<?php echo $options['author_box_related_layout']; ?>">
                                <div class="m-a-box-item m-a-box-related-entries" <?php echo ( $options['author_box_layout'] == 'slim' ? 'style="display: none;"' : '' ); ?>>
                                    <ul>
                                        <?php
                                        if ( !empty( $profile->has_posts() ) )
                                        {
                                            /*!
                                             * FILTER HOOK
                                             * Allows filtering the related posts markup before it is generated.
                                             *
                                             * @param string $related_layout Default related posts layout markup. Defaults to null.
                                             * @param array  $options        Plugin settings.
                                             * @param array  $profile        Author data.
                                             * @since 5.0.0
                                             */
                                            $related_layout = apply_filters( 'molongui_authorship/author_box_related_markup', null, $options, $profile );

                                            if ( is_null( $related_layout ) )
                                            {
                                                $file = MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/related/html-'.$options['author_box_related_layout'].'.php';

                                                if ( !file_exists( $file ) )
                                                {
                                                    $file = MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/related/html-layout-1.php';
                                                }

                                                ob_start();
                                                include $file;
                                                $related_layout = ob_get_clean();
                                            }

                                            echo $related_layout;
                                        }
                                        else
                                        {
                                            echo ' <span class="m-a-box-string-no-related-posts">'. ( $options['author_box_related_none'] ? $options['author_box_related_none'] : __( "This author does not have any more posts.", 'molongui-authorship' ) ).'</span>';
                                        }
                                        ?>
                                    </ul>
                                </div><!-- End of .m-a-box-related-entries -->
                            </div><!-- End of .m-a-box-related -->
                        <?php endif; ?>
                    </div><!-- End of .m-a-box-data -->
                </div><!-- End of .m-a-box-content-middle -->

                <div class="m-a-box-content-bottom">
                    <!-- Author social -->
                    <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-socialmedia.php'; ?>
                </div><!-- End of .m-a-box-content-bottom -->

                <?php break;
        }
        $output = ob_get_clean();

        return $output;
    }
    public function preview_related_layout( $output, $options, $profile )
    {
        if ( !self::is_editor() )
        {
            return $output;
        }

        $add_microdata = false;

        ob_start();
        switch ( substr( $options['author_box_related_layout'], 7 ) )
        {
            case '1':
            case '2':

                include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/related/html-'.$options['author_box_related_layout'].'.php';

                break;

            case '3':

                foreach( $profile->get_posts( array( 'post_type' => 'related' ) ) as $related_id )
                {
                    ?>
                    <li>
                        <div class="m-a-box-related-entry" itemscope itemtype="http://schema.org/CreativeWork">

                            <div class="molongui-display-none" itemprop="author" itemscope itemtype="http://schema.org/Person">
                                <div itemprop="name"><?php echo $profile->get_display_name(); ?></div>
                                <div itemprop="url"><?php echo esc_url( $profile->get_archive_url() ); ?></div>
                            </div>

                            <!-- Related entry thumb -->
                            <div class="m-a-box-related-entry-thumb">
                                <?php if ( has_post_thumbnail( $related->ID ) ) : ?>
                                    <a href="<?php echo get_permalink( $related->ID ); ?>">
                                        <?php echo get_the_post_thumbnail( $related->ID, 'thumbnail', $attr = array( 'itemprop' => 'thumbnailUrl' ) ) ?>
                                    </a>
                                <?php else : ?>
                                    <img src="<?php echo MOLONGUI_AUTHORSHIP_URL.'assets/img/related_placeholder.svg'; ?>" width="<?php echo get_option( 'thumbnail_size_w' ).'px'; ?>">
                                <?php endif; ?>
                            </div>

                            <!-- Related entry date -->
                            <div class="m-a-box-related-entry-date" itemprop="datePublished">
                                <?php echo get_the_date( '', $related->ID ); ?>
                            </div>

                            <!-- Related entry title -->
                            <div class="m-a-box-related-entry-title">
                                <a class="molongui-remove-underline" itemprop="url" href="<?php echo get_permalink( $related->ID ); ?>">
                                    <span itemprop="headline"><?php echo $related->post_title; ?></span>
                                </a>
                            </div>

                        </div>
                    </li>
                    <?php
                }

                break;
        }
        $output = ob_get_clean();

        return $output;
    }
    public function get_dummy_data( $options )
    {
        $author = new Author( 0, 'dummy' );
        $dummy  = $author->get_data();
        if ( !empty( $options['author_box_bio_source'] ) and 'short' === $options['author_box_bio_source'] )
        {
            $dummy['description'] = $dummy['short_bio'];
        }
        if ( !empty( $options['author_box_avatar_source'] ) )
        {
            switch ( $options['author_box_avatar_source'] )
            {
                case 'gravatar':
                    $dummy['avatar'] = $author->get_gravatar( array( 'width' => $options['author_box_avatar_width'], 'height' => $options['author_box_avatar_height'] ), $options );
                    break;

                case 'acronym':
                    $dummy['avatar'] = $author->get_acronym( array( 'width' => $options['author_box_avatar_width'], 'height' => $options['author_box_avatar_height'] ), $options );
                    break;
            }
        }

        return $dummy;
    }
    public function save()
    {
        if ( !isset( $_POST['nonce'] ) )
        {
            return;
        }

        if ( !wp_verify_nonce( $_POST['nonce'], 'authorship_box_editor_nonce' ) )
        {
            return;
        }
        $options = wp_unslash( $_POST['data'] );

        if ( !empty( $options ) and is_array( $options ) )
        {
            $options['plugin_version'] = MOLONGUI_AUTHORSHIP_VERSION;
            $current = (array) get_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', array() );
            $options = array_merge( $current, $options );
            $options = apply_filters( 'authorship/validate_editor_options', $options, $current );
            update_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', $options, true );

            $old = $current;
            do_action( 'authorship/editor_options', $options, $old );
        }
        wp_die();
    }
    public function validate_spacing_options( $options, $current = null )
    {
        $size = array
        (
            'author_box_margin_top',
            'author_box_margin_right',
            'author_box_margin_bottom',
            'author_box_margin_left',
            'author_box_padding_top',
            'author_box_padding_right',
            'author_box_padding_bottom',
            'author_box_padding_left',
            'author_box_border_top',
            'author_box_border_right',
            'author_box_border_bottom',
            'author_box_border_left',
        );
        foreach ( $size as $key )
        {
            if ( empty( $options[$key] ) or self::input_has_units( $options[$key] ) )
            {
                continue;
            }
            $options[$key] = $options[$key] . 'px';
        }

        return $options;
    }
    public function validate_premium_options( $options, $current = array() )
    {
        $defaults = Settings::get_defaults();
        $premium  = array
        (
            'author_box_bio_source' => array( 'full' ),
            'author_box_related_layout'      => array( 'layout-1', 'layout-2' ),
            'author_box_related_orderby'     => array( 'date' ),
            'author_box_related_posts_count' => array( 4 ),
            'author_box_profile_layout' => array( 'layout-1' ),
        );
        foreach ( $premium as $key => $accepted )
        {
            if ( empty( $options[$key] ) )
            {
                continue;
            }

            if ( !in_array( $options[$key], $accepted ) )
            {
                if ( isset( $defaults[$key] ) )
                {
                    $options[$key] = $defaults[$key];
                }
                else
                {
                    $options[$key] = $accepted[0];
                }
            }
        }

        return $options;
    }
    public function validate_saved_options( $options )
    {
        if ( self::is_editor() )
        {
            return $options;
        }

        return $this->validate_premium_options( $options, $options );
    }
    public static function url()
    {
        return admin_url( 'admin.php?page=author-box-editor' );
    }
    public static function is_editor()
    {
        return apply_filters( 'molongui_authorship/is_author_box_editor', defined( 'MOLONGUI_AUTHORSHIP_IS_EDITOR' ) and MOLONGUI_AUTHORSHIP_IS_EDITOR );
    }
    public static function render_heading( $label )
    {
        ?>
        <div class="m-editor-heading">
            <span class="m-editor-heading__label"><?php echo $label; ?></span>
        </div>
        <?php
    }
    public static function render_input( $name, $label, $args = array() )
    {
        $type        = !empty( $args['type'] ) ? $args['type'] : 'text';
        $default     = isset( $args['default'] ) ? $args['default'] : '';
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
        $disabled    = !empty( $args['disabled'] ) ? $args['disabled'] : '';
        $min         = isset( $args['min'] ) ? $args['min'] : null;
        $max         = !empty( $args['max'] ) ? $args['max'] : '';
        $step        = !empty( $args['step'] ) ? $args['step'] : '';
        $width       = !empty( $args['width'] ) ? $args['width'] : '';
        $info_title  = !empty( $args['info-title'] ) ? 'data-info-title="'.$args['info-title'].'"' : '';
        $info_desc   = !empty( $args['info-desc'] ) ? 'data-info-desc="'.$args['info-desc'].'"' : '';
        $info_tip    = !empty( $args['info-tip'] ) ? 'data-info-tip="'.$args['info-tip'].'"' : '';
        $info_more   = !empty( $args['info-more'] ) ? 'data-info-more="'.$args['info-more'].'"' : '';
        $parent      = !empty( $args['parent'] ) ? 'data-parent="'.$args['parent'].'"' : '';

        $atts  = '';
        $atts .= isset( $placeholder ) ? 'placeholder="'.$placeholder.'"' : '';
        $atts .= !empty( $disabled ) ? 'disabled="disabled"' : '';
        $atts .= isset( $min ) ? 'min="'.$min.'"' : '';
        $atts .= !empty( $max ) ? 'max="'.$max.'"' : '';
        $atts .= !empty( $step ) ? 'step="'.$step.'"' : '';
        $atts .= !empty( $width ) ? 'width="'.$width.'"' : '';

        $options = Settings::get();
        $value   = isset( $options[$name] ) ? $options[$name] : $default;
        $checked = 'checkbox' === $type ? checked( $value, true, false ) : '';

        ?>
        <div class="m-editor-input" <?php echo $info_title; ?> <?php echo $info_desc; ?> <?php echo $info_tip; ?> <?php echo $info_more; ?>>
            <div class="m-editor-property__label"><?php echo $label; ?></div>
            <div class="m-editor-property">
                <input type="<?php echo $type; ?>" id="<?php echo $name; ?>" name="<?php echo $name; ?>" <?php echo $atts; ?> value="<?php echo $value; ?>" <?php echo $checked; ?> <?php echo $parent; ?>>
                <?php if ( 'range' === $type ) : ?>
                    <input type="number" id="<?php echo $name; ?>" name="<?php echo $name; ?>" class="m-editor-range-value" value="<?php echo $value; ?>" <?php echo $atts; ?> <?php echo $parent; ?>>
                    <script>
                        jQuery(document).ready(function($)
                        {
                            $('#<?php echo $name; ?>').on('input', function()
                            {
                                $(this).next('.m-editor-range-value').val($(this).val());
                            });
                            $('#<?php echo $name; ?> ~ .m-editor-range-value').on('input', function()
                            {
                                $(this).prev().val($(this).val());
                            });
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    public static function render_select( $name, $label, $options, $args = array() )
    {
        $default     = isset( $args['default'] ) ? $args['default'] : '';
        $disabled    = !empty( $args['disabled'] ) ? $args['disabled'] : '';
        $size        = !empty( $args['size'] ) ? $args['size'] : '';
        $info_title  = !empty( $args['info-title'] ) ? 'data-info-title="'.$args['info-title'].'"' : '';
        $info_desc   = !empty( $args['info-desc'] ) ? 'data-info-desc="'.$args['info-desc'].'"' : '';
        $info_tip    = !empty( $args['info-tip'] ) ? 'data-info-tip="'.$args['info-tip'].'"' : '';
        $info_more   = !empty( $args['info-more'] ) ? 'data-info-more="'.$args['info-more'].'"' : '';
        $parent      = !empty( $args['parent'] ) ? 'data-parent="'.$args['parent'].'"' : '';

        $atts  = '';
        $atts .= !empty( $disabled ) ? 'disabled="disabled"' : '';
        $atts .= !empty( $size ) ? 'size="'.$size.'"' : '';

        $settings = Settings::get();
        $selected = isset( $settings[$name] ) ? ( false === $settings[$name] ? '0' : $settings[$name] ) : $default;

        ?>
        <div class="m-editor-input" <?php echo $info_title; ?> <?php echo $info_desc; ?> <?php echo $info_tip; ?> <?php echo $info_more; ?>>
            <div class="m-editor-property__label"><?php echo $label; ?></div>
            <div class="m-editor-property">
                <select id="<?php echo $name; ?>" name="<?php echo $name; ?>" <?php echo $atts; ?> <?php echo $parent; ?>>
                    <?php foreach ( $options as $key => $item ) : ?>
                        <?php
                        $off = '';
                        if ( is_array( $item ) )
                        {
                            $off  = !empty( $item['disabled'] ) ? 'disabled="disabled"' : '';
                            $item = !empty( $item['label'] ) ? $item['label'] : '';
                        }
                        ?>
                        <option value="<?php echo $key; ?>" <?php selected( $selected, $key ); ?> <?php echo $off; ?>><?php echo $item; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }
    public static function render_textarea( $name, $label, $args = array() )
    {
        $default     = !empty( $args['default'] ) ? $args['default'] : '';
        $placeholder = !empty( $args['placeholder'] ) ? $args['placeholder'] : '';
        $disabled    = !empty( $args['disabled'] ) ? $args['disabled'] : '';
        $cols        = !empty( $args['cols'] ) ? $args['cols'] : '';
        $rows        = !empty( $args['rows'] ) ? $args['rows'] : '';
        $maxlength   = !empty( $args['maxlength'] ) ? $args['maxlength'] : '';
        $info_title  = !empty( $args['info-title'] ) ? 'data-info-title="'.$args['info-title'].'"' : '';
        $info_desc   = !empty( $args['info-desc'] ) ? 'data-info-desc="'.$args['info-desc'].'"' : '';
        $info_tip    = !empty( $args['info-tip'] ) ? 'data-info-tip="'.$args['info-tip'].'"' : '';
        $info_more   = !empty( $args['info-more'] ) ? 'data-info-more="'.$args['info-more'].'"' : '';
        $parent      = !empty( $args['parent'] ) ? 'data-parent="'.$args['parent'].'"' : '';

        $atts  = '';
        $atts .= !empty( $placeholder ) ? 'placeholder="'.$placeholder.'"' : '';
        $atts .= !empty( $disabled ) ? 'disabled="disabled"' : '';
        $atts .= !empty( $cols ) ? 'cols="'.$cols.'"' : '';
        $atts .= !empty( $rows ) ? 'rows="'.$rows.'"' : '';
        $atts .= !empty( $maxlength ) ? 'maxlength="'.$maxlength.'"' : '';

        $options = Settings::get();
        $value   = isset( $options[$name] ) ? $options[$name] : $default;

        ?>
        <div class="m-editor-input" <?php echo $info_title; ?> <?php echo $info_desc; ?> <?php echo $info_tip; ?> <?php echo $info_more; ?>>
            <textarea id="<?php echo $name; ?>" name="<?php echo $name; ?>" <?php echo $atts; ?> <?php echo $parent; ?>><?php echo $value; ?></textarea>
        </div>
        <?php
    }
    public static function render_colorpicker( $name, $label, $args = array() )
    {
        $default               = !empty( $args['default'] ) ? $args['default'] : 'inherit';
        $container             = !empty( $args['container'] ) ? $args['container'] : 'body';
        $closeOnScroll         = !empty( $args['closeOnScroll'] ) ? $args['closeOnScroll'] : 'true';
        $padding               = !empty( $args['padding'] ) ? $args['padding'] : '8';
        $inline                = !empty( $args['inline'] ) ? $args['inline'] : 'false';
        $autoReposition        = !empty( $args['autoReposition'] ) ? $args['autoReposition'] : 'false';
        $sliders               = !empty( $args['sliders'] ) ? $args['sliders'] : 'h';
        $disabled              = !empty( $args['disabled'] ) ? $args['disabled'] : 'false';
        $comparison            = !empty( $args['comparison'] ) ? $args['comparison'] : 'false';
        $defaultRepresentation = !empty( $args['defaultRepresentation'] ) ? $args['defaultRepresentation'] : 'HEX';
        $position              = !empty( $args['position'] ) ? $args['position'] : 'bottom-end';
        $adjustableNumbers     = !empty( $args['adjustableNumbers'] ) ? $args['adjustableNumbers'] : 'true';
        $info_title            = !empty( $args['info-title'] ) ? 'data-info-title="'.$args['info-title'].'"' : '';
        $info_desc             = !empty( $args['info-desc'] ) ? 'data-info-desc="'.$args['info-desc'].'"' : '';
        $info_tip              = !empty( $args['info-tip'] ) ? 'data-info-tip="'.$args['info-tip'].'"' : '';
        $info_more             = !empty( $args['info-more'] ) ? 'data-info-more="'.$args['info-more'].'"' : '';
        $parent                = !empty( $args['parent'] ) ? 'data-parent="'.$args['parent'].'"' : '';

        $options = Settings::get();
        $value   = !empty( $options[$name] ) ? $options[$name] : $default;
        $value   = 'inherit' === $value ? '' : $value;

        ?>
        <div class="m-editor-input" <?php echo $info_title; ?> <?php echo $info_desc; ?> <?php echo $info_tip; ?> <?php echo $info_more; ?>>
            <div class="m-editor-property__label"><?php echo $label; ?></div>
            <div class="m-editor-property">
                <input type="hidden" id="<?php echo $name; ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" <?php echo $parent; ?>>
                <div class="<?php echo $name; ?>"></div>
                <script>
                    const pickr_<?php echo $name; ?> = Pickr.create(
                        {
                            el                    : '.<?php echo $name; ?>',
                            container             : '<?php echo $container; ?>',
                            theme                 : 'nano',
                            closeOnScroll         : <?php echo $closeOnScroll; ?>,
                            padding               : <?php echo $padding; ?>,
                            inline                : <?php echo $inline; ?>,
                            autoReposition        : <?php echo $autoReposition; ?>,
                            sliders               : '<?php echo $sliders; ?>',
                            disabled              : <?php echo $disabled; ?>,
                            comparison            : <?php echo $comparison; ?>,
                            defaultRepresentation : '<?php echo $defaultRepresentation; ?>',
                            position              : '<?php echo $position; ?>',
                            adjustableNumbers     : <?php echo $adjustableNumbers; ?>,

                            default : '<?php echo $value; ?>',

                            swatches : [
                                'rgba(244, 67, 54, 1)',
                                'rgba(233, 30, 99, 0.95)',
                                'rgba(156, 39, 176, 0.9)',
                                'rgba(103, 58, 183, 0.85)',
                                'rgba(63, 81, 181, 0.8)',
                                'rgba(33, 150, 243, 0.75)',
                                'rgba(3, 169, 244, 0.7)',
                                'rgba(0, 188, 212, 0.7)',
                                'rgba(0, 150, 136, 0.75)',
                                'rgba(76, 175, 80, 0.8)',
                                'rgba(139, 195, 74, 0.85)',
                                'rgba(205, 220, 57, 0.9)',
                                'rgba(255, 235, 59, 0.95)',
                                'rgba(255, 193, 7, 1)'
                            ],

                            components : {
                                preview : true,
                                opacity : true,
                                hue     : true,
                                interaction : {
                                    hex   : true,
                                    rgba  : true,
                                    hsla  : false,
                                    hsva  : false,
                                    cmyk  : false,
                                    input : true,
                                    clear : true,
                                    save  : true
                                },
                            },
                            i18n: {
                                'ui:dialog'      : 'color picker dialog',
                                'btn:toggle'     : 'toggle color picker dialog',
                                'btn:swatch'     : 'color swatch',
                                'btn:last-color' : 'use previous color',
                                'btn:save'       : '<?php _e( 'Apply' ); ?>',
                                'btn:cancel'     : '<?php _e( 'Cancel' ); ?>',
                                'btn:clear'      : '<?php _e( 'Clear' ); ?>',
                                'aria:btn:save'   : 'save and close',
                                'aria:btn:cancel' : 'cancel and close',
                                'aria:btn:clear'  : 'clear and close',
                                'aria:input'      : 'color input field',
                                'aria:palette'    : 'color selection area',
                                'aria:hue'        : 'hue selection slider',
                                'aria:opacity'    : 'selection slider'
                            },
                        });
                    pickr_<?php echo $name; ?>.on('change', (color, instance) => {

                        let hidden = document.getElementById('<?php echo $name; ?>');
                        hidden.value = color.toHEXA().toString();
                        hidden.dispatchEvent(new Event('change', {bubbles: true, cancelable: true}));
                        hidden.dispatchEvent(new Event('input', {bubbles: true, cancelable: true}));
                    });
                    pickr_<?php echo $name; ?>.on('clear', instance => {

                        let hidden = document.getElementById('<?php echo $name; ?>');
                        hidden.value = 'transparent';
                        hidden.dispatchEvent(new Event('change', {bubbles: true, cancelable: true}));
                        hidden.dispatchEvent(new Event('input', {bubbles: true, cancelable: true}));
                    });
                    pickr_<?php echo $name; ?>.on('save', (color, instance) => {
                        pickr_<?php echo $name; ?>.hide();
                    });
                </script>
            </div>
        </div>
        <?php
    }
    public static function render_spacing( $prefix )
    {
        $options = Settings::get();

        ?>
        <div class="m-editor-spacing m-editor-input">

            <div class="m-editor-margin">
                <div class="m-editor-margin__label"><?php _e( "Margin", 'molongui-authorship' ); ?></div>
                <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-margin-top"    value="<?php echo !empty( $options[$prefix.'margin_top'] ) ? $options[$prefix.'margin_top'] : ''; ?>"       id="<?php echo $prefix; ?>margin_top"    name="<?php echo $prefix; ?>margin_top"    title="margin-top">
                <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-margin-right"  value="<?php echo !empty( $options[$prefix.'margin_right'] ) ? $options[$prefix.'margin_right'] : ''; ?>"   id="<?php echo $prefix; ?>margin_right"  name="<?php echo $prefix; ?>margin_right"  title="margin-right">
                <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-margin-bottom" value="<?php echo !empty( $options[$prefix.'margin_bottom'] ) ? $options[$prefix.'margin_bottom'] : ''; ?>" id="<?php echo $prefix; ?>margin_bottom" name="<?php echo $prefix; ?>margin_bottom" title="margin-bottom">
                <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-margin-left"   value="<?php echo !empty( $options[$prefix.'margin_left'] ) ? $options[$prefix.'margin_left'] : ''; ?>"     id="<?php echo $prefix; ?>margin_left"   name="<?php echo $prefix; ?>margin_left"   title="margin-left">

                <div class="m-editor-border">
                    <div class="m-editor-border__label"><?php _e( "Border", 'molongui-authorship' ); ?></div>
                    <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-border-top-width"    value="<?php echo !empty( $options[$prefix.'border_top'] ) ? $options[$prefix.'border_top'] : ''; ?>"       id="<?php echo $prefix; ?>border_top"    name="<?php echo $prefix; ?>border_top"    title="border-top-width">
                    <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-border-right-width"  value="<?php echo !empty( $options[$prefix.'border_right'] ) ? $options[$prefix.'border_right'] : ''; ?>"   id="<?php echo $prefix; ?>border_right"  name="<?php echo $prefix; ?>border_right"  title="border-right-width">
                    <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-border-bottom-width" value="<?php echo !empty( $options[$prefix.'border_bottom'] ) ? $options[$prefix.'border_bottom'] : ''; ?>" id="<?php echo $prefix; ?>border_bottom" name="<?php echo $prefix; ?>border_bottom" title="border-bottom-width">
                    <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-border-left-width"   value="<?php echo !empty( $options[$prefix.'border_left'] ) ? $options[$prefix.'border_left'] : ''; ?>"     id="<?php echo $prefix; ?>border_left"   name="<?php echo $prefix; ?>border_left"   title="border-left-width">

                    <div class="m-editor-padding">
                        <div class="m-editor-padding__label"><?php _e( "Padding", 'molongui-authorship' ); ?></div>
                        <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-padding-top"    value="<?php echo !empty( $options[$prefix.'padding_top'] ) ? $options[$prefix.'padding_top'] : ''; ?>"       id="<?php echo $prefix; ?>padding_top"    name="<?php echo $prefix; ?>padding_top"    title="padding-top">
                        <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-padding-right"  value="<?php echo !empty( $options[$prefix.'padding_right'] ) ? $options[$prefix.'padding_right'] : ''; ?>"   id="<?php echo $prefix; ?>padding_right"  name="<?php echo $prefix; ?>padding_right"  title="padding-right">
                        <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-padding-bottom" value="<?php echo !empty( $options[$prefix.'padding_bottom'] ) ? $options[$prefix.'padding_bottom'] : ''; ?>" id="<?php echo $prefix; ?>padding_bottom" name="<?php echo $prefix; ?>padding_bottom" title="padding-bottom">
                        <input type="text" placeholder="-" class="m-editor-spacing-input m-editor-padding-left"   value="<?php echo !empty( $options[$prefix.'padding_left'] ) ? $options[$prefix.'padding_left'] : ''; ?>"     id="<?php echo $prefix; ?>padding_left"   name="<?php echo $prefix; ?>padding_left"   title="padding-left">
                    </div>
                </div>
            </div>

        </div>
        <?php
    }
    public static function render_separator()
    {
        ?>
        <div class="m-editor-separator"></div>
        <?php
    }
    public static function render_notice( $id, $notice )
    {
        $notice = apply_filters( '_authorship/editor_notice', $notice );
        ?>
        <div id="<?php echo $id; ?>-notice" class="m-editor-notice"><?php echo $notice; ?></div>
        <?php
    }
    public static function html_tags()
    {
        return array
        (
            'h1'   => 'H1',
            'h2'   => 'H2',
            'h3'   => 'H3',
            'h4'   => 'H4',
            'h5'   => 'H5',
            'h6'   => 'H6',
            'div'  => 'div',
            'span' => 'span',
            'p'    => 'p',
        );
    }
    public static function font_weight()
    {
        return array
        (
            ''        => 'Default',
            'normal'  => 'Normal',
            'bold'    => 'Bold',
            '100'     => '100 - Thin',
            '200'     => '200 - Extra light',
            '300'     => '300 - Light',
            '400'     => '400 - Normal',
            '500'     => '500 - Medium',
            '600'     => '600 - Semi bold',
            '700'     => '700 - Bold',
            '800'     => '800 - Extra bold',
            '900'     => '900 - Black',
        );
    }
    public static function text_transform()
    {
        return array
        (
            ''           => 'Default',
            'uppercase'  => 'Uppercase',
            'lowercase'  => 'Lowercase',
            'capitalize' => 'Capitalize',
            'none'       => 'Normal',
        );
    }
    public static function font_style()
    {
        return array
        (
            ''        => 'Default',
            'none'    => 'Normal',
            'italic'  => 'Italic',
            'oblique' => 'Oblique',
        );
    }
    public static function text_decoration()
    {
        return array
        (
            ''             => 'Default',
            'underline'    => 'Underline',
            'overline'     => 'Overline',
            'line-through' => 'Line Through',
            'none'         => 'None',
        );
    }
    public static function text_align()
    {
        return array
        (
            ''        => 'Default',
            'left'    => 'Left',
            'center'  => 'Center',
            'right'   => 'Right',
            'justify' => 'Justify',
        );
    }
    public static function border_style()
    {
        return array
        (
            'none'   => 'None',
            'dotted' => 'Dotted',
            'dashed' => 'Dashed',
            'solid'  => 'Solid',
            'double' => 'Double',
            'groove' => 'Groove',
            'ridge'  => 'Ridge',
            'inset'  => 'Inset',
            'outset' => 'Outset',
        );
    }
    public static function query_order()
    {
        return array
        (
            'ASC'  => __( 'Ascending' ),
            'DESC' => __( 'Descending' ),
        );
    }
    public static function input_has_units( $input )
    {
        if ( preg_match("/[a-z]/i", $input ) )
        {
            return true;
        }

        return false;
    }

} // class
Author_Box_Editor::instance();
