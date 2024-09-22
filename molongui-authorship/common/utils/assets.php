<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Assets
{
    public static function register_script( $file, $scope, $deps = array( 'jquery' ), $handle = null, $version = null )
    {
        if ( empty( $file ) or empty( $scope ) )
        {
            return;
        }
        if ( file_exists( trailingslashit( WP_PLUGIN_DIR ) . $file ) )
        {
            do_action( "authorship/{$scope}/pre_register_script", $scope );

            $handle  = !empty( $handle )  ? $handle  : str_replace( '_', '-', MOLONGUI_AUTHORSHIP_PREFIX . '-' . $scope );
            $version = !empty( $version ) ? $version : MOLONGUI_AUTHORSHIP_VERSION;

            wp_register_script( $handle, plugins_url( '/' ).$file, $deps, $version, true );
            $params = apply_filters( "authorship/{$scope}_script_params", '' );

            if ( !empty( $params ) )
            {
                wp_localize_script( $handle, str_replace( '-', '_', $handle ).'_params', $params );
            }
            else
            {
                $function = 'authorship_'.$scope.'_script_params';
                if ( function_exists( $function ) )
                {
                    $params = call_user_func( $function );

                    if ( !empty( $params ) )
                    {
                        wp_localize_script( $handle, str_replace( '-', '_', $handle ).'_params', $params );
                    }
                }
            }
            do_action( "authorship/{$scope}/script_registered", $scope );
        }
    }
    public static function enqueue_script( $file, $scope, $admin = false, $handle = null, $version = null )
    {
        if ( empty( $file ) or empty( $scope ) )
        {
            return;
        }

        $filepath = trailingslashit( WP_PLUGIN_DIR ) . $file;

        if ( file_exists( $filepath ) )
        {
            $filesize = filesize( $filepath );
            if ( !$filesize )
            {
                return;
            }

            $handle  = !empty( $handle )  ? $handle  : str_replace( '_', '-', MOLONGUI_AUTHORSHIP_PREFIX . '-' . $scope );
            $version = !empty( $version ) ? $version : MOLONGUI_AUTHORSHIP_VERSION;
            $inline = apply_filters( "authorship/{$scope}/inline_script", $filesize < 4096 );
            do_action( "authorship/{$scope}/pre_enqueue_script", $scope, $inline );
            if ( $inline )
            {
                /*! This action is documented in includes/helpers/assets/scripts.php */
                if ( !did_action( "_authorship/{$scope}/script_inlined" ) )
                {
                    $hook = is_admin() ? 'admin_print_footer_scripts' : 'wp_print_footer_scripts';

                    add_action( $hook, function() use ( $scope, $filepath, $handle, $version )
                    {
                        do_action( "authorship/{$scope}/pre_inline_script", $scope, $filepath, $handle );
                        $contents = file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                        $params = apply_filters( "authorship/{$scope}_script_params", '' );
                        if ( empty( $params ) )
                        {
                            $function = 'authorship_'.$scope.'_script_params';
                            if ( function_exists( $function ) )
                            {
                                $params = call_user_func( $function );
                            }
                        }

                        if ( !empty( $params ) )
                        {
                            $jsextra = str_replace( '-', '_', $handle ).'_params';
                            echo '<script id="'.esc_attr( $handle ).'-inline-js-extra">' . 'var '.esc_html( $jsextra ).' = '.wp_json_encode( $params ).';' . '</script>';
                        }
                        echo '<script id="'.esc_attr( $handle ).'-inline-js" type="text/javascript" data-file="'.esc_attr( basename( $filepath ) ).'" data-version="'.esc_attr( $version ).'">' . $contents . '</script>';
                    });

                    /*!
                     * PRIVATE ACTION HOOK.
                     *
                     * For internal use only. Not intended to be used by plugin or theme developers.
                     * Future compatibility NOT guaranteed.
                     *
                     * Please do not rely on this hook for your custom code to work. As a private hook it is meant to be
                     * used only by Molongui. It may be edited, renamed or removed from future releases without prior notice
                     * or deprecation phase.
                     *
                     * If you choose to ignore this notice and use this filter, please note that you do so at on your own
                     * risk and knowing that it could cause code failure.
                     */
                    do_action( "_authorship/{$scope}/script_inlined" );
                }
            }
            else
            {
                wp_enqueue_script( $handle );
            }
            do_action( "authorship/{$scope}/script_loaded", $scope );
        }
    }
    public static function register_style( $file, $scope, $deps = array(), $handle = null, $version = null )
    {
        if ( empty( $file ) or empty( $scope ) )
        {
            return;
        }
        if ( file_exists( trailingslashit( WP_PLUGIN_DIR ) . $file ) )
        {
            do_action( "authorship/{$scope}/pre_register_styles", $scope );

            $handle  = !empty( $handle )  ? $handle  : str_replace( '_', '-', MOLONGUI_AUTHORSHIP_PREFIX . '-' . $scope );
            $version = !empty( $version ) ? $version : MOLONGUI_AUTHORSHIP_VERSION;

            wp_register_style( $handle, plugins_url( '/' ) . $file, $deps, $version, 'all' );
            $extra = apply_filters( "authorship/{$scope}_extra_styles", '' );

            if ( !empty( $extra ) )
            {
                wp_add_inline_style( $handle, $extra );
            }
            else
            {
                $function = 'authorship_'.$scope.'_extra_styles';
                if ( function_exists( $function ) )
                {
                    $extra = call_user_func( $function );

                    if ( !empty( $extra ) )
                    {
                        wp_add_inline_style( $handle, $extra );
                    }
                }
            }
            do_action( "authorship/{$scope}/styles_registered", $scope );
        }
    }
    public static function enqueue_style( $file, $scope, $admin = false, $handle = null, $version = null )
    {
        if ( empty( $file ) or empty( $scope ) )
        {
            return;
        }

        $filepath = trailingslashit( WP_PLUGIN_DIR ) . $file;

        if ( file_exists( $filepath ) )
        {
            $filesize = filesize( $filepath );
            if ( !$filesize )
            {
                return;
            }

            $handle  = !empty( $handle )  ? $handle  : str_replace( '_', '-', MOLONGUI_AUTHORSHIP_PREFIX . '-' . $scope );
            $version = !empty( $version ) ? $version : MOLONGUI_AUTHORSHIP_VERSION;
            $inline = apply_filters( "authorship/{$scope}/inline_styles", $filesize < 4096 );
            do_action( "authorship/{$scope}/pre_enqueue_styles", $scope, $inline );
            if ( $inline )
            {
                /*! This action is documented in includes/helpers/assets/styles.php */
                if ( !did_action( "_authorship/{$scope}/styles_inlined" ) )
                {
                    $hook = is_admin() ? 'admin_print_footer_scripts' : 'wp_print_footer_scripts';

                    add_action( $hook, function() use ( $scope, $filepath, $handle, $version )
                    {
                        $contents = file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

                        /*!
                         * PRIVATE FILTER HOOK.
                         *
                         * For internal use only. Not intended to be used by plugin or theme developers.
                         * Future compatibility NOT guaranteed.
                         *
                         * Please do not rely on this hook for your custom code to work. As a private hook it is meant to be
                         * used only by Molongui. It may be edited, renamed or removed from future releases without prior
                         * notice or deprecation phase.
                         *
                         * If you choose to ignore this notice and use this filter, please note that you do so at on your
                         * own risk and knowing that it could cause code failure.
                         *
                         * @todo Is this filter being used by any plugin? REMOVE it if it is not.
                         */
                        $contents = apply_filters( "_authorship/{$scope}/styles_contents", $contents, $filepath );
                        $extra = apply_filters( "authorship/{$scope}_extra_styles", '' );
                        if ( empty( $extra ) )
                        {
                            $function = 'authorship_'.$scope.'_extra_styles';
                            if ( function_exists( $function ) )
                            {
                                $extra = call_user_func( $function );
                            }
                        }

                        echo '<style id="'.esc_attr( $handle ).'-inline-css" data-file="'.esc_attr( basename( $filepath ) ).'" data-version="'.esc_attr( $version ).'">' . $contents . $extra . '</style>';
                    });

                    /*!
                     * PRIVATE ACTION HOOK.
                     *
                     * For internal use only. Not intended to be used by plugin or theme developers.
                     * Future compatibility NOT guaranteed.
                     *
                     * Please do not rely on this hook for your custom code to work. As a private hook it is meant to be
                     * used only by Molongui. It may be edited, renamed or removed from future releases without prior notice
                     * or deprecation phase.
                     *
                     * If you choose to ignore this notice and use this filter, please note that you do so at on your own
                     * risk and knowing that it could cause code failure.
                     */
                    do_action( "_authorship/{$scope}/styles_inlined" );
                }
            }
            else
            {
                wp_enqueue_style( $handle );
            }
            do_action( "authorship/{$scope}/styles_loaded", $scope );
        }
    }
    public static function register_media_uploader()
    {
        $file = MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/common/media-upload-rtl.min.css' : '/assets/css/common/media-upload.min.css' );
        $deps = array();

        Assets::register_style( $file, 'media-uploader', $deps );
    }
    public static function enqueue_media_uploader_styles()
    {
        $file = MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/common/media-upload-rtl.min.css' : '/assets/css/common/media-upload.min.css' );

        Assets::enqueue_style( $file, 'media-uploader', true );
    }
    public static function register_sweetalert()
    {
        $version = '2.1.2';

        if ( apply_filters( 'authorship/assets/load_remote', true ) )
        {
            $sweetalert_js_url = 'https://cdn.jsdelivr.net/npm/sweetalert@'.$version.'/dist/sweetalert.min.js';
        }
        else
        {
            $sweetalert_js_url = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/sweetalert/sweetalert.min.js';
        }
        wp_register_script( 'molongui-sweetalert', $sweetalert_js_url, array( 'jquery' ), $version, true );
    }
    public static function enqueue_sweetalert()
    {
        $handle = 'molongui-sweetalert';
        if ( !wp_script_is( $handle, 'registered' ) )
        {
            self::register_sweetalert();
        }
        if ( wp_script_is( $handle, 'registered' ) and !wp_script_is( $handle, 'enqueued' ) )
        {
            wp_enqueue_script( $handle );
            wp_add_inline_script( $handle, 'var molongui_swal = swal;' );
        }
    }
    public static function register_selectr()
    {
        $version = '2.4.13';

        if ( apply_filters( 'authorship/assets/load_remote', true ) )
        {
            $selectr_js_url  = 'https://cdn.jsdelivr.net/npm/mobius1-selectr@'.$version.'/dist/selectr.min.js';
            $selectr_css_url = 'https://cdn.jsdelivr.net/npm/mobius1-selectr@'.$version.'/dist/selectr.min.css';
        }
        else
        {
            $selectr_js_url  = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/selectr/selectr.min.js';
            $selectr_css_url = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/selectr/selectr.min.css';
        }
        wp_register_script( 'molongui-selectr', $selectr_js_url, array(), $version, true );
        wp_register_style( 'molongui-selectr', $selectr_css_url, array(), $version, 'screen' );
    }
    public static function enqueue_selectr()
    {
        $handle = 'molongui-selectr';
        if ( !wp_script_is( $handle, 'registered' ) )
        {
            self::register_selectr();
        }
        if ( wp_script_is( $handle, 'registered' ) and !wp_script_is( $handle, 'enqueued' ) )
        {
            wp_enqueue_script( $handle );
            wp_enqueue_style( $handle );
            wp_add_inline_script( $handle, 'var MolonguiSelectr = Selectr; Selectr = undefined;' );
        }
    }
    public static function register_sortable()
    {
        $version = '1.10.2'; //'1.14.0';

        if ( apply_filters( 'authorship/assets/load_remote', true ) )
        {
            $sortable_js_url = 'https://cdn.jsdelivr.net/npm/sortablejs@'.$version.'/Sortable.min.js';
        }
        else
        {
            $sortable_js_url  = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/sortable/Sortable.min.js';
        }
        wp_register_script( 'molongui-sortable', $sortable_js_url, array( 'jquery' ), $version, true );
    }
    public static function enqueue_sortable()
    {
        $handle = 'molongui-sortable';
        if (!wp_script_is($handle, 'registered'))
        {
            self::register_sortable();
        }
        if (wp_script_is($handle, 'registered') and !wp_script_is($handle, 'enqueued'))
        {
            wp_enqueue_script($handle);
        }
    }
    public static function register_element_queries()
    {
        $version = '1.2.2'; //'1.2.3';

        if ( apply_filters( 'authorship/load_element_queries', true ) )
        {
            if ( apply_filters( 'authorship/assets/load_remote', true ) )
            {
                $resizesensor_js_url   = 'https://cdn.jsdelivr.net/npm/css-element-queries@'.$version.'/src/ResizeSensor.min.js';
                $elementqueries_js_url = 'https://cdn.jsdelivr.net/npm/css-element-queries@'.$version.'/src/ElementQueries.min.js';
            }
            else
            {
                $resizesensor_js_url   = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/element-queries/ResizeSensor.min.js';
                $elementqueries_js_url = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/element-queries/ElementQueries.min.js';
            }
            wp_register_script( 'molongui-resizesensor',   $resizesensor_js_url,   array( 'jquery' ), $version, true );
            wp_register_script( 'molongui-elementqueries', $elementqueries_js_url, array( 'jquery' ), $version, true );
        }
    }
    public static function enqueue_element_queries()
    {
        $handle = 'molongui-resizesensor';
        if (!wp_script_is($handle, 'registered'))
        {
            self::register_element_queries();
        }
        if (wp_script_is($handle, 'registered') and !wp_script_is($handle, 'enqueued'))
        {
            wp_enqueue_script($handle);
        }
        $handle = 'molongui-elementqueries';
        if (!wp_script_is($handle, 'registered'))
        {
            self::register_element_queries();
        }
        if (wp_script_is($handle, 'registered') and !wp_script_is($handle, 'enqueued'))
        {
            wp_enqueue_script($handle);
        }
    }
    public static function register_semantic_ui_dropdown()
    {
        $version = '2.4.1';

        if ( apply_filters( 'authorship/assets/load_remote', true ) )
        {
            $dropdown_js_url  = 'https://cdn.jsdelivr.net/npm/semantic-ui-dropdown@'.$version.'/dropdown.min.js';
            $dropdown_css_url = 'https://cdn.jsdelivr.net/npm/semantic-ui-dropdown@'.$version.'/dropdown.min.css';
        }
        else
        {
            $dropdown_js_url  = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/semantic/dropdown.'.$version.'.min.js';
            $dropdown_css_url = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/semantic/dropdown.'.$version.'.min.css';
        }
        wp_register_script( 'molongui-dropdown', $dropdown_js_url , array( 'jquery' ), $version, true );
        wp_register_style( 'molongui-dropdown' , $dropdown_css_url, array(), $version, 'screen' );
    }
    public static function enqueue_semantic_ui_dropdown()
    {
        wp_enqueue_script( 'molongui-dropdown' );
        wp_enqueue_style( 'molongui-dropdown'  );
    }
    public static function register_semantic_ui_transition()
    {
        $version = '2.3.1';

        if ( apply_filters( 'authorship/assets/load_remote', true ) )
        {
            $transition_js_url  = 'https://cdn.jsdelivr.net/npm/semantic-ui-transition@'.$version.'/transition.min.js';
            $transition_css_url = 'https://cdn.jsdelivr.net/npm/semantic-ui-transition@'.$version.'/transition.min.css';
        }
        else
        {
            $transition_js_url  = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/semantic/transition.'.$version.'.min.js';
            $transition_css_url = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/semantic/transition.'.$version.'.min.css';
        }
        wp_register_script( 'molongui-transition', $transition_js_url , array( 'jquery' ), $version, true );
        wp_register_style( 'molongui-transition' , $transition_css_url, array(), $version, 'screen' );
    }
    public static function enqueue_semantic_ui_transition()
    {
        wp_enqueue_script( 'molongui-transition' );
        wp_enqueue_style( 'molongui-transition'  );
    }
    public static function register_semantic_ui_icon()
    {
        $version = '2.3.3';

        if ( apply_filters( 'authorship/assets/load_remote', true ) )
        {
            $icon_css_url = 'https://cdn.jsdelivr.net/npm/semantic-ui-icon@'.$version.'/icon.min.css';
        }
        else
        {
            $icon_css_url = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/semantic/icon.'.$version.'.min.css';
        }
        wp_register_style( 'molongui-icon', $icon_css_url, array(), $version, 'screen' );
    }
    public static function enqueue_semantic_ui_icon()
    {
        wp_enqueue_style( 'molongui-icon' );
    }
    public static function register_semantic_ui_label()
    {
        $version = '2.3.2';

        if ( apply_filters( 'authorship/assets/load_remote', true ) )
        {
            $label_css_url = 'https://cdn.jsdelivr.net/npm/semantic-ui-label@'.$version.'/label.min.css';
        }
        else
        {
            $label_css_url = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/semantic/label.'.$version.'.min.css';
        }
        wp_register_style( 'molongui-label', $label_css_url, array(), $version, 'screen' );
    }
    public static function enqueue_semantic_ui_label()
    {
        wp_enqueue_style( 'molongui-label' );
    }
    public static function register_semantic_ui_popup()
    {
        $version = '2.3.1';

        if ( apply_filters( 'authorship/assets/load_remote', true ) )
        {
            $popup_js_url  = 'https://cdn.jsdelivr.net/npm/semantic-ui-popup@'.$version.'/popup.min.js';
            $popup_css_url = 'https://cdn.jsdelivr.net/npm/semantic-ui-popup@'.$version.'/popup.min.css';
        }
        else
        {
            $popup_js_url  = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/semantic/popup.'.$version.'.min.js';
            $popup_css_url = MOLONGUI_AUTHORSHIP_URL . 'common/assets/vendor/semantic/popup.'.$version.'.min.css';
        }
        wp_register_script( 'molongui-popup', $popup_js_url , array( 'jquery' ), $version, true );
        wp_register_style( 'molongui-popup' , $popup_css_url, array(), $version, 'screen' );
    }
    public static function enqueue_semantic_ui_popup()
    {
        wp_enqueue_script( 'molongui-popup' );
        wp_enqueue_style( 'molongui-popup'  );
    }
    public static function enqueue_semantic()
    {
        self::enqueue_semantic_ui_transition(); // Dependency. Required by Semantic UI Dropdown
        self::enqueue_semantic_ui_icon();       // Used by Semantic UI Dropdown. Not a hard dependency
        self::enqueue_semantic_ui_label();      // Used by Semantic UI Dropdown. Not a hard dependency
        self::enqueue_semantic_ui_dropdown();
        self::enqueue_semantic_ui_popup();
    }

} // class