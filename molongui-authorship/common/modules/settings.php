<?php

namespace Molongui\Authorship\Common\Modules;

use Molongui\Authorship\Common\Modules\Settings\Settings_Page;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Common\Utils\Request;
use Molongui\Authorship\Common\Utils\User;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Settings
{
    use Settings_Page;
    public function __construct()
    {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );
        add_filter( 'authorship/options_script_params', array( __CLASS__, 'localize_scripts' ), 10 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_styles' ) );
        add_filter( 'authorship/options_extra_styles', array( __CLASS__, 'extra_styles' ) );
        add_action( 'authorship/options', array( __CLASS__, 'add_defaults' ) );
        add_filter( 'authorship/sanitize_option', array( $this, 'custom_snippets_input' ), 10, 3 );
        add_action( 'wp_ajax_'.MOLONGUI_AUTHORSHIP_PREFIX.'_save_options', array( $this, 'save' ) );
        add_action( 'wp_ajax_'.MOLONGUI_AUTHORSHIP_PREFIX.'_export_options', array( $this, 'export' ) );
        add_action( 'wp_ajax_molongui_send_mail', array( $this, 'send_mail' ) );
        add_action( 'plugins_loaded', array( $this, 'load_custom_snippets' ), PHP_INT_MAX );
    }
    public static function get( $id = null, $default = false )
    {
        $settings = (array) get_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', array() );

        if ( empty( $settings ) )
        {
            $settings = self::get_defaults();
        }
        $settings = apply_filters( 'authorship/get_options', $settings );

        if ( !empty( $id ) )
        {
            if ( isset( $settings[$id] ) )
            {
                return $settings[$id];
            }
            else
            {
                return $default;
            }
        }
        else
        {
            return $settings;
        }
    }
    public static function get_defaults()
    {
        $fw_options = array
        (
            'custom_css'  => '',
            'custom_php'  => '',
            'keep_config' => true,
            'keep_data'   => true,
        );

        return apply_filters( 'authorship/default_options', $fw_options );
    }
    public static function add_defaults()
    {
        $options  = self::get();
        $defaults = self::get_defaults();
        update_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', array_merge( $defaults, $options ), true );
    }
    public static function get_config()
    {
        global $wpdb;
        $entries = $wpdb->get_results // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        (
            $wpdb->prepare( "SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s", MOLONGUI_AUTHORSHIP_PREFIX.'_%' ),
            ARRAY_A
        );

        if ( !empty( $entries ) )
        {
            $options = array();
            foreach ( $entries as $entry ) $options[$entry['option_name']] = maybe_unserialize( $entry['option_value'] );
        }

        return empty( $options ) ? false : $options;
    }
    public function save()
    {
        if ( !WP::verify_nonce( 'mfw_save_options_nonce', 'nonce' ) )
        {
            echo 'false';
            wp_die();
        }
        if ( !current_user_can( 'manage_options' ) )
        {
            return;
        }
        $options = wp_unslash( $_POST['data'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        foreach ( $options as $key => $value )
        {
            $options[$key] = apply_filters( 'authorship/sanitize_option', sanitize_text_field( $value ), $key, $value );
        }

        if ( isset( $options ) and is_array( $options ) )
        {
            $options['plugin_version'] = MOLONGUI_AUTHORSHIP_VERSION;
            $current = (array) get_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', array() );
            $options = array_merge( $current, $options );
            $options = apply_filters( 'authorship/validate_options', $options, $current );
            update_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', $options, true );

            $old = $current;
            do_action( 'authorship/options', $options, $old );
        }
        wp_die();
    }
    public function export()
    {
        if ( !WP::verify_nonce( 'mfw_export_options_nonce', 'nonce' ) )
        {
            echo 'false';
            wp_die();
        }
        if ( !current_user_can( 'manage_options' ) )
        {
            return;
        }
        $options = self::get_config();
        $options['plugin_id']      = MOLONGUI_AUTHORSHIP_PREFIX;
        $options['plugin_version'] = MOLONGUI_AUTHORSHIP_VERSION;
        $options = apply_filters( 'authorship/export_options', $options );
        echo wp_json_encode( $options );
        wp_die();
    }
    public function send_mail()
    {
        if ( !is_admin() and !isset( $_POST['form'] ) and $_POST['type'] == 'ticket' )
        {
            echo 'Missing data';
            wp_die();
        }
        check_ajax_referer( 'molongui-support-nonce', 'security', true );
        switch( $_POST['type'] )
        {
            case 'ticket':
                $params = array();
                parse_str( $_POST['form'], $params );
                /*! // translators: %1$s: Ticket ID. %2$s: Ticket subject. */
                $subject = sprintf( esc_html__( "Support Ticket %s: %s", 'molongui-authorship' ), sanitize_text_field( $params['ticket-id'] ), sanitize_text_field( $params['your-subject'] ) );
                $message = esc_html( sanitize_textarea_field( $params['your-message'] ) );
                $headers = array
                (
                    'From: '         . $params['your-name'] . ' <' . $params['your-email'] . '>',
                    'Reply-To: '     . $params['your-name'] . ' <' . $params['your-email'] . '>',
                    'Content-Type: ' . 'text/html; charset=UTF-8',
                );
                $message .= '<br><br>---<br><br>';
                /*! // translators: %s: Current site's URL. */
                $message .= '<small>'.sprintf( esc_html__( "This support ticket was sent using the form on the Support Page (%s)", 'molongui-authorship' ), $_POST['domain'] ).'</small>';
                $message .= '<br><br><hr><br><br>';

                $user = array( 'name' => $params['your-name'], 'mail' => $params['your-email'] );

                break;

            case 'report':
                $current_user = wp_get_current_user();

                $from = 'noreply@' . sanitize_text_field( $_POST['domain'] );
                $from = filter_var( $from, FILTER_VALIDATE_EMAIL ) ? $from : $current_user->user_email;
                /*! // translators: %s: Current site's domain. */
                $subject = sprintf( esc_html__( "Support Report for %s", 'molongui-authorship' ), sanitize_text_field( $_POST['domain'] ) );
                $message = '';
                $headers = array
                (
                    'From: ' . $current_user->display_name . ' <' . $from . '>',
                    'Reply-To: ' . $current_user->display_name . ' <' . $current_user->user_email . '>',
                    'Content-Type: ' . 'text/html; charset=UTF-8',
                );

                $user = array( 'name' => $current_user->user_firstname, 'mail' => $current_user->user_email );

                break;
        }
        if ( apply_filters( 'molongui/support/add_debug_data', true ) )
        {
            $message .= self::get_mail_appendix();
        }
        add_action( 'wp_mail_failed', function( $wp_error )
        {
            wp_die( esc_html( $wp_error->errors['wp_mail_failed']['0'] ) );
        });
        $sent = wp_mail( 'support@molongui.com', $subject, $message, $headers );
        if ( $sent and !empty( $user ) ) self::mail_autorespond( $user );
        echo( $sent ? 'Email sent' : 'wp_mail failed' );
        wp_die();
    }
    public static function get_mail_appendix()
    {
        $appendix = '';
        global $current_user;
        $data   = Debug::get_debug_data( false );
        $client = User::get_browser_data();
        $css_title    = 'font-size: 14px; font-weight: bold;';
        $css_subtitle = 'font-size: 13px; font-weight: bold; color: #4a4a4a; margin-left: 20px;';
        $css_item     = 'font-size: 12px; font-family: consolas; margin-left: 20px;';
        $css_detail   = 'font-size: 11px; color: #b0b0b0;';
        $css_table_1  = 'border-collapse: collapse; border: 1px solid lightgray; margin-left: 20px;';
        $css_table_2  = 'border-collapse: collapse; border: 1px solid lightgray; width: 100%';
        $css_tr       = 'border: 1px solid lightgray;';
        $css_td_head  = 'font-size: 12px; font-family: consolas; font-weight: bold; border: 1px solid lightgray; background: lightgray;';
        $css_td_title = 'font-size: 12px; font-family: consolas; font-weight: bold; border: 1px solid lightgray; width: 240px;';
        $css_td_value = 'font-size: 12px; font-family: consolas; border: 1px solid lightgray;';
        $appendix .= '<p style="'.$css_title.'">'.__( "Molongui Plugins", 'molongui-authorship' ).'</p>';
        $molonguis = Plugin::get_molonguis();
        foreach( $molonguis as $plugin )
        {
            $appendix .= '<p style="'.$css_item.'">'. esc_html( $plugin['Name'] ) . ' ' . '<span style="'.$css_detail.'">' . esc_html( $plugin['Version'] ) . '</span>' . '</p>';
        }
        $appendix .= '<p style="'.$css_title.'">' . $data['wp-plugins-active']['label'] . '</p>';
        foreach( $data['wp-plugins-active']['fields'] as $plugin )
        {
            $appendix .= '<p style="'.$css_item.'">' . $plugin['label'] . ' ' . '<span style="'.$css_detail.'">' . $plugin['value'] . '</span>' . '</p>';
        }
        $appendix .= '<p style="'.$css_title.'">' . $data['wp-active-theme']['label'] . '</p>';
        $appendix .= '<p style="'.$css_item.'">' . $data['wp-active-theme']['fields']['name']['value'] . ' ' . '<span style="'.$css_detail.'">' . $data['wp-active-theme']['fields']['version']['value'] .  ' by ' . $data['wp-active-theme']['fields']['author']['value'] . '</span>' . '</p>';
        $appendix .= '<p style="'.$css_title.'">'.__( "Client Browser", 'molongui-authorship' ).'</p>';
        $appendix .= '<p style="'.$css_item.'">'.$client['browser'].' on '.$client['platform'].'</p>';
        $appendix .= '<p style="'.$css_title.'">'.__( "Current User", 'molongui-authorship' ).'</p>';
        $appendix .= '<p style="'.$css_item.'">'.$current_user->display_name.' with registered e-mail '.$current_user->user_email.'</p>';
        $appendix .= '<p style="'.$css_title.'">'.__( "System Report", 'molongui-authorship' ).'</p>';
        $appendix .= '<p style="'.$css_item.'">'.nl2br( Debug::get_debug_data() ).'</p>';
        $appendix .= '<p style="'.$css_title.'">'.__( "Plugin Settings", 'molongui-authorship' ).'</p>';
        foreach ( $molonguis as $plugin )
        {
            if ( false !== strpos( $plugin['Name'], ' Pro' ) ) continue;

            $options = Settings::get_config();
            $appendix .= '<p style="'.$css_subtitle.'">' . esc_html( $plugin['Name'] ). ' Options' . '</p>';
            $appendix .= '<table style="'.$css_table_1.'">';
            foreach ( $options as $option_group => $values )
            {
                $appendix .= '<tr style="'.$css_tr.'"><td style="'.$css_td_head.'">'.$option_group.'</td></tr>';

                $appendix .= '<tr style="'.$css_tr.'">';
                if ( !is_array( $values ) )
                {
                    $appendix .= '<td style="'.$css_td_value.'">'.$values.'</td>';
                }
                else
                {
                    if ( false !== strpos( $option_group, '_cache_' ) and is_array( $values ) )
                    {
                        $count = count( $values );
                        /*! // translators: %s: Number of keys. */
                        $appendix .= '<td style="'.$css_td_value.'">'. sprintf( _n( '%s key', '%s keys', $count, 'text-domain' ), number_format_i18n( $count ) ) .'</td>';
                    }
                    else
                    {
                        $appendix .= '<td style="'.$css_td_value.'">';
                        $appendix .= '<table style="'.$css_table_2.'">';
                        foreach ( $values as $key => $item )
                        {
                            $item = is_array( $item ) ? wp_json_encode( $item ) : $item;

                            $appendix .= '<tr style="'.$css_tr.'">';
                            $appendix .= '<td style="'.$css_td_title.'">'.$key.'</td>';
                            $appendix .= '<td style="'.$css_td_value.'">'.$item.'</td>';
                            $appendix .= '</tr>';
                        }
                        $appendix .= '</table>';
                        $appendix .= '</td>';
                    }
                }
                $appendix .= '</tr>';
            }
            $appendix .= '</table>';
        }

        return $appendix;
    }
    public static function mail_autorespond( $user )
    {
        $subject = __( "We got your email! Hang tight!", 'molongui-authorship' );
        /*! // translators: %1$s: User name. %2$s: Line breaks. */
        $message = sprintf( esc_html__( "Hi %s! %s This is an automatic email just to let you know we've got your help request . We'll get you an answer back shortly.", 'molongui-authorship' ), $user['name'], '<br><br>' );
        $headers = array
        (
            'From: Molongui Support <support@molongui.com>',
            'Reply-To: Molongui Support <support@molongui.com>',
            'Content-Type: text/html; charset=UTF-8',
        );
        $sent = wp_mail( $user['mail'], $subject, $message, $headers );
        return $sent;
    }
    public static function custom_snippets_input( $sanitized_text_field, $key, $value )
    {
        $dont_sanitize = array( 'custom_css', 'custom_php' );
        if ( in_array( $key, $dont_sanitize ) )
        {
            return $value;
        }

        return $sanitized_text_field;
    }
    public static function get_custom_css()
    {
        return apply_filters( 'authorship/custom_css', self::get( 'custom_css' ) );
    }
    public static function get_custom_php()
    {
        return apply_filters( 'authorship/custom_php', self::get( 'custom_php' ) );
    }
    public function load_custom_snippets()
    {
        if ( Request::get( 'nophpAuthorship' ) )
        {
            return;
        }
        $load_on_admin = ( apply_filters( 'authorship/enable_custom_php_in_admin', false ) or Request::get( 'phpAuthorship' ) or Settings::get( 'enable_custom_php_in_admin' ) );
        if ( is_admin() and !$load_on_admin )
        {
            return;
        }

        $custom_php = self::get_custom_php();
        $custom_php = Helpers::clean_php( $custom_php );

        if ( !empty( $custom_php ) )
        {
            $custom_php = trim( $custom_php );
            $tag = '<?php';
            if ( 0 === strpos( $custom_php, $tag ) )
            {
                $custom_php = substr( $custom_php, strlen( $tag ) );
            }

            if ( !empty( $custom_php ) )
            {
                eval( $custom_php ); // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
                Debug::console_log( null, __( "Custom PHP snippets loaded." ) );
            }
        }
    }

} // class