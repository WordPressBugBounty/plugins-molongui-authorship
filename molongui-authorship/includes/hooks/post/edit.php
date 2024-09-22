<?php

use Molongui\Authorship\Author;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\User;
use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_post_quick_add_guest()
{
    if ( !wp_verify_nonce( $_POST['nonce'], 'molongui_authorship_quick_add_nonce' ) ) die();
    if ( empty( $_POST['display_name'] ) )
    {
        echo json_encode( array( 'error' => __( "No display name provided", 'molongui-authorship' ) ) );
        die();
    }
    $postarr = array
    (
        'post_type'      => 'guest_author',
        'post_name'      => $_POST['display_name'],
        'post_title'     => $_POST['display_name'],
        'post_excerpt'   => '',
        'post_content'   => '',
        'thumbnail'      => '',
        'meta_input'     => array
        (
            '_molongui_guest_author_display_name' => $_POST['display_name'],
        ),
        'post_status'    => 'publish',
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
        'post_author'    => get_current_user_id(),
    );
    $guest_id = wp_insert_post( $postarr, true );

    if ( is_wp_error( $guest_id ) )
    {
        echo json_encode( array( 'error' => $guest_id->get_error_message() ) );
    }
    else
    {
        authorship_guest_clear_object_cache();

        echo json_encode( array( 'guest_id' => $guest_id, 'guest_ref' => 'guest-'.$guest_id, 'guest_name' => $_POST['display_name'] ) );
    }

    die();
}
function authorship_post_trash( $post_id )
{
    if ( is_customize_preview() ) return;
    $post_type = authorship_get_post_type( $post_id );
    if ( !authorship_is_post_type_enabled( $post_type, $post_id ) ) return;
    authorship_post_clear_object_cache();
    $post_status = authorship_post_status( $post_type );
    if ( in_array( get_post_meta( $post_id, '_wp_trash_meta_status', true ), $post_status ) )
    {
        authorship_decrement_post_counter( get_post_type( $post_id ), authorship_get_post_authors( $post_id, 'ref' ) );
    }
}
function authorship_post_untrash( $post_id )
{
    $post_type = authorship_get_post_type( $post_id );
    if ( !authorship_is_post_type_enabled( $post_type, $post_id ) ) return;
    authorship_post_clear_object_cache();
    $post_status = authorship_post_status( $post_type );
    if ( in_array( get_post_meta( $post_id, '_wp_trash_meta_status', true ), $post_status ) )
    {
        authorship_increment_post_counter( get_post_type( $post_id ), authorship_get_post_authors( $post_id, 'ref' ) );
    }
}
function authorship_post_add_meta_boxes( $post_type )
{
    /*!
     * FILTER HOOK
     *
     * Allows changing the capabilities criteria followed to decide whether to add custom meta boxes.
     *
     * @param bool   Current user editor capabilities.
     * @param string Current post type.
     * @since 4.4.0
     */
    $editor_caps = apply_filters( 'authorship/editor_caps', current_user_can( 'edit_others_pages' ) or current_user_can( 'edit_others_posts' ), $post_type );
    if ( !$editor_caps ) return;

    $post_types = molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all' );
    if ( in_array( $post_type, $post_types ) and apply_filters( 'authorship/add_authors_widget', authorship_byline_takeover(), $post_type ) )
    {
        add_meta_box
        (
            'molongui-post-authors-box'
            , __( "Authors", 'molongui-authorship' )
            , 'authorship_post_render_author_metabox'
            , $post_type
            , 'side'
            , 'high'
        );
    }

    /*!
     * FILTER HOOK
     *
     * Allows filtering contributors metabox display criteria.
     *
     * @param bool   True by default.
     * @param string Current post type.
     * @since 4.8.6
     */
    if ( in_array( $post_type, $post_types ) and
         !is_plugin_active( 'molongui-post-contributors/molongui-post-contributors.php' ) and
         apply_filters( 'authorship/add_contributors_widget', true, $post_type ) )
    {
        add_meta_box
        (
            'molongui-post-contributors-box'
            , __( "Contributors", 'molongui-authorship' )
            , 'authorship_post_render_contributor_metabox'
            , $post_type
            , 'side'
            , 'high'
        );
    }
    if ( authorship_is_feature_enabled( 'box' ) and in_array( $post_type, authorship_box_post_types() ) and apply_filters( 'authorship/add_author_box_widget', true, $post_type ) )
    {
        add_meta_box
        (
            'molongui-author-box-box'
            ,__( "Author Box", 'molongui-authorship' )
            ,'authorship_post_render_box_metabox'
            ,$post_type
            ,'side'
            ,'high'
        );
    }
}
function authorship_post_render_author_metabox( $post )
{
    wp_nonce_field( 'molongui_authorship_post', 'molongui_authorship_post_nonce' );

    Post::author_selector( $post->ID );
}
function authorship_post_render_contributor_metabox( $post )
{
    $class = Helpers::is_edit_mode() ? 'components-button is-secondary' : 'button button-primary';
    ?>
    <div class="molongui-metabox">
        <div class="m-title"><?php _e( "Reviewers? Fact-checkers?", 'molongui-authorship' ); ?></div>
        <p class="m-description"><?php printf( __( "The %sMolongui Post Contributors%s plugin allows you to add contributors to your posts and display them towards the post author.", 'molongui-authorship' ), '<strong>', '</strong>' ); ?></p>
        <?php if ( current_user_can( 'install_plugins' ) ) : ?>
            <p class="m-description"><?php printf( __( "Install it now, it's %sfree%s!", 'molongui-authorship' ), '<strong>', '</strong>' ); ?></p>
            <a class="<?php echo $class; ?>" href="<?php echo wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=molongui-post-contributors' ), 'install-plugin_molongui-post-contributors' ); ?>"><?php _e( "Install Now", 'molongui-authorship' ); ?></a>
        <?php else : ?>
            <p class="m-description"><?php printf( __( "Ask the site administrator to install it, it's %sfree%s!", 'molongui-authorship' ), '<strong>', '</strong>' ); ?></p>
            <a class="<?php echo $class; ?>" href="<?php echo esc_url( 'https://wordpress.org/plugins/molongui-post-contributors/' ); ?>" target="_blank"><?php _e( "Know More", 'molongui-authorship' ); ?></a>
        <?php endif; ?>
    </div>
    <?php
}
function authorship_post_render_box_metabox( $post )
{
    wp_nonce_field( 'molongui_authorship_post', 'molongui_authorship_post_nonce' );
    $screen = get_current_screen();
    $author_box_display  = get_post_meta( $post->ID, '_molongui_author_box_display', true );
    $author_box_position = get_post_meta( $post->ID, '_molongui_author_box_position', true );
    if ( empty( $author_box_display ) )  $author_box_display  = 'default';
    if ( empty( $author_box_position ) ) $author_box_position = 'default';
    include MOLONGUI_AUTHORSHIP_DIR . 'views/post/html-admin-box-metabox.php';
}
function authorship_dropdown_authors( $type = 'authors', $args = array() )
{
    global $post;
    extract( array_merge( array
    (
        'multi'    => authorship_is_feature_enabled( 'multi' ),
        'guest'    => authorship_is_feature_enabled( 'guest' ),
        'selected' => '',
    ), $args ) );
    $archived_users  = apply_filters( 'authorship/authors_dropdown/exclude_users' , authorship_get_archived_users()  );
    $archived_guests = apply_filters( 'authorship/authors_dropdown/exclude_guests', authorship_get_archived_guests() );
    $authors = molongui_get_authors( $type, array(), $archived_users, array(), $archived_guests );
    $html = '';
    if ( empty( $authors ) )
    {
        $html .= '<div><p>'.__( "No authors found.", 'molongui-authorship' ).'</p></div>';
    }
    else
    {
        if ( $multi )
        {
            $html .= '<select id="_molongui_author" name="_molongui_author" class="searchable" data-placeholder="'.__( 'Add an(other) author...', 'molongui-authorship' ).'">';
            foreach ( $authors as $author ) $html .= '<option value="'.$author['ref'].'" data-type="['.$author['type'].']">' . $author['name'] . '</option>';
        }
        else
        {
            $html .= '<select id="_molongui_author" name="_molongui_author" class="searchable" data-placeholder="'.__( 'Add an author...', 'molongui-authorship' ).'">';
            if ( !$selected )
            {
                $main_author = get_main_author( $post->ID );
                $selected    = $main_author->ref;
            }
            foreach ( $authors as $author ) $html .= '<option value="'.$author['ref'].'" data-type="['.$author['type'].']"'.selected( $author['type'].'-'.$author['id'], $selected, false ).'>' . $author['name'] . '</option>';
        }
        $html .= '</select>';
    }
    if ( !$multi ) return $html;

    $html .= '<div class="block__list block__list_words"><ul id="molongui_authors">';
    $post_authors = authorship_get_post_authors( $post->ID );

    if ( $post_authors )
    {
        foreach ( $post_authors as $author )
        {
            if ( $type == 'users' and $author->type == 'guest' ) continue;
            $author_class = new Author( $author->id, $author->type );
            $html .= '<li data-post="'.$post->ID.'" data-value="'.$author->ref.'">';
            $html .= $author_class->get_name();
            $html .= '<input type="hidden" name="molongui_authors[]" value="'.$author->ref.'" />';
            $html .= '<div class="m-tooltip">';
            $html .= '<span class="dashicons dashicons-trash js-remove"></span>';
            $html .= '<span class="m-tooltip__text m-tooltip__left">'.__( "Remove author from selection", 'molongui-authorship' ).'</span>';
            $html .= '</div>';
            $html .= '</li>';
        }
    }

    $html .= '</ul></div>';
    return $html;
}