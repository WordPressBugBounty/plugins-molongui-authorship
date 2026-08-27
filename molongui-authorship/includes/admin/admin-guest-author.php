<?php
/*!
 * Handles guest author functionality within the Molongui Authorship plugin.
 *
 * This file defines the features related to guest authors, including creating, retrieving, and managing guest authors
 * in a WordPress environment. It provides utility functions for handling guest author data in posts and extends the
 * functionality of traditional WordPress authorship by allowing non-registered users to be associated with content.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/admin
 * @since      5.0.0
 */

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Author;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Common\Utils\WP;
use Molongui\Authorship\Guest_Author;
use Molongui\Authorship\Settings;
use Molongui\Authorship\Social;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Admin_Guest_Author
{
    private $post_type;
    private $javascript     = '/assets/js/edit-guest.xxxx.min.js';
    private $stylesheet     = '';
    private $stylesheet_ltr = '/assets/js/edit-guest.xxxx.min.css';
    private $stylesheet_rtl = '/assets/js/edit-guest-rtl.xxxx.min.css';
    public function __construct()
    {
        if ( Settings::is_enabled( 'guest-author' ) )
        {
            $this->post_type = Guest_Author::get_post_type();
            add_filter( 'post_updated_messages', array( $this, 'custom_messages' ) );
            add_action( 'admin_menu', array( $this, 'remove_menu_item' ) );
            add_filter( 'wp_insert_post_data', array( $this, 'filter_cpt_title' ), PHP_INT_MAX, 2 );
            add_action( 'save_post_'.$this->post_type, array( $this, 'on_save' ) );
            add_filter( 'removable_query_args', array( $this, 'add_removable_arg' ) );
            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
            add_action( 'delete_post', array( $this, 'on_delete' ) );
            add_action( 'deleted_post', array( $this, 'on_deleted' ), 10, 2 );
            add_action( 'transition_post_status', array( $this, 'maybe_update_guest_count' ), 10, 3 );
            $this->set_assets();
            add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
            add_filter( "authorship/edit_guest_script_params", array( $this, 'localize_admin_scripts' ) );
            add_filter( 'views_edit-'.$this->post_type, array( $this, 'remove_mine_filter' ) );
            add_filter( 'manage_'.$this->post_type.'_posts_columns', array( $this, 'add_list_columns' ) );
            add_action( 'manage_'.$this->post_type.'_posts_custom_column', array( $this, 'fill_list_columns' ), 5, 2 );
            add_filter( 'post_row_actions', array( $this, 'remove_view_link' ) );
            add_action( 'admin_head', array( $this, 'quick_edit_add_title_field' ) );
            add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_add_custom_fields' ), 10, 2 );
            add_action( 'admin_footer', array( $this, 'quick_edit_populate_custom_fields' ) );
            add_action( 'save_post_'.$this->post_type, array( $this, 'quick_edit_save_custom_fields' ), 10, 2 );
            add_filter( 'bulk_actions-'.'edit-'.$this->post_type, array( $this, 'remove_bulk_edit_action' ) );
            add_action( 'admin_head', array( $this, 'remove_media_buttons' ) );
            add_action( 'admin_head', array( $this, 'remove_preview_button' ) );
            add_action( 'edit_form_after_title', array( $this, 'add_top_section_after_title' ) );
            add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
            add_filter( 'authorship/admin/guest/convert/metabox', array( $this, 'hide_convert_metabox' ), 9 );
            add_filter( 'postbox_classes_'.$this->post_type.'_authorconversiondiv', array( $this, 'add_conversion_metabox_class' ) );
            add_filter( 'postbox_classes_'.$this->post_type.'_authorshortbiodiv', array( $this, 'add_short_bio_metabox_class' ) );
        }
    }
    public function set_assets()
    {
        $this->stylesheet = MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? $this->stylesheet_rtl : $this->stylesheet_ltr );
        $this->stylesheet = apply_filters( 'authorship/edit_guest/styles', $this->stylesheet );
        $this->javascript = MOLONGUI_AUTHORSHIP_FOLDER . $this->javascript;
        $this->javascript = apply_filters( 'authorship/edit_guest/script', $this->javascript );
    }
    public function custom_messages( $msg )
    {
        $msg[$this->post_type] = array
        (
            0  => '',                                                   // Unused. Messages start at index 1.
            1  => __( "Guest author updated.", 'molongui-authorship' ),
            2  => "Custom field updated.",                              // Probably better do not touch
            3  => "Custom field deleted.",                              // Probably better do not touch
            4  => __( "Guest author updated.", 'molongui-authorship' ),
            5  => __( "Guest author restored to revision", 'molongui-authorship' ),
            6  => __( "Guest author published.", 'molongui-authorship' ),
            7  => __( "Guest author saved.", 'molongui-authorship' ),
            8  => __( "Guest author submitted.", 'molongui-authorship' ),
            9  => __( "Guest author scheduled.", 'molongui-authorship' ),
            10 => __( "Guest author draft updated.", 'molongui-authorship' ),
        );

        return $msg;
    }
    public function remove_menu_item()
    {
        $menu_level = Settings::get( 'dashboard_guest_authors_menu_location' );

        $slug = 'edit.php?post_type='.$this->post_type;

        if ( !current_user_can( 'edit_others_pages' ) and !current_user_can( 'edit_others_posts' ) )
        {
            if ( 'top' !== $menu_level )
            {
                if ( 'users.php' === $menu_level )
                {
                    $menu_level = 'profile.php';
                }

                remove_submenu_page( $menu_level, $slug );
            }
            else
            {
                remove_menu_page( $slug );
            }
        }
    }
    public function filter_cpt_title( $data, $postarr )
    {
        if ( $data['post_type'] != $this->post_type )
        {
            return $data;
        }
        if ( $postarr['ID'] == null or empty( $_POST ) )
        {
            return $data;
        }
        if ( !isset( $_POST['molongui_authorship_guest_nonce'] ) or !wp_verify_nonce( $_POST['molongui_authorship_guest_nonce'], 'molongui_authorship_guest' ) )
        {
            return $data;
        }
        $first_name   = !empty( $_POST['_molongui_guest_author_first_name'] )   ? $_POST['_molongui_guest_author_first_name']   : '';
        $last_name    = !empty( $_POST['_molongui_guest_author_last_name'] )    ? $_POST['_molongui_guest_author_last_name']    : '';
        $display_name = !empty( $_POST['_molongui_guest_author_display_name'] ) ? $_POST['_molongui_guest_author_display_name'] : '';
        if ( $display_name ) $post_title = sanitize_text_field( $_POST['_molongui_guest_author_display_name'] );
        elseif (  $first_name and  $last_name ) $post_title = sanitize_text_field( $_POST['_molongui_guest_author_first_name'] ) . ' ' . sanitize_text_field( $_POST['_molongui_guest_author_last_name'] );
        elseif (  $first_name and !$last_name ) $post_title = sanitize_text_field( $_POST['_molongui_guest_author_first_name'] );
        elseif ( !$first_name and  $last_name ) $post_title = sanitize_text_field( $_POST['_molongui_guest_author_last_name'] );

        if ( !empty( $post_title ) )
        {
            $data['post_title'] = $post_title;
        }
        $data['post_name'] = '';
        return $data;
    }
    public function on_save( $post_id )
    {
        if ( !isset( $_POST['molongui_authorship_guest_nonce'] ) or !wp_verify_nonce( $_POST['molongui_authorship_guest_nonce'], 'molongui_authorship_guest' ) )
        {
            return $post_id;
        }
        if ( defined( 'DOING_AUTOSAVE' ) and DOING_AUTOSAVE )
        {
            return $post_id;
        }
        if ( wp_is_post_revision( $post_id ) )
        {
            return $post_id;
        }
        if ( 'page' == $_POST['post_type'] ) if ( !current_user_can( 'edit_page', $post_id ) )
        {
            return $post_id;
        }
        elseif ( !current_user_can( 'edit_post', $post_id ) )
        {
            return $post_id;
        }
        $networks = Social::get( 'enabled' );
        foreach ( $networks as $id => $network )
        {
            if ( !empty( $_POST['_molongui_guest_author_'.$id] ) )
            {
                update_post_meta( $post_id, '_molongui_guest_author_'.$id, sanitize_text_field( $_POST['_molongui_guest_author_'.$id] ) );
            }
            else
            {
                delete_post_meta( $post_id, '_molongui_guest_author_'.$id );
            }
        }
        $inputs = array
        (
            '_molongui_guest_author_first_name',
            '_molongui_guest_author_last_name',
            '_molongui_guest_author_display_name',
            '_molongui_guest_author_mail',
            '_molongui_guest_author_phone',
            '_molongui_guest_author_job',
            '_molongui_guest_author_company',
        );
        foreach ( $inputs as $input )
        {
            if ( !empty( $_POST[$input] ) )
            {
                update_post_meta( $post_id, $input, sanitize_text_field( $_POST[$input] ) );
            }
            else
            {
                delete_post_meta( $post_id, $input );
            }
        }
        $urls = array
        (
            '_molongui_guest_author_web',
            '_molongui_guest_author_company_link',
            '_molongui_guest_author_custom_link',
        );
        foreach ( $urls as $url )
        {
            if ( !empty( $_POST[$url] ) )
            {
                update_post_meta( $post_id, $url, sanitize_url( $_POST[$url] ) );
            }
            else
            {
                delete_post_meta( $post_id, $url );
            }
        }
        $checkboxes = array
        (
            '_molongui_guest_author_show_meta_mail',
            '_molongui_guest_author_show_meta_phone',
            '_molongui_guest_author_show_icon_mail',
            '_molongui_guest_author_show_icon_web',
            '_molongui_guest_author_show_icon_phone',
            '_molongui_guest_author_archived',
        );
        foreach ( $checkboxes as $checkbox )
        {
            if ( isset( $_POST[$checkbox] ) )
            {
                update_post_meta( $post_id, $checkbox, sanitize_text_field( $_POST[$checkbox] ) );
            }
            else
            {
                delete_post_meta( $post_id, $checkbox );
            }
        }
        update_post_meta( $post_id, '_molongui_guest_author_box_display', 'default' );
        add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99, 2 );
        do_action( 'authorship/guest/save', $post_id, $_POST );
    }
    public function add_notice_query_var( $location, $post_id )
    {
        remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );

        $author = new Author( $post_id, 'guest' );
        $name_exists = $author->is_display_name_available();

        if ( $name_exists )
        {
            switch ( $name_exists )
            {
                case 'user' :
                    return add_query_arg( array( 'authorship_guest_save' => 'user_alert'  ), $location );
                    break;
                case 'guest':
                    return add_query_arg( array( 'authorship_guest_save' => 'guest_alert' ), $location );
                    break;
                case 'both' :
                    return add_query_arg( array( 'authorship_guest_save' => 'both_alert'  ), $location );
                    break;
            }
        }

        return $location;
    }
    public function add_removable_arg( $args )
    {
        array_push( $args, 'authorship_guest_save' );
        return $args;
    }
    public function admin_notices()
    {
        if ( !isset( $_GET['authorship_guest_save'] ) )
        {
            return;
        }

        switch ( $_GET['authorship_guest_save'] )
        {
            case 'user_alert':
                $message = esc_html__( 'There is a registered WordPress user with the same display name. You might want to address that.', 'molongui-authorship' );
                break;

            case 'guest_alert':
                $message = esc_html__( 'There is another guest author with the same display name. You might want to address that.', 'molongui-authorship' );
                break;

            case 'both_alert':
                $message = esc_html__( 'There is a registered WordPress user and another guest author with the same display name. You might want to address that.', 'molongui-authorship' );
                break;

            default:
                $message = '';
                break;
        }

        if ( empty( $message ) )
        {
            return;
        }
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php echo $message; ?></p>
        </div>
        <?php
    }
    public function on_delete( $guest_id )
    {
        $author      = new Author( $guest_id, 'guest' );
        $guest_posts = $author->get_posts( array( 'fields' => 'ids', 'post_type' => 'all' ) );

        add_filter( 'authorship/admin/guest/delete', function() use ( $guest_posts ) { return $guest_posts; } );
    }
    public function on_deleted( $guest_id, $guest = null )
    {
        $post_ids = apply_filters( 'authorship/admin/guest/delete', array() );
        if ( !empty( $post_ids ) )
        {
            foreach ( $post_ids as $post_id )
            {
                delete_post_meta( $post_id, '_molongui_author', 'guest-'.$guest_id );
                if ( get_post_meta( $post_id, '_molongui_main_author', true ) === 'guest-'.$guest_id )
                {
                    $post_authors = get_post_meta( $post_id, '_molongui_author', false );
                    if ( empty( $post_authors ) )
                    {
                        $post_author = get_post_field ('post_author', $post_id );
                        update_post_meta( $post_id, '_molongui_main_author', 'user-'.$post_author, 'guest-'.$guest_id );
                        update_post_meta( $post_id, '_molongui_author', 'user-'.$post_author );
                    }
                    else
                    {
                        update_post_meta( $post_id, '_molongui_main_author', $post_authors[0], 'guest-'.$guest_id );
                        if ( strpos( $post_authors[0], 'user-' ) !== false )
                        {
                            $id = str_replace ( 'user-', '', $post_authors[0] );
                            wp_update_post( array( 'ID' => $post_id, 'post_author' => $id ) );
                        }
                    }
                }
            }
        }
        do_action( 'authorship/admin/guest/deleted', $guest_id, $guest );
    }
    public function maybe_update_guest_count( $new_status, $old_status, $post )
    {
        if ( $this->post_type === $post->post_type and ( ( $new_status === 'publish' and $old_status !== 'publish' ) or ( $new_status !== 'publish' and $old_status === 'publish' ) ) )
        {
            self::update_guest_count();
        }
    }
    public function register_admin_scripts()
    {
        Assets::register_script( $this->javascript, 'edit_guest' );
    }
    public function enqueue_admin_scripts()
    {
        $screen = get_current_screen();
        if ( !in_array( $screen->id, array( 'edit-'.$this->post_type, $this->post_type ) ) )
        {
            return;
        }

        Assets::enqueue_script( $this->javascript, 'edit_guest', true );
    }
    public function localize_admin_scripts()
    {
        $params = array
        (
        );
        return apply_filters( 'authorship/edit_guest/script_params', $params );
    }
    public function remove_mine_filter( $views )
    {
        unset( $views['mine'] );

        return $views;
    }
    public function add_list_columns( $columns )
    {
        unset( $columns['title'] );
        unset( $columns['date'] );
        unset( $columns['thumbnail'] );
        $new_cols = array
        (
            'guestAuthorPic'      => __( "Photo", 'molongui-authorship' ),
            'title'		          => __( "Name", 'molongui-authorship' ),
            'guestDisplayBox'     => __( "Box", 'molongui-authorship' ),
            'guestAuthorBio'      => __( "Bio", 'molongui-authorship' ),
            'guestAuthorMail'     => __( "Email", 'molongui-authorship' ),
            'guestAuthorPhone'    => __( "Phone", 'molongui-authorship' ),
            'guestAuthorUrl'      => __( "URL", 'molongui-authorship' ),
            'guestAuthorJob'      => __( "Job", 'molongui-authorship' ),
            'guestAuthorCia'      => __( "Co.", 'molongui-authorship' ),
            'guestAuthorCiaUrl'   => __( "Co. URL", 'molongui-authorship' ),
            'guestAuthorSocial'   => __( "Social Profiles", 'molongui-authorship' ),
            'guestAuthorEntries'  => __( "Entries", 'molongui-authorship' ),
            'guestAuthorArchived' => '<span class="dashicons dashicons-archive"></span>',
            'guestAuthorId'       => __( "ID", 'molongui-authorship' ),
        );
        if ( !Settings::is_enabled( 'author-box' ) )
        {
            unset( $new_cols['guestDisplayBox'] );
        }
        if ( !Settings::get( 'social_profiles_enabled' ) )
        {
            unset( $new_cols['guestAuthorSocial'] );
        }
        if ( 'trash' == get_query_var( 'post_status' ) )
        {
            unset( $new_cols['guestAuthorEntries'] );
        }
        return array_merge( $columns, $new_cols );
    }
    public function fill_list_columns( $column, $ID )
    {
        $value  = '';
        $author = new Author( $ID, 'guest' );
        if ( 'guestAuthorPic' == $column )
        {
            echo get_the_post_thumbnail( $ID, array( 60, 60 ) );
            return;
        }
        elseif ( 'guestDisplayBox' == $column )
        {
            $value = $author->get_meta( 'box_display' );

            switch ( $value )
            {
                case 'show':
                    $icon = 'visibility';
                    $tip  = __( "Visible", 'molongui-authorship' );
                    break;

                case 'hide':
                    $icon = 'hidden';
                    $tip  = __( "Hidden", 'molongui-authorship' );
                    break;

                default:
                    $icon = 'admin-generic';
                    $tip  = __( "Visibility depends on global plugin settings", 'molongui-authorship' );
                    break;
            }

            $html  = '<div id="box_display_'.$ID.'" data-display-box="'.$value.'">';
            $html .= '<div class="m-tooltip">';
            $html .= '<span class="dashicons dashicons-'.$icon.'"></span>';
            $html .= '<span class="m-tooltip__text m-tooltip__top m-tooltip__w100">'.$tip.'</span>';
            $html .= '</div>';
            $html .= '</div>';

            echo $html;
            return;
        }
        elseif ( 'guestAuthorEntries' == $column )
        {
            $html   = '';
            $values = $author->get_post_counts();
            foreach ( Settings::enabled_post_types( 'guest-author', 'object' ) as $post_type )
            {
                $link = admin_url( 'edit.php?post_type='.$post_type['id'].'&guest='.$ID );
                if ( isset( $values[$post_type['id']] ) and $values[$post_type['id']] > 0 )
                {
                    $html .= '<div><a href="'.$link.'">'.$values[$post_type['id']].' '.$post_type['label'].'</a></div>';
                }
            }
            if ( !$html )
            {
                $html = __( 'None' );
            }

            echo $html;
            return;
        }
        elseif ( 'guestAuthorSocial' == $column )
        {
            $networks = Social::get( 'enabled' );
            foreach ( $networks as $name => $network )
            {
                if ( $sn = $author->get_meta( $name ) )
                {
                    $html  = '<div class="m-tooltip">';
                    $html .= '<a href="'.esc_url( $sn ).'" target="_blank">';
                    $html .= '<i class="m-a-icon-'.$name.'"></i>';
                    $html .= '</a>';
                    $html .= '<span class="m-tooltip__text m-tooltip__top">'.esc_url( $sn ).'</span>';
                    $html .= '</div>';

                    echo $html;
                }
            }
            return;
        }
        elseif ( 'guestAuthorArchived' === $column )
        {
            if ( $author->is_archived() )
            {
                $tip   = __( "Archived author", 'molongui-authorship' );

                $html  = '<div class="m-tooltip">';
                $html .= '<span class="dashicons dashicons-yes"></span>';
                $html .= '<span class="m-tooltip__text m-tooltip__top m-tooltip__w50">'.$tip.'</span>';
                $html .= '</div>';

                echo $html;
            }
            return;
        }
        elseif ( 'guestAuthorId' === $column )
        {
            echo $ID;
            return;
        }
        elseif ( $column == 'guestAuthorBio'   ) $value = $author->get_description();
        elseif ( $column == 'guestAuthorMail'  ) $value = $author->get_email();
        elseif ( $column == 'guestAuthorPhone' ) $value = $author->get_meta( 'phone' );
        elseif ( $column == 'guestAuthorJob'   ) $value = $author->get_meta( 'job' );
        elseif ( $column == 'guestAuthorCia'   ) $value = $author->get_meta( 'company' );

        if ( !empty( $value ) )
        {
            $html  = '<div class="m-tooltip">';
            $html .= '<i class="m-a-icon-ok"></i>';
            $html .= '<span class="m-tooltip__text m-tooltip__top'.( 'guestAuthorBio' === $column ? ' m-tooltip__w400' : ' m-tooltip__w100' ).'">'.esc_html( $value ).'</span>';
            $html .= '</div>';

            echo $html;
            return;
        }
        elseif ( $column == 'guestAuthorUrl'    )
        {
            $value = $author->get_website();
        }
        elseif ( $column == 'guestAuthorCiaUrl' )
        {
            $value = $author->get_meta( 'company_link' );
        }

        if ( !empty( $value ) )
        {
            $html  = '<div class="m-tooltip">';
            $html .= '<a href="'.esc_url( $value ).'" target="_blank">';
            $html .= '<i class="m-a-icon-ok"></i>';
            $html .= '</a>';
            $html .= '<span class="m-tooltip__text m-tooltip__top m-tooltip__w100">'.esc_url( $value ).'</span>';
            $html .= '</div>';

            echo $html;
            return;
        }
        else
        {
            echo '-';
            return;
        }
    }
    public function remove_view_link( $actions )
    {
        if ( !apply_filters( 'authorship/guest/row_actions/remove_view_link', true ) )
        {
            return $actions;
        }

        if ( $this->post_type == get_post_type() )
        {
            unset( $actions['view'] );
        }

        return $actions;
    }
    public function quick_edit_add_title_field()
    {
        global $pagenow, $post_type;

        if ( 'edit.php' == $pagenow and $post_type == $this->post_type )
        {
            add_post_type_support( $post_type, 'title' );
        }
    }
    public function quick_edit_add_custom_fields( $column_name, $post_type )
    {
        if ( $column_name != 'guestDisplayBox' )
        {
            return;
        }

        wp_nonce_field( 'quick_edit_guest', 'quick_edit_guest_nonce' );

        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <div class="inline-edit-group wp-clearfix">
                    <label class="inline-edit-status alignleft">
                        <span class="title"><?php esc_html_e( "Author Box", 'molongui-authorship' ); ?></span>
                        <select name="_molongui_guest_author_box_display">
                            <option value="default" ><?php _e( "Default", 'molongui-authorship' ); ?></option>
                            <option value="show"    ><?php _e( "Show", 'molongui-authorship' ); ?></option>
                            <option value="hide"    ><?php _e( "Hide", 'molongui-authorship' ); ?></option>
                        </select>
                    </label>
                </div>
            </div>
        </fieldset>
        <?php
    }
    public function quick_edit_populate_custom_fields()
    {
        $current_screen = get_current_screen();
        if ( $current_screen->id != 'edit-'.$this->post_type or $current_screen->post_type != $this->post_type )
        {
            return;
        }
        wp_enqueue_script( 'jquery' );
        ?>
        <script type="text/javascript">
            jQuery(function($)
            {
                var $inline_editor = inlineEditPost.edit;
                inlineEditPost.edit = function(id)
                {
                    $inline_editor.apply(this, arguments);
                    var post_id = 0;
                    if (typeof(id) == 'object')
                    {
                        post_id = parseInt(this.getId(id));
                    }
                    if (post_id != 0)
                    {
                        $row = $('#edit-' + post_id);
                        $box_display = $('#box_display_' + post_id).data('display-box');
                        if ($box_display === '')
                        {
                            $box_display = 'default';
                        }
                        $row.find('[name="_molongui_guest_author_box_display"]').val($box_display);
                        $row.find('[name="_molongui_guest_author_box_display"]').children('[value="' + $box_display + '"]').attr('selected', true);
                    }
                }
            });
        </script>
        <?php
    }
    public function quick_edit_save_custom_fields( $post_id, $post )
    {
        if ( !isset( $_POST['quick_edit_guest_nonce'] ) or !wp_verify_nonce( $_POST['quick_edit_guest_nonce'], 'quick_edit_guest' ) )
        {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) and DOING_AUTOSAVE )
        {
            return;
        }
        if ( wp_is_post_revision( $post_id ) )
        {
            return;
        }
        if ( !current_user_can( 'edit_post', $post_id ) )
        {
            return;
        }
        if ( isset( $_POST['post_title'] ) )
        {
            update_post_meta( $post_id, '_molongui_guest_author_display_name', sanitize_text_field( $_POST['post_title'] ) );
        }
        if ( isset( $_POST['_molongui_guest_author_box_display'] ) )
        {
            update_post_meta( $post_id, '_molongui_guest_author_box_display', sanitize_text_field( $_POST['_molongui_guest_author_box_display'] ) );
        }
    }
    public function remove_bulk_edit_action( $actions )
    {
        if ( !apply_filters( 'authorship/guest/bulk_actions/remove_edit', true ) )
        {
            return $actions;
        }

        unset( $actions['edit'] );
        return $actions;
    }
    public function remove_media_buttons()
    {
        global $current_screen;

        if ( $this->post_type == $current_screen->post_type )
        {
            remove_action( 'media_buttons', 'media_buttons' );
        }
    }
    public function remove_preview_button()
    {
        $current_screen = get_current_screen();
        if ( $current_screen->post_type != $this->post_type )
        {
            return;
        }
        if ( apply_filters( 'authorship/admin/guest/show_preview_button', false, $current_screen ) )
        {
            return;
        }
        echo '<style>#post-preview{ display:none !important; }</style>';
    }
    public function add_top_section_after_title()
    {
        global $post;
        if ( $post->post_type !== $this->post_type )
        {
            return;
        }
        do_meta_boxes( get_current_screen(), 'top', $post );
    }
    public function add_meta_boxes( $post_type )
    {
        if ( !current_user_can( 'edit_others_pages' ) and !current_user_can( 'edit_others_posts' ) )
        {
            return;
        }
        if ( in_array( $post_type, array( $this->post_type ) ) )
        {
            add_meta_box(
                'authorprofilediv'
                ,__( "Profile", 'molongui-authorship' )
                ,array( $this, 'render_profile_metabox' )
                ,$post_type
                ,'top'
                ,'high'
            );
            add_meta_box(
                'authorbiodiv'
                ,__( "Biography", 'molongui-authorship' )
                ,array( $this, 'render_bio_metabox' )
                ,$post_type
                ,'top'
                ,'high'
            );
            if ( apply_filters( 'authorship/admin/guest/shortbio/metabox', true ) )
            {
                add_meta_box(
                    'authorshortbiodiv'
                    ,__( "Short Biography", 'molongui-authorship' )
                    ,array( $this, 'render_short_bio_metabox' )
                    ,$post_type
                    ,'top'
                    ,'default'
                );
            }
            add_meta_box(
                'authorsocialdiv'
                ,__( "Social Media", 'molongui-authorship' )
                ,array( $this, 'render_social_metabox' )
                ,$post_type
                ,'normal'
                ,'high'
            );
            if ( !Settings::is_enabled( 'local-avatar' ) )
            {
                add_meta_box(
                    'authoravatardiv'
                    ,__( "Profile Image", 'molongui-authorship' )
                    ,array( $this, 'render_avatar_metabox' )
                    ,$post_type
                    ,'side'
                    ,'low'
                );
            }
            add_meta_box(
                'authorarchivediv'
                ,__( "Archive", 'molongui-authorship' )
                ,array( $this, 'render_archive_metabox' )
                ,$post_type
                ,'side'
                ,'high'
            );
            if ( Settings::is_enabled( 'author-box' ) )
            {
                add_meta_box(
                    'authorboxdiv'
                    ,__( "Author Box", 'molongui-authorship' )
                    ,array( $this, 'render_box_metabox' )
                    ,$post_type
                    ,'side'
                    ,'low'
                );
            }
            if ( apply_filters( 'authorship/admin/guest/convert/metabox', true ) )
            {
                add_meta_box(
                    'authorconversiondiv'
                    ,__( "Role" )
                    ,array( $this, 'render_conversion_metabox' )
                    ,$post_type
                    ,'side'
                    ,'low'
                );
            }
            do_action( 'authorship/admin/guest/metaboxes', $post_type );
        }
    }
    public function hide_convert_metabox()
    {
        if ( !current_user_can( 'create_users' ) )
        {
            return false;
        }
        return true;
    }
    public function render_profile_metabox( $post )
    {
        wp_nonce_field( 'molongui_authorship_guest', 'molongui_authorship_guest_nonce' );
        $guest_author_first_name   = esc_attr( get_post_meta( $post->ID, '_molongui_guest_author_first_name', true ) );
        $guest_author_last_name    = esc_attr( get_post_meta( $post->ID, '_molongui_guest_author_last_name', true ) );
        $guest_author_display_name = esc_attr( get_post_meta( $post->ID, '_molongui_guest_author_display_name', true ) ); //get_the_title( $post->ID );
        $guest_author_mail         = esc_attr( get_post_meta( $post->ID, '_molongui_guest_author_mail', true ) );
        $guest_author_phone        = esc_attr( get_post_meta( $post->ID, '_molongui_guest_author_phone', true ) );
        $guest_author_web          = esc_attr( get_post_meta( $post->ID, '_molongui_guest_author_web', true ) );
        $guest_author_job          = esc_attr( get_post_meta( $post->ID, '_molongui_guest_author_job', true ) );
        $guest_author_company      = esc_attr( get_post_meta( $post->ID, '_molongui_guest_author_company', true ) );
        $guest_author_company_link = esc_attr( get_post_meta( $post->ID, '_molongui_guest_author_company_link', true ) );
        include MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-profile-metabox.php';
    }
    public function render_bio_metabox( $post )
    {
        $guest_author_bio = get_post_field( 'post_content', $post->ID );
        wp_editor( $guest_author_bio, 'content', array( 'media_buttons' => false, /*'editor_height' => 100,*/ 'textarea_rows' => 10, 'editor_css' => '<style>#wp-content-editor-tools{background:none;padding-top:0;}</style>' ) );
    }
    public function render_short_bio_metabox( $post )
    {
        $file = MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-short-bio-metabox.php';
        include apply_filters( 'authorship/admin/guest/shortbio_metabox_html', $file );
    }
    public function render_social_metabox( $post )
    {
        $networks = Social::get( 'enabled' );
        include MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-social-metabox.php';
    }
    public function render_archive_metabox( $post )
    {
        $guest_author_archived = get_post_meta( $post->ID, '_molongui_guest_author_archived', true );
        include MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-archive-metabox.php';
    }
    public function render_box_metabox( $post )
    {
        $guest_author_hide_box    = get_post_meta( $post->ID, '_molongui_guest_author_box_display', true );
        $guest_author_custom_link = get_post_meta( $post->ID, '_molongui_guest_author_custom_link', true );
        $guest_author_mail_icon   = get_post_meta( $post->ID, '_molongui_guest_author_show_icon_mail', true );
        $guest_author_phone_icon  = get_post_meta( $post->ID, '_molongui_guest_author_show_icon_phone', true );
        $guest_author_web_icon    = get_post_meta( $post->ID, '_molongui_guest_author_show_icon_web', true );
        $guest_author_mail_meta   = get_post_meta( $post->ID, '_molongui_guest_author_show_meta_mail', true );
        $guest_author_phone_meta  = get_post_meta( $post->ID, '_molongui_guest_author_show_meta_phone', true );
        include MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-box-metabox.php';
    }
    public function render_avatar_metabox( $post )
    {
        $options = Settings::get();
        include MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-avatar-metabox.php';
    }
    public function render_conversion_metabox( $post )
    {
        $file = MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-convert-metabox.php';
        include apply_filters( 'authorship/admin/guest/convert_metabox_html', $file );
    }
    public function add_conversion_metabox_class( $classes )
    {
        if ( ! Plugin::has_pro() )
        {
            $classes[] = 'free';
        }
        return $classes;
    }
    public function add_short_bio_metabox_class( $classes )
    {
        if ( ! Plugin::has_pro() )
        {
            $classes[] = 'free';
        }
        return $classes;
    }
    public static function update_guest_count()
    {
        $post_type = Guest_Author::get_post_type();

        /*!
         * FILTER HOOK
         *
         * Allows the use of 'wp_count_posts' instead of a custom SQL query to count the number of guest authors.
         *
         * When dealing with a large number of guests, using 'wp_count_posts' can become slow. A more efficient way
         * to get the guest count is to run a custom SQL query directly on the database. This approach bypasses
         * the overhead of 'wp_count_posts' and can be significantly faster.
         *
         * @since 4.9.5
         */
        if ( apply_filters( 'molongui_authorship/guest_count_custom_sql_query', true ) )
        {
            global $wpdb;
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = %s",
                $post_type, 'publish'
            );
            $guest_count = $wpdb->get_var( $query );
        }
        else
        {
            $guest_count = wp_count_posts( $post_type );
            $guest_count = isset( $guest_count->publish ) ? $guest_count->publish : 0;
        }

        update_option( 'molongui_authorship_guest_count', $guest_count, false );
    }
    public static function clear_object_cache()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0' );

        Cache::clear( 'guests' );
        Cache::clear( 'posts' );
    }

} // class
new Admin_Guest_Author();
