<?php

namespace Molongui\Authorship\Common\Modules\Settings;

use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
trait Settings_Page
{
    use Options;
    public static function is_settings_page()
    {
        $current_screen = get_current_screen();
        return ( strpos( $current_screen->id, MOLONGUI_AUTHORSHIP_NAME ) );
    }
    public static function render()
    {
        $common_options = self::get_options();
        $plugin_options = apply_filters( 'authorship/plugin_settings', array() );
        $settings = array_merge_recursive( $plugin_options, (array)$common_options );
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
        Assets::enqueue_semantic();
        Assets::enqueue_sweetalert();
        $deps = apply_filters( 'authorship/options/script_deps', array() );
        $file = apply_filters( 'authorship/options/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/common/options.xxxx.min.js' );

        Assets::register_script( $file, 'options', $deps );
    }
    public static function enqueue_scripts()
    {
        $file = apply_filters( 'authorship/options/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/common/options.xxxx.min.js' );
        if ( !empty( $deps ) )
        {
            add_filter( "mpb/options/inline_script", '__return_false' );
        }

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
                'custom_css' => wp_enqueue_code_editor( array( 'type' => 'text/css', 'codemirror' => array( 'mode' => 'css', 'autofocus' => true ) ) ),
                'custom_php' => wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php', 'codemirror' => array( 'mode' => 'php', 'autofocus' => true ) ) ),
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
        $file = apply_filters( 'authorship/options/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/common/modules/settings/assets/css/styles-rtl.fcde.min.css' : '/common/modules/settings/assets/css/styles.1af2.min.css' ) );
        $deps = array();

        Assets::register_style( $file, 'options', $deps );
    }
    public static function enqueue_styles()
    {
        $file = apply_filters( 'authorship/options/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/common/modules/settings/assets/css/styles-rtl.fcde.min.css' : '/common/modules/settings/assets/css/styles.1af2.min.css' ) );

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
        $tidio_url = Helpers::get_tidio_url();

        ob_start(); ?>

        <h2 class="m-section-title"><?php esc_html_e( "Need help? Let us know and we will be happy to assist.", 'molongui-authorship' ); ?></h2>

        <!-- Docs -->
        <div class="m-card m-card-header">
            <div class="m-card-header__label">
                <span class="m-card-header__label-text"><?php esc_html_e( "Plugin Documentation", 'molongui-authorship' ); ?></span>
            </div>
            <div class="m-card-header__actions">
                <a href="<?php echo 'https://www.molongui.com/help/docs/'; ?>" target="_blank" type="button" class="m-button is-compact is-primary same-width"><?php esc_html_e( "Read Docs", 'molongui-authorship' ); ?></a>
            </div>
        </div>
        <div class="m-card">
            <div>
                <?php esc_html_e( "Learn the basics to help you make the most of Molongui plugins.", 'molongui-authorship' ); ?>
            </div>
        </div>

        <!-- Open Ticket -->
        <div class="m-card m-card-header">
            <div class="m-card-header__label">
                <span class="m-card-header__label-text"><?php esc_html_e( "Open a Support Ticket", 'molongui-authorship' ); ?></span>
            </div>
            <div class="m-card-header__actions">
                <a href="<?php echo 'https://www.molongui.com/help/support/'; ?>" target="_blank" type="button" class="m-button is-compact same-width"><?php esc_html_e( "Get Support", 'molongui-authorship' ); ?></a>
            </div>
        </div>
        <div class="m-card">
            <div>
                <?php
                /*! // translators: %1$s: Opening i tag. %2$s: Closing i tag. */
                printf( esc_html__( "Documentation didn't help? Submit a ticket below and get help from our friendly and knowledgeable %sMolonguis%s. We reply to every ticket, please check your Spam folder if you haven't heard from us.", 'molongui-authorship' ), '<i>', '</i>');
                ?>
            </div>
        </div>
        <div class="m-card">
            <div>
                <form id="molongui-help-ticket-form">
                    <div id="molongui-form-error" class="hidden"><?php esc_html_e( "All fields are mandatory", 'molongui-authorship' ); ?></div>
                    <p>
                        <label for="your-name"><?php esc_html_e( "Name", 'molongui-authorship' ); ?></label>
                        <input type="text" name="your-name" required placeholder="<?php esc_attr_e( "Your name here", 'molongui-authorship' ); ?>">
                    </p>
                    <p>
                        <label for="your-email"><?php esc_html_e( "Email", 'molongui-authorship' ); ?></label>
                        <input type="email" name="your-email" required placeholder="<?php esc_attr_e( "Your e-mail here", 'molongui-authorship' ); ?>">
                    </p>
                    <p>
                        <label for="your-subject"><?php esc_html_e( "Subject", 'molongui-authorship' ); ?></label>
                        <input type="text" name="your-subject" required placeholder="<?php esc_attr_e( "Brief issue description", 'molongui-authorship' ); ?>">
                    </p>
                    <p>
                        <label for="plugin"><?php esc_html_e( "Plugin", 'molongui-authorship' ); ?></label>
                        <select name="plugin" required>
                            <option value="">---</option>
                            <option value="Molongui Authorship">Molongui Post Authors and Author Box</option>
                            <option value="Molongui Contributors">Molongui Post Contributors</option>
                            <option value="Molongui Deals, Sales Promotions and Upsells for WooCommerce">Molongui Order Bump for WooCommerce</option>
                        </select>
                    </p>
                    <p>
                        <label for="your-message"><?php esc_html_e( "Message", 'molongui-authorship' ); ?></label>
                        <textarea name="your-message" cols="40" rows="7" required placeholder="<?php esc_attr_e( "Explain your issue providing a URL we can check", 'molongui-authorship' ); ?>"></textarea>
                    </p>
                    <p><input type="checkbox" id="molongui-accept-tos" name="molongui-accept-tos" value="1">
                        <?php
                        /*! // translators: %1$s: Opening a tag. %2$s: Closing a tag. */
                        printf( esc_html__( "I have read and accept the %sprivacy policy%s.", 'molongui-authorship' ), '<a href="https://www.molongui.com/privacy/">', '</a>' );
                        ?>
                    </p>
                    <p class="hidden"><input type="hidden" name="ticket-id" value="<?php echo esc_attr( 'HR'.gmdate('y').'-'.gmdate('mdHis') ); ?>"></p>
                    <button type="submit" id="molongui-submit-ticket" class="m-button is-compact is-primary"><?php esc_html_e( "Open Support Ticket", 'molongui-authorship' ); ?></button>
                </form>
            </div>
        </div>

        <!-- Live Chat -->
        <div class="m-card m-card-header">
            <div class="m-card-header__label">
                <span class="m-card-header__label-text"><?php esc_html_e( "Live Support", 'molongui-authorship' ); ?></span>
            </div>
            <div class="m-card-header__actions">
                <a href="<?php echo esc_url( $tidio_url ); ?>" target="_blank" type="button" class="m-button is-compact same-width"><?php esc_html_e( "Open Chat", 'molongui-authorship' ); ?></a>
            </div>
        </div>
        <div class="m-card">
            <div>
                <?php esc_html_e( "Need answers and documentation didn't help? Chat with us. You can open the chat by clicking either on the link below, on the button above or on the floating dark button on the bottom right.", 'molongui-authorship' ); ?>
            </div>
            <div>
                <ul class="m-list">
                    <li><span class="dashicons dashicons-translation"></span>
                        <?php
                        /*! // translators: %1$s: Opening strong tag. %2$s: Closing strong tag. %3$s: Opening strong tag. %4$s: Closing strong tag. */
                        printf( esc_html__( "We speak %sEnglish%s and %sSpanish%s", 'molongui-bump-offer' ), '<strong>', '</strong>', '<strong>', '</strong>' ); ?></li>
                    <li><span class="dashicons dashicons-clock"></span>
                        <?php
                        /*! // translators: %1$s: Opening strong tag. %2$s: Closing strong tag. */
                        printf( esc_html__( "We answer Monday to Friday, from 9 AM to 5 PM (%sCentral European Time%s)", 'molongui-bump-offer' ), '<strong>', '</strong>' );
                        ?>
                    </li>
                    <li><span class="dashicons dashicons-email"></span><?php esc_html_e( "If offline, please leave your email address so we can get in touch with you", 'molongui-bump-offer' ); ?></li>
                </ul>
            </div>
        </div>
        <a class="m-card is-card-link is-compact" target="_blank" href="<?php echo esc_url( $tidio_url ); ?>" title="<?php esc_attr_e( "Click to open Live Chat", 'molongui-authorship' ); ?>">
            <svg class="gridicon gridicons-external m-card__link-indicator" height="24" width="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g><path d="M19 13v6c0 1.105-.895 2-2 2H5c-1.105 0-2-.895-2-2V7c0-1.105.895-2 2-2h6v2H5v12h12v-6h2zM13 3v2h4.586l-7.793 7.793 1.414 1.414L19 6.414V11h2V3h-8z"></path></g></svg>
            <?php esc_html_e( "Open Live Support Chat", 'molongui-authorship' ); ?>
        </a>

        <!-- Send Report -->
        <div class="m-card m-card-header">
            <div class="m-card-header__label">
                <span class="m-card-header__label-text"><?php esc_html_e( "System Status Report", 'molongui-authorship' ); ?></span>
            </div>
            <div class="m-card-header__actions">
                <a id="send-molongui-support-report" type="button" class="m-button is-compact is-primary same-width"><?php esc_html_e( "Send Report", 'molongui-authorship' ); ?></a>
            </div>
        </div>
        <div class="m-card">
            <div>
                <?php esc_html_e( "Sometimes we may ask you to send us your system status report so we can get a better knowledge of your installation.", 'molongui-authorship' ); ?>
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

} // trait