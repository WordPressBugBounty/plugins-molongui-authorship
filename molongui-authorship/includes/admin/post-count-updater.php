<?php
/*!
 * Handles the background process for updating post count metadata for authors.
 *
 * This file contains logic to ensure that the post counts for authors, including guest authors, are updated accurately.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/admin
 * @since      4.0.7
 */

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Common\Libraries\WP_Background_Process;
use Molongui\Authorship\Author;
use Molongui\Authorship\Authors;
use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Guest_Author;
use Molongui\Authorship\Post;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Post_Count_Updater extends WP_Background_Process
{
    protected $prefix = 'molongui_authorship';
    protected $action = 'update_posts_count';
    protected $authors;
    protected $post_types;
    use Singleton;
    protected function __construct()
    {
        if ( apply_filters( 'molongui_authorship/enable_post_count_updater', true ) )
        {
            add_action( 'admin_init', array( $this, 'enable_admin_init' ) );
            add_action( 'admin_init', array( $this, 'enable_ajax_request' ) );
            add_action( 'admin_init', array( $this, 'enable_bulk_request' ) );
            add_action( 'admin_init', array( $this, 'enable_admin_action' ) );

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
        if ( get_option( 'molongui_authorship_update_post_counters' ) )
        {
            if ( get_option( 'molongui_authorship_update_post_authors', false ) or get_option( 'molongui_post_authorship_update_running', false ) )
            {
                add_action( 'admin_notices', function()
                {
                    $message = '<p>' . sprintf( __( '%sAuthorship Data Updater%s - Post counters update will run once the post authorship update process finishes.', 'molongui-authorship' ), '<strong>', '</strong>' ) . '</p>';
                    echo '<div class="notice notice-warning is-dismissible">' . $message . '</div>';
                });
            }
            else
            {
                delete_option( 'molongui_authorship_update_post_counters' );
                $this->authors = self::get_all_authors();
                $this->run();
            }
        }
    }
    public function enable_ajax_request()
    {
        add_action( "wp_ajax_molongui_authorship_update_post_count", array( $this, 'handle_ajax_request' ) );
        add_action( 'wp_ajax_authorship_update_counters', array( $this, 'handle_ajax_request' ) );
    }
    public function handle_ajax_request()
    {
        check_ajax_referer( 'molongui_authorship_post_count_updater_nonce', 'nonce', true );
        if ( apply_filters( 'authorship/check_wp_cron', true ) and defined( 'DISABLE_WP_CRON' ) and DISABLE_WP_CRON )
        {
            $result = 'cron_disabled';
        }
        else
        {
            $this->authors = self::get_all_authors();
            $result = $this->run();
        }
        echo json_encode( is_wp_error( $result ) ? 'false' : $result );
        wp_die();
    }
    public function enable_bulk_request()
    {
        if ( apply_filters( 'molongui_authorship/enable_bulk_count_update_for_users', true ) )
        {
            add_filter( "bulk_actions-users", array( $this, 'register_bulk_action' ) );
            add_filter( "handle_bulk_actions-users", array( $this, 'handle_bulk_action' ), 10, 3 );
        }

        if ( apply_filters( 'molongui_authorship/enable_bulk_count_update_for_guests', true ) )
        {
            add_filter( "bulk_actions-edit-guest_author", array( $this, 'register_bulk_action' ) );
            add_filter( "handle_bulk_actions-edit-guest_author", array( $this, 'handle_bulk_action' ), 10, 3 );
        }

        if ( apply_filters( 'molongui_authorship/enable_bulk_count_update_for_authors', true ) )
        {
        }
    }
    public function register_bulk_action( $bulk_actions )
    {
        $bulk_actions[$this->prefix . '_bulk_' . $this->action] = __( "Update Posts Count", 'molongui-authorship' );

        return $bulk_actions;
    }
    public function handle_bulk_action( $redirect_to, $doaction, $object_ids )
    {
        if ( $doaction === $this->prefix . '_bulk_' . $this->action )
        {
            if ( !empty( $object_ids ) )
            {
                $current_screen = get_current_screen();
                if ( 'users' === $current_screen->id )
                {
                    $author_type = 'user';
                }
                elseif ( 'edit-guest_author' === $current_screen->id )
                {
                    $author_type = 'guest';
                }
                else
                {
                    return $redirect_to;
                }
                $this->authors = self::prepare_authors( $object_ids, $author_type );
                $result = $this->run();
                $redirect_to = add_query_arg( 'update_count_bulk_action_running', count( $object_ids ), $redirect_to );
            }
        }

        return $redirect_to;
    }
    public function enable_admin_action()
    {
        if ( apply_filters( 'molongui_authorship/enable_row_count_update_for_users', true ) )
        {
            add_filter( 'user_row_actions', array( $this, 'register_row_action' ), 10, 2 );
        }
        if ( apply_filters( 'molongui_authorship/enable_row_count_update_for_guests', true ) )
        {
            add_filter( 'post_row_actions', array( $this, 'register_row_action' ), 10, 2 );
        }
        if ( apply_filters( 'molongui_authorship/enable_row_count_update_for_authors', true ) )
        {
            add_filter( 'molongui_authorship/author_row_actions', array( $this, 'register_row_action' ), 10, 2 );
        }
        add_action( 'admin_action_'.$this->prefix . '_' . $this->action, array( $this, 'handle_admin_action' ) );
    }
    public function register_row_action( $actions, $object )
    {
        if ( !empty( $object ) and is_a( $object, 'WP_User' ) )
        {
            $author_id   = $object->ID;
            $author_type = 'user';
        }
        elseif ( !empty( $object ) and is_a( $object, 'WP_Post' ) )
        {
            if ( Post::retrieve_post_type( $object ) === Guest_Author::get_post_type() )
            {
                $author_id   = $object->ID;
                $author_type = 'guest';
            }
            else
            {
                return $actions;
            }
        }
        elseif ( is_array( $object ) )
        {
            $author_id   = $object['id'];
            $author_type = $object['type'];
        }
        else
        {
            return $actions;
        }

        $actions[$this->prefix . '_' . $this->action] = '<a href="admin.php?action='.$this->prefix . '_' . $this->action.'&amp;author_id=' . $author_id . '&amp;author_type=' . $author_type . '" data-author-id="' . $author_id . '" data-author-type="' . $author_type . '" title="' . __( "Update posts count", 'molongui-authorship' ) . '" rel="permalink">' . __( "Update posts count", 'molongui-authorship' ) . '</a>';

        return $actions;
    }
    public function handle_admin_action()
    {
        if ( !( isset( $_GET['author_id'] ) or isset( $_POST['author_id'] ) or ( isset( $_REQUEST['action'] ) and $this->prefix.'_'.$this->action == $_REQUEST['action'] ) ) )
        {
            wp_die( __( "No author to update posts count for has been supplied!", 'molongui-authorship' ) );
        }
        $author_id   = ( isset( $_GET['author_id'] ) ? $_GET['author_id'] : $_POST['author_id'] );
        $author_type = ( isset( $_GET['author_type'] ) ? $_GET['author_type'] : $_POST['author_type'] );
        $this->authors = self::prepare_authors( array( $author_id ), $author_type );
        $result = $this->run();
        $redirect_url = remove_query_arg( array( 'action', 'author_id', 'author_type' ), wp_get_referer() );
        wp_redirect( esc_url( $redirect_url ) );
        exit;
    }
    public function handle_internal_request()
    {
        $this->authors = self::get_all_authors();
        $result = $this->run();
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

        foreach ( $this->post_types as $post_type )
        {
            if ( !empty( $this->authors ) )
            {
                foreach ( $this->authors as $author )
                {
                    $this->push_to_queue( array( 'post_type' => $post_type, 'author_id' => $author['id'], 'author_type' => $author['type'] ) );
                }

                $r = $this->save()->dispatch();
            }
        }

        return $r;
    }
    protected function task( $item )
    {
        self::update_author_post_counter( array( 'id' => $item['author_id'], 'type' => $item['author_type'] ), $item['post_type'] );
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

            $message = '<p>' . sprintf( __( "%sAuthorship Data Updater%s – The update process is now complete. All post counters have been updated.", 'molongui-authorship' ), '<strong>', '</strong>' ) . '</p>';
            echo '<div class="notice notice-success is-dismissible">' . $message . '</div>';
        }
        elseif ( get_option( $this->prefix . '_' . $this->action . '_running' ) )
        {
            $message = '<p>' . sprintf( __( "%sAuthorship Data Updater%s – The post counter update process is running in the background and may take some time to complete. Please be patient. Refresh the page to check the status of the process.", 'molongui-authorship' ), '<strong>', '</strong>' ) . '</p>';
            echo '<div class="notice notice-warning is-dismissible">' . $message . '</div>';
        }
    }
    public static function get_all_authors()
    {
        return Authors::get_authors();
    }
    public static function prepare_authors( $ids = null, $type = 'user' )
    {
        $authors = array();

        if ( !empty( $ids ) )
        {
            foreach( $ids as $id )
            {
                $authors[] = array( 'id' => $id, 'type' => $type, 'ref' => $type.'-'.$id, 'name' => '' );
            }
        }

        return $authors;
    }
    public static function get_post_types()
    {
        $post_types = apply_filters( 'molongui_authorship/post_types_for_count_updater', array() );

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
    public static function update_author_post_counter( $author, $post_type, $count = null )
    {
        $_author = new Author( $author['id'], $author['type'] );

        if ( !isset( $count ) )
        {
            $count = $_author->get_post_count( $post_type, false );
        }
        $_author->update_post_type_count( $count, $post_type );
    }
    public static function increment_counter( $post_type = 'post', $post_authors = null )
    {
        if ( empty( $post_authors ) )
        {
            return;
        }
        if ( is_string( $post_authors ) )
        {
            $parts = explode( '-', $post_authors );

            if ( isset( $parts[0] ) and $parts[0] === $post_authors )
            {
                return;
            }

            $post_authors          = array();
            $post_authors[0]       = new \stdClass();
            $post_authors[0]->id   = $parts[1];
            $post_authors[0]->type = $parts[0];
        }

        foreach ( $post_authors as $post_author )
        {
            $author = new Author( $post_author->id, $post_author->type );
            $count  = $author->get_post_count( $post_type );
            self::update_author_post_counter( array( 'id' => $post_author->id, 'type' => $post_author->type ), $post_type, $count + 1 );
        }
    }
    public static function decrement_counter( $post_type, $post_authors )
    {
        if ( empty( $post_authors ) )
        {
            return;
        }
        if ( is_string( $post_authors ) )
        {
            $parts = explode( '-', $post_authors );

            if ( isset( $parts[0] ) and $parts[0] === $post_authors )
            {
                return;
            }

            $post_authors          = array();
            $post_authors[0]       = new \stdClass();
            $post_authors[0]->id   = $parts[1];
            $post_authors[0]->type = $parts[0];
        }

        foreach ( $post_authors as $post_author )
        {
            $author = new Author( $post_author->id, $post_author->type );
            $count  = $author->get_post_count( $post_type );
            self::update_author_post_counter( array( 'id' => $post_author->id, 'type' => $post_author->type ), $post_type, $count - 1 );
        }
    }

} // class
Post_Count_Updater::instance();
