<?php

namespace Molongui\Authorship\Common\Modules\Settings;

use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
trait Settings_Page
{
    public static function is_settings_page()
    {
        $current_screen = null;

        if ( function_exists( 'get_current_screen' ) )
        {
            $current_screen = get_current_screen();
        }

        if ( $current_screen )
        {
            return (bool) strpos( $current_screen->id, MOLONGUI_AUTHORSHIP_NAME );
        }
        else
        {
            global $pagenow;
            return $pagenow === 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] === MOLONGUI_AUTHORSHIP_NAME;
        }
    }
    public function dequeue_wp_forms_styles( $wp_styles )
    {
        if ( !( is_admin() && self::is_settings_page() ) )
        {
            return $wp_styles;
        }

        $style_handle = 'forms';
        if ( isset( $wp_styles->registered[ $style_handle ] ) )
        {
            unset( $wp_styles->registered[ $style_handle ] );
            if ( in_array( $style_handle, $wp_styles->queue, true ) )
            {
                $wp_styles->queue = array_diff( $wp_styles->queue, array( $style_handle ) );
            }
            foreach ( $wp_styles->registered as $handle => $style )
            {
                if ( in_array( $style_handle, $style->deps, true ) )
                {
                    $wp_styles->registered[ $handle ]->deps = array_diff( $style->deps, array( $style_handle ) );
                }
            }
        }

        return $wp_styles;
    }
    public function void_wp_forms_styles( $html, $handle, $href, $media )
    {
        if ( 'forms' === $handle && is_admin() && self::is_settings_page() )
        {
            return '';
        }
        return $html;
    }
    public static function render()
    {
        $settings = apply_filters( 'authorship/plugin_settings', array() );
        if ( $settings )
        {
            foreach ( $settings as $key => $value )
            {
                if ( isset( $value['display'] ) and !$value['display'] )
                {
                    continue;
                }

                if ( $value['type'] == 'section' )
                {
                    $tabs[$value['id']] = array
                    (
                        'display' => empty( $value['display'] ) ? true : $value['display'],
                        'access'  => empty( $value['access']  ) ? 'public' : $value['access'],
                        'id'      => $value['id'],
                        'name'    => ucfirst( $value['name'] )
                    );
                    $parent = $value['id'];
                }
                else
                {
                    if ( !isset( $parent ) )
                    {
                        $parent = 0;
                    }
                    ${'tab_'.$parent}[$key] = $value;
                }
            }
            if ( isset( $tabs ) )
            {
                $nav_items    = '';
                $div_contents = null;
                $current_tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( $current_tab == '' )
                {
                    reset( $tabs );
                    $current_tab = key( $tabs );
                    while ( !$tabs[$current_tab]['display'] )
                    {
                        next( $tabs );
                        $current_tab = key( $tabs );
                    }
                }
                foreach ( $tabs as $tab )
                {
                    if ( 'private' !== $tab['access'] )
                    {
                        $nav_items .= '<li class="m-section-nav-tab '.( $tab['id'] == $current_tab ? 'is-selected' : '' ).'"><a class="m-section-nav-tab__link" href="#'.$tab['id'].'" data-id="'.$tab['id'].'" role="menuitem"><span class="m-section-nav-tab__text">' . $tab['name'] . '</span></a></li>';
                    }
                    $div_contents .= '<section id="'.$tab['id'].'" class="m-tab '.( $tab['id'] == $current_tab ? 'current' : '' ).'">';
                    if ( isset( ${'tab_'.$tab['id']} ) )
                    {
                        $group = '';
                        foreach ( ${'tab_'.$tab['id']} as $option )
                        {
                            if ( 'header' === $option['type'] ) $group = empty( $option['id'] ) ? '' : str_replace( '_header', '', $option['id'] );

                            $html = new Control( $option, $group, '', MOLONGUI_AUTHORSHIP_PREFIX.'_' );
                            $div_contents .= $html;
                        }
                    }
                    elseif ( 'help' === (string) $tab['id'] )
                    {
                        $div_contents .= self::support_tab_content();
                    }
                    else
                    {
                        $div_contents .= __( "There are no settings defined for this tab.", 'molongui-authorship' );
                    }

                    $div_contents .= '</section>';
                }
            }
            else
            {
                $no_tab = true;
                $div_contents = '<div class="m-no-tab">';

                foreach ( ${'tab_0'} as $tab_content )
                {
                    $option = new Control( $tab_content, '', '', MOLONGUI_AUTHORSHIP_PREFIX );
                    $div_contents .= $option;
                }

                $div_contents .= '</div>';
            }

        }
        require_once MOLONGUI_AUTHORSHIP_DIR . 'common/modules/settings/views/html-page-options.php';
    }
    public static function register_scripts()
    {
        do_action( 'authorship/options/enqueue_required_deps' );
        Assets::enqueue_sweetalert();
        $deps = apply_filters( 'authorship/options/script_deps', array() );
        if ( !empty( $deps ) )
        {
            add_filter( "authorship/options/inline_script", '__return_false' );
        }
        $file = apply_filters( 'authorship/options/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/common/options.xxxx.min.js' );

        Assets::register_script( $file, 'options', $deps );
    }
    public static function enqueue_scripts()
    {
        $file = apply_filters( 'authorship/options/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/common/options.xxxx.min.js' );

        Assets::enqueue_script( $file, 'options', true );
    }
    public static function localize_scripts()
    {
        $params = array
        (
            'plugin_id'      => MOLONGUI_AUTHORSHIP_PREFIX,
            'plugin_version' => MOLONGUI_AUTHORSHIP_VERSION,
            'is_pro'         => did_action( 'authorship_pro/loaded' ),
            'options_page'   => esc_url( admin_url( 'admin.php?page=' . MOLONGUI_AUTHORSHIP_NAME . '&tab=' . MOLONGUI_AUTHORSHIP_PREFIX . '_pro_' . 'license' ) ),
            'cm_settings' => array
            (
                'custom_css' => wp_enqueue_code_editor( array( 'type' => 'text/css', 'codemirror' => array( 'mode' => 'css', 'lint' => true, 'autofocus' => true ) ) ),
                'custom_php' => wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php', 'codemirror' => array( 'mode' => 'php', 'lint' => true, 'autofocus' => true ) ) ),
            ),
            1 => __( "Premium feature", 'molongui-authorship' ),
            2 => __( "This feature is available only for Premium users. Upgrade to Premium to unlock it!", 'molongui-authorship' ),
            10001 => '', // unused?
            10002 => __( "Saving", 'molongui-authorship' ),
            10003 => __( "You are about to leave this page without saving. All changes will be lost.", 'molongui-authorship' ),
            10004 => __( "WARNING: You are about to delete all your settings! Please confirm this action.", 'molongui-authorship' ),
            10005 => MOLONGUI_AUTHORSHIP_PREFIX.'_',
            10006 => __( "WARNING: You are about to restore your backup. This will overwrite all your settings! Please confirm this action.", 'molongui-authorship' ),
            10007 => __( "WARNING: You are about to delete your backup. All unsaved options will be lost. We recommend that you save your options before deleting a backup. Please confirm this action.", 'molongui-authorship' ),
            10008 => __( "WARNING: You are about to create a backup. All unsaved options will be lost. We recommend that you save your options before deleting a backup. Please confirm this action.", 'molongui-authorship' ),
            10009 => __( "Delete", 'molongui-authorship' ),
            10010 => MOLONGUI_AUTHORSHIP_PREFIX,
            10011 => wp_create_nonce( 'mfw_import_options_nonce' ),
            10012 => __( "File upload failed", 'molongui-authorship' ),
            10013 => __( "Failed to load file.", 'molongui-authorship' ),
            10014 => __( "Wrong file type", 'molongui-authorship' ),
            10015 => __( "Only valid .JSON files are accepted.", 'molongui-authorship' ),
            10016 => __( "Warning", 'molongui-authorship' ),
            10017 => __( "You are about to restore your settings. This will overwrite all your existing configuration! Please confirm this action.", 'molongui-authorship' ),
            10018 => __( "Cancel", 'molongui-authorship' ),
            10019 => __( "OK", 'molongui-authorship' ),
            10020 => __( "Success!", 'molongui-authorship' ),
            10021 => __( "Plugin settings have been imported successfully. Click on the OK button and the page will be reloaded automatically.", 'molongui-authorship' ),
            10022 => __( "Error", 'molongui-authorship' ),
            10023 => __( "Something went wrong and plugin settings couldn't be restored. Please, make sure uploaded file has content and try uploading the file again.", 'molongui-authorship' ),
            /*! // translators: %1$s: Plugin name. %2$s: Plugin version. */
            10024 => sprintf( esc_html__( "Either the uploaded backup file is for another plugin or it is from a newer version of the plugin. Please, make sure you are uploading a file generated with %s version lower or equal to %s.", 'molongui-authorship' ), MOLONGUI_AUTHORSHIP_TITLE, MOLONGUI_AUTHORSHIP_VERSION ),
            10025 => __( "Some settings couldn't be restored. Please, try uploading the file again.", 'molongui-authorship' ),
            10026 => __( "You are about to restore plugin default settings. This will overwrite all your existing configuration! Please confirm this action.", 'molongui-authorship' ),
            10027 => wp_create_nonce( 'mfw_reset_options_nonce' ),
            10028 => __( "Plugin settings have been restored to defaults successfully. Click on the OK button and the page will be reloaded automatically.", 'molongui-authorship' ),
            10029 => __( "Something went wrong and plugin defaults couldn't be restored. Please, try again.", 'molongui-authorship' ),
            10030 => __( "Something went wrong and couldn't connect to the server. Please, try again.", 'molongui-authorship' ),
            20000 => wp_create_nonce( 'mfw_license_nonce' ),
            20001 => __( "Something is missing...", 'molongui-authorship' ),
            20002 => __( "You need to provide both values, License Key and PIN", 'molongui-authorship' ),
            20003 => __( "Activated!", 'molongui-authorship' ),
            20004 => __( "Oops... activation failed", 'molongui-authorship' ),
            20005 => __( "Oops!", 'molongui-authorship' ),
            20006 => __( "Something went wrong and the license has not been activated.", 'molongui-authorship' ),
            20007 => __( "Deactivate license", 'molongui-authorship' ),
            20008 => __( "Submit to deactivate your license now", 'molongui-authorship' ),
            20009 => __( "No, cancel!", 'molongui-authorship' ),
            20010 => __( "Yes, deactivate it!", 'molongui-authorship' ),
            20011 => __( "Deactivated!", 'molongui-authorship' ),
            20012 => __( "Oops... something weird happened!", 'molongui-authorship' ),
            20013 => __( "Something went wrong and the license has not been deactivated.", 'molongui-authorship' ),
            20014 => __( "Activate", 'molongui-authorship' ),
            20015 => __( "Deactivate", 'molongui-authorship' ),
            20016 => __( "Error", 'molongui-authorship' ),
            20017 => __( "License PIN must contain only digits", 'molongui-authorship' ),
        );
        return apply_filters( 'authorship/options/script_params', $params );
    }
    public static function register_styles()
    {
        if ( apply_filters( 'authorship/options/enqueue_colorpicker', false ) ) wp_enqueue_style( 'wp-color-picker' );
        $file = apply_filters( 'authorship/options/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/common/modules/settings/assets/css/styles-rtl.e456.min.css' : '/common/modules/settings/assets/css/styles.9f01.min.css' ) );
        $deps = array();

        Assets::register_style( $file, 'options', $deps );
    }
    public static function enqueue_styles()
    {
        $file = apply_filters( 'authorship/options/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/common/modules/settings/assets/css/styles-rtl.e456.min.css' : '/common/modules/settings/assets/css/styles.9f01.min.css' ) );

        Assets::enqueue_style( $file, 'options', true );
    }
    public static function extra_styles()
    {
        $css = '';
        $css .= WP::get_admin_color();
        return apply_filters( 'authorship/options/extra_styles', $css );
    }
    public static function support_tab_content()
    {
        $tidio_url   = Helpers::get_tidio_url();
        $chat_online = Helpers::is_time_in_range( '09:00', '15:00' );

        ob_start(); ?>

        <?php UI::heading( array( 'title' => __( "Need help? Let us know and we will be happy to assist", 'molongui-authorship' ) ) ); ?>

        <div class="molongui-ui-columns">

            <div class="molongui-ui-column molongui-ui-column-left">

                <!-- Documentation -->
                <div class="molongui-ui-card">
                    <div class="molongui-ui-card__title">
                        <?php esc_html_e( "Explore Our Documentation", 'molongui-authorship' ); ?>
                    </div>
                    <div class="molongui-ui-card__description">
                        <?php esc_html_e( "Find detailed guides, FAQs, and troubleshooting tips in our comprehensive plugin documentation. Get the answers you need to make the most out of your plugin!", 'molongui-authorship' ); ?>
                    </div>
                    <a class="molongui-ui-card__button" href="<?php echo 'https://www.molongui.com/help/docs/'; ?>" target="_blank" type="button">
                        <?php esc_html_e( "Open Docs →", 'molongui-authorship' ); ?>
                    </a>
                </div>

                <!-- Live Chat -->
                <div class="molongui-ui-card">
                    <div class="molongui-ui-card__title">
                        <?php esc_html_e( "Live Support Chat", 'molongui-authorship' ); ?>
                    </div>
                    <div class="molongui-ui-card__description">
                        <?php esc_html_e( "Need immediate help and couldn't find answers in the documentation? Chat with us directly! Our support team is here to assist you.", 'molongui-authorship' ); ?>
                    </div>
                    <div class="molongui-ui-card__description">
                        <?php if ( $chat_online ) : ?>
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo sprintf( "%s%s%s"
                                            , _x( "Live Chat is currently", 'This is followed by the Live Chat Support status', 'molongui-authorship' )
                                            , ': '
                                            , '<b style="color:green">' . __( "Online", 'molongui-authorship' ) . '</b>'
                                       );
                            ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo sprintf( "%s%s%s"
                                            , _x( "Live Chat is currently", 'This is followed by the Live Chat Support status', 'molongui-authorship' )
                                            , ': '
                                            , '<b style="color:red">' . __( "Offline", 'molongui-authorship' ) . '</b>'
                                       );
                            ?>
                        <?php endif; ?>
                    </div>
                    <a class="molongui-ui-card__button" target="_blank" href="<?php echo esc_url( $tidio_url ); ?>" title="<?php esc_attr_e( "Click to open Live Chat", 'molongui-authorship' ); ?>">
                        <?php esc_html_e( "Chat Now →", 'molongui-authorship' ); ?>
                    </a>
                </div>

                <!-- System Details -->
                <div class="molongui-ui-card">
                    <div class="molongui-ui-card__title">
                        <?php esc_html_e( "System Details", 'molongui-authorship' ); ?>
                    </div>
                    <div class="molongui-ui-card__description">
                        <?php esc_html_e( "Sometimes we may need more information about your site and plugin setup to assist you better. You can generate a detailed report and send it to our support team for faster troubleshooting.", 'molongui-authorship' ); ?>
                    </div>
                    <input type="hidden" name="system-details" value="">
                    <div style="display:flex; gap:1em">
                        <a class="molongui-ui-card__button" id="molongui-system-details-report" type="button">
                            <?php esc_html_e( "Send Report", 'molongui-authorship' ); ?>
                        </a>
                        <a class="molongui-ui-card__button molongui-ui-card__button--secondary" id="molongui-system-details" type="button">
                            <?php esc_html_e( "Copy Details", 'molongui-authorship' ); ?>
                        </a>
                    </div>
                </div>

            </div>

            <div class="molongui-ui-column molongui-ui-column-right">
                <div class="molongui-ui-card">
                    <div class="molongui-ui-card__title">
                        <?php esc_html_e( "Help Request", 'molongui-authorship' ); ?>
                    </div>
                    <div class="molongui-ui-card__description" style="margin:1em 0 2em">
                        <?php esc_html_e( "Still need help? Submit a support ticket using the form below. Our team is here to assist you!", 'molongui-authorship' ); ?>
                        <div style="display:flex; margin:2em 0 0">
                            <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; gap:4px; background:#e8c00e">
                                <span class="dashicons dashicons-tickets-alt" style="flex:0; width: 3em; height: 3em; font-size:3em; color:#102A43"></span>
                                <div style="flex:0; font-weight:bold; color:#102A43">
                                    <?php esc_html_e( "Ticket Notes", 'molongui-authorship' ); ?>
                                </div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:1em; padding:1em; background:#e8e8e8">
                                <div>
                                    <?php echo wp_kses_post( __( "<b>Languages</b>: English and Spanish", 'molongui-authorship' ) ); ?>
                                </div>
                                <div>
                                    <?php echo wp_kses_post( __( "<b>Response</b>: We usually reply within 24 hours", 'molongui-authorship' ) ); ?>
                                </div>
                                <div>
                                    <?php echo wp_kses_post( __( "<b>Follow-Up</b>: We respond to all tickets. If you haven't heard back, check your Spam folder.", 'molongui-authorship' ) ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Help Request -->
                    <form id="molongui-help-request-form">
                        <div id="molongui-form-error" class="hidden">
                            <?php esc_html_e( "All fields are mandatory", 'molongui-authorship' ); ?>
                        </div>
                        <p>
                            <label for="your-name"><?php esc_html_e( "Name", 'molongui-authorship' ); ?></label>
                            <input type="text" required id="your-name" name="your-name" placeholder="<?php esc_attr_e( "Your name here", 'molongui-authorship' ); ?>">
                        </p>
                        <p>
                            <label for="your-email"><?php esc_html_e( "Email", 'molongui-authorship' ); ?></label>
                            <input type="email" required id="your-email" name="your-email" placeholder="<?php esc_attr_e( "Your email address here", 'molongui-authorship' ); ?>">
                        </p>
                        <p>
                            <label for="your-subject"><?php esc_html_e( "Subject", 'molongui-authorship' ); ?></label>
                            <input type="text" required id="your-subject" name="your-subject" placeholder="<?php esc_attr_e( "Brief issue description", 'molongui-authorship' ); ?>">
                        </p>
                        <p>
                            <label for="your-message"><?php esc_html_e( "Message", 'molongui-authorship' ); ?></label>
                            <textarea required id="your-message" name="your-message" cols="40" rows="7" placeholder="<?php esc_attr_e( "Explain your issue providing a URL we can check", 'molongui-authorship' ); ?>"></textarea>
                        </p>
                        <p>
                            <input type="checkbox" required id="molongui-accept-tos" name="molongui-accept-tos" value="1">
                            <?php
                            /*! // translators: %1$s: Opening a tag. %2$s: Closing a tag. */
                            printf( esc_html__( "I have read and accept the %sprivacy policy%s.", 'molongui-authorship' ), '<a href="https://www.molongui.com/privacy/">', '</a>' );
                            ?>
                        </p>
                        <input type="hidden" name="plugin-id" value="<?php echo esc_attr( MOLONGUI_AUTHORSHIP_TITLE ); ?>">
                        <input type="hidden" name="ticket-id" value="<?php echo esc_attr( 'HR'.gmdate('y').'-'.gmdate('mdHis') ); ?>">
                        <button type="submit" id="molongui-submit-ticket" class="molongui-ui-card__button"><?php esc_html_e( "Submit Ticket", 'molongui-authorship' ); ?></button>
                    </form>
                </div>
            </div>
        </div>

        <?php

        wp_nonce_field( 'molongui-support-nonce', 'molongui-support-nonce' );

        return ob_get_clean();
    }
    public static function get_custom_php_tip()
    {
        ob_start();
        ?>
        <p><?php esc_html_e( "Activating this setting allows custom code to run in the WordPress admin area.", 'molongui-authorship' ); ?></p>
        <p><?php esc_html_e( "Running custom code in the backend has risks. If the code contains errors and causes a fatal error, you may lose access to your dashboard. However, you can recover from this situation:", 'molongui-authorship' ); ?></p>
        <p>
            <?php
            /*! // translators: %1$s: <strong>. %2$s: </strong>. %3$s: <code>. %4$s: </code> */
            echo sprintf( esc_html__( "%1\$sRevert Changes%2\$s: Append %3\$s?nophpAuthorship%4\$s to the end of your wp-admin URL to disable the custom code temporarily. For example:", 'molongui-authorship' ), '<strong>', '</strong>', '<code>', '</code>' );
            ?>
        </p>
        <p><code><?php echo esc_url( admin_url( '/options-general.php?page=molongui-authorship&tab=advanced&nophpAuthorship' ) ); ?></code></p>
        <p><?php esc_html_e( "This parameter disables the custom code, allowing you to regain access and fix the issue.", 'molongui-authorship' ); ?></p>
        <p>
            <?php
            /*! // translators: %1$s: <strong>. %2$s: </strong>. %3$s: <code>. %4$s: </code> */
            echo sprintf( esc_html__( "%1\$sTesting New Code%2\$s: Before permanently enabling custom code in the admin area, test your code by appending %3\$s?phpAuthorship%4\$s to the end of your wp-admin URL to simulate the environment:", 'molongui-authorship' ), '<strong>', '</strong>', '<code>', '</code>' );
            ?>
        </p>
        <p><?php esc_html_e( "This approach helps ensure the stability of your code before you apply changes.", 'molongui-authorship' ); ?></p>
        <?php

        return ob_get_clean();
    }
    public static function custom_css_control()
    {
        UI::textarea( array
        (
            'id'          => 'custom_css',
            'value'       => self::get( 'custom_css', '' ),
            'disabled'    => '',
            'echo'        => true,
            'class'       => 'codemirror-css',
            'label'       => '',
            'description' => __( 'Enter here any CSS rules you want to add to your site to modify default plugin styles. If you need help customizing anything, feel free to <a href="https://www.molongui.com/help/support/" target="_blank">open a support ticket</a> with us and we will be happy to help.', 'molongui-authorship' ),
            'rows'        => 10,
            'placeholder' => '',
            'tooltip'     => '',
        ));
    }
    public static function custom_php_control()
    {
        UI::textarea( array
        (
            'id'          => 'custom_php',
            'value'       => self::get( 'custom_php', '' ),
            'disabled'    => '',
            'echo'        => true,
            'class'       => 'codemirror-php',
            'label'       => '',
            'description' => sprintf( '%s%s%s%s%s%s'
                                , '<p>'
                                , __( "Add any PHP snippet here to modify the plugin's default behavior or for other purposes. <b>Keep the opening PHP tag</b> <code>&lt;?php</code> on the first line. Snippets will only run on your site's frontend by default, preventing loss of Dashboard access in case of errors. To run them in the Dashboard, enable the setting below.", 'molongui-authorship' )
                                , '</p>'
                                , '<div style="border-left:3px solid #fbb638; background:#f0f0f1; margin:1rem 0; font-family:Consolas,Monaco,monospace; font-size:11px; padding:8px">'
                                , __( "Ensure your code is error-free as no error checking occurs during saving.", 'molongui-authorship' )
                                , '</div>'
                             ),
            'rows'        => 10,
            'placeholder' => '',
            'tooltip'     => '',
        ));

        UI::checkbox( array
        (
            'id'          => 'custom_php_in_admin',
            'value'       => self::get( 'custom_php_in_admin', false ),
            'label'       => __( "Run custom PHP also in WordPress admin area <code>Exercise extreme caution!</code>", 'molongui-authorship' ),
            'echo'        => true,
            'class'       => '',
            'help'        => sprintf( "%s%s%s%s%s%s%s%s%s%s%s%s%s%s%s%s%s%s%s%s%s"
                                , '<p>'
                                , __( "Activating this setting allows custom code to run in the WordPress admin area.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "Running custom code in the backend has risks. If the code contains errors and causes a fatal error, you may lose access to your dashboard. However, you can recover from this situation:", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "<b>Revert Changes</b>: Append <code>?nophpAuthorship</code> to the end of your wp-admin URL to disable the custom code temporarily. For example:", 'molongui-authorship' )
                                , '</p>'
                                , '<p><code>' . esc_url( admin_url( '/options-general.php?page=molongui-authorship&tab=tools&nophpAuthorship' ) ) . '</code></p>'
                                , '<p>'
                                , __( "This parameter disables the custom code, allowing you to regain access and fix the issue.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "<b>Testing New Code</b>: Before permanently enabling custom code in the admin area, test your code by appending <code>?phpAuthorship</code> to the end of your wp-admin URL to simulate the environment.", 'molongui-authorship' )
                                , '</p>'
                                , '<p>'
                                , __( "This approach helps ensure the stability of your code before you apply changes.", 'molongui-authorship' )
                                , '</p>'
                                , '<hr>'
                                , '<a href="https://www.molongui.com/help/using-the-custom-php-setting/" target="_blank">' . __( "Learn more about custom PHP snippets", 'molongui-authorship' ) . '</a>'
                             ),
            'description' => '',
            'content'     => array
            (
                'off' => '',
                'on'  => '',
            ),
        ));
    }
    public static function plugin_settings_control()
    {
        echo '<div class="molongui-ui-cards">';

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Export", 'molongui-authorship' ),
            'description' => __( "Export the plugin settings to create a backup or restore it on another installation.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'export_options',
                'label' => __( "Backup", 'molongui-authorship' ),
                'link'  => '',
                'nonce' => 'molongui_export_options',
            ),
            'echo'        => true,
            'disabled'    => false,
            'class'       => '',
        ));

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Import", 'molongui-authorship' ),
            'description' => __( "Restore the plugin settings from a previously exported backup file.", 'molongui-authorship' ),
            'button'      => array
            (
                'id'     => 'import_options',
                'label'  => __( "Import", 'molongui-authorship' ),
                'accept' => '.json',
                'nonce'  => wp_nonce_field( 'molongui_import_options', 'molongui_import_options_nonce' ),
            ),
            'echo'        => true,
            'disabled'    => apply_filters( 'authorship/disable_premium_setting', 'premium' ),
            'class'       => '',
        ));

        UI::card( array
        (
            'id'          => '',
            'image'       => '',
            'title'       => __( "Reset", 'molongui-authorship' ),
            'description' => __( "Reset the plugin settings to their default values", 'molongui-authorship' ),
            'button'      => array
            (
                'id'    => 'reset_options',
                'label' => __( "Reset", 'molongui-authorship' ),
                'link'  => '',
                'nonce' => wp_nonce_field( 'molongui_reset_options', 'molongui_reset_options_nonce' ),
            ),
            'echo'        => true,
            'disabled'    => apply_filters( 'authorship/disable_premium_setting', 'premium' ),
            'class'       => '',
        ));

        echo '</div>';
    }
    public static function plugin_data_removal()
    {
        UI::toggle( array
        (
            'id'          => 'uninstall_enabled',
            'value'       => self::get( 'uninstall_enabled', false ),
            'label'       => __( "Enable Data Removal on Uninstall", 'molongui-authorship' ),
            'disabled'    => false,
            'echo'        => true,
            'class'       => '',
            'description' => __( "Choose what plugin data should be removed when uninstalling. Enabling this setting lets you decide which information—such as plugin data and configuration—should be deleted along with the plugin files (which are always removed upon uninstall). Use this to control the level of data cleanup during uninstallation.", 'molongui-authorship' ),
            'content'     => array
            (
                'off' => '',
                'on'  => self::get_plugin_data_removal_hidden_content(),
            ),
        ));
    }
    public static function get_plugin_data_removal_hidden_content()
    {
        $uninstall = self::get( 'uninstall', 'files' );
        if ( empty( $uninstall ) or !is_string( $uninstall ) )
        {
            $uninstall = 'files';
        }
        else
        {
            if ( strpos( 'files,', $uninstall ) !== 0 )
            {
                $uninstall = 'files,' . $uninstall;
            }
        }

        return UI::checkbox_group( array
        (
            'id'       => 'uninstall',
            'selected' => $uninstall,
            'options'  => array
            (
                array
                (
                    'value'    => 'files',
                    'label'    => __( "Plugin files", 'molongui-authorship' ),
                    'disabled' => true,
                ),
                array
                (
                    'value'    => 'config',
                    'label'    => __( "Plugin configuration", 'molongui-authorship' ),
                    'disabled' => false,
                ),
                array
                (
                    'value'    => 'data',
                    'label'    => __( "Plugin data", 'molongui-authorship' ),
                    'disabled' => false,
                ),
            ),
            'echo'     => false,
            'title'    => '',
            'class'    => 'molongui-ui-checkbox-group--vertical',
            'tooltip'  => __( "If you want to keep plugin files, don't remove the plugin, just deactivate it.", 'molongui-authorship' ),
        ));
    }

} // trait