<?php
/*!
 * Handles the background process for updating post authorship metadata.
 *
 * This file defines the logic for updating post authorship metadata, such as guest and co-authors, ensuring the correct
 * postmeta fields are added for each post to support accurate author archive pages and queries.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/admin
 * @since      4.5.0
 */

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Common\Libraries\WP_Background_Process;
use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Post;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Post_Author_Updater extends WP_Background_Process
{
    public $prefix = 'molongui_authorship';
    public $action = 'update_post_authorship';
    protected $post_types;
    use Singleton;
    protected function __construct()
    {
        if ( apply_filters( 'molongui_authorship/enable_post_author_updater', true ) )
        {
            add_action( 'admin_init', array( $this, 'enable_admin_init' ), 9 );
            add_action( 'admin_init', array( $this, 'enable_ajax_request' ) );

            add_action( 'admin_notices', array( $this, 'task_status_notice' ) );

            parent::__construct();
        }
    }
    public function enable_admin_init()
    {
        $this->handle_admin_init();
    }
    public function handle_admin_init()
    {
        if ( get_option( 'molongui_authorship_update_post_authors' ) )
        {
            delete_option( 'molongui_authorship_update_post_authors' );
            $this->run();
        }
    }
    public function enable_ajax_request()
    {
        add_action( "wp_ajax_molongui_authorship_update_post_authors", array( $this, 'handle_ajax_request' ) );
    }
    public function handle_ajax_request()
    {
        check_ajax_referer( 'molongui_authorship_post_author_updater_nonce', 'nonce', true );
        if ( apply_filters( 'authorship/check_wp_cron', true ) and defined( 'DISABLE_WP_CRON' ) and DISABLE_WP_CRON )
        {
            $result = 'cron_disabled';
        }
        else
        {
            $result = $this->run();
        }
        echo json_encode( is_wp_error( $result ) ? 'false' : $result );
        wp_die();
    }
    public function run()
    {
        if ( apply_filters( 'authorship/check_wp_cron', true ) )
        {
            if ( defined( 'DISABLE_WP_CRON' ) and DISABLE_WP_CRON )
            {
                return false;
            }
        }

        $r = true;
        $this->post_types = self::get_post_types();
        $post_ids = get_posts( array
        (
            'numberposts'      => -1,
            'meta_key'         => '_molongui_author',
            'meta_compare'     => 'NOT EXISTS',
            'post_type'        => $this->post_types,
            'suppress_filters' => true,
            'post_status' => Post::get_public_post_status( $this->post_types ),
            'fields' => 'ids',
        ));

        if ( !empty( $post_ids ) )
        {
            foreach ( $post_ids as $post_id )
            {
                $this->push_to_queue( $post_id );
            }

            $r = $this->save()->dispatch();
        }

        return $r;
    }
    protected function task( $post_id )
    {
        self::set_default_authorship_meta( $post_id );
        return false;
    }
    public function dispatch()
    {
        $result = parent::dispatch();

        if ( !is_wp_error( $result ) )
        {
            add_option( $this->prefix . '_' . $this->action . '_running', true, '', true );
        }

        return $result;
    }
    protected function complete()
    {
        parent::complete();
        add_option( $this->prefix . '_' . $this->action . '_complete', true, '', true );
    }
    public function task_status_notice()
    {
        if ( get_option( $this->prefix . '_' . $this->action . '_complete' ) )
        {
            delete_option( $this->prefix . '_' . $this->action . '_complete' );
            delete_option( $this->prefix . '_' . $this->action . '_running' );

            $message = '<p>' . sprintf( __( "%sAuthorship Data Updater%s – The update process is complete. All posts now contain the necessary authorship information.", 'molongui-authorship' ), '<strong>', '</strong>' ) . '</p>';
            echo '<div class="notice notice-success is-dismissible">' . $message . '</div>';
        }
        elseif ( get_option( $this->prefix . '_' . $this->action . '_running' ) )
        {
            $message = '<p>' . sprintf( __( "%sAuthorship Data Updater%s – The post authorship update process is running in the background and may take some time to complete. Please be patient. Refresh the page to check the status of the process.", 'molongui-authorship' ), '<strong>', '</strong>' ) . '</p>';
            echo '<div class="notice notice-warning is-dismissible">' . $message . '</div>';
        }
    }
    public static function get_post_types()
    {
        $post_types = apply_filters( 'molongui_authorship/post_types_for_authorship_updater', array() );

        if ( empty( $post_types ) )
        {
            $post_types = 'enabled';
        }

        if ( is_string( $post_types ) )
        {
            switch ( $post_types )
            {
                case 'all':
                    $post_types = Post::get_post_types();
                    break;

                case 'enabled':
                    $post_types = Settings::enabled_post_types();
                    break;

                default:
                    $post_types = array( $post_types );
                    break;
            }
            if ( empty( $post_types ) )
            {
                $post_types = array( 'post', 'page' );
            }
            else
            {
                $post_types = array_unique( array_merge( $post_types, array( 'post', 'page' ) ) );
            }
        }

        return $post_types;
    }
    public static function set_default_authorship_meta( $post_id )
    {
        if ( empty( $post_id ) )
        {
            return false;
        }

        $post_id     = (int) $post_id;
        $post_author = get_post_field( 'post_author', $post_id );

        $r1 = update_post_meta( $post_id, '_molongui_main_author', 'user-'.$post_author );
        $r2 = update_post_meta( $post_id, '_molongui_author', 'user-'.$post_author );

        if ( empty( $r1 ) or empty( $r2 ) )
        {
            return false;
        }
        else
        {
            return true;
        }
    }

} // class
Post_Author_Updater::instance();