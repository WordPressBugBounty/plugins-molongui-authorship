<?php

use Molongui\Authorship\Author;
use Molongui\Authorship\Post;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$post      = Post::get( $post );
$is_a_post = is_a( $post, 'WP_Post' );
$post_type = $is_a_post ? $post->post_type : null;

$guest_author_enabled = Settings::is_guest_author_enabled_on_post_type( $post_type );
$co_authors_enabled   = Settings::is_co_authors_enabled_on_post_type( $post_type );
$placeholder          = __( "Search authors...", 'molongui-authorship' );

if ( 'box-editor' === $screen )
{
    $placeholder = __( "Search for an author to preview their author box", 'molongui-authorship' );
}

if ( !$is_a_post )
{
    $add_author   = false;
    $post_authors = array();

    if ( 'settings' === $screen )
    {
        $default_authors = Settings::get( 'default_post_author', '' );

        if ( !empty( $default_authors ) and is_string( $default_authors ) )
        {
            $default_authors = explode( ',', $default_authors );
            foreach ( $default_authors as $default_author )
            {
                $split = explode( '-', $default_author );

                $post_authors[] = (object) array
                (
                    'ID'   => (int)$split[1],
                    'id'   => (int)$split[1],
                    'type' => $split[0],
                    'ref'  => $default_author,
                );
            }
        }

$author_tag_title = ( 1 === count( (array)$post_authors ) ? '' : __( "Drag this author to reorder", 'molongui-authorship' ) );
    }
}
else
{
    $post_authors = Post::get_authors( $post->ID );
    if ( !$co_authors_enabled )
    {
        $post_authors = array( reset( $post_authors ) );
    }

    $add_author = ( current_user_can( 'edit_others_pages' ) or current_user_can( 'edit_others_posts' ) ) and !current_user_can( 'authorship_granted_edit_others_posts' ) and 'edit' === $screen;

    $author_tag_title = ( 1 === count( (array)$post_authors ) ? '' : __( "Drag this author to reorder", 'molongui-authorship' ) );

    /*! // translators: %s: The icon used to remove an author */
    $tip       = sprintf( __( "Type to search for an author. Add as many as needed. Drag to reorder, or click the %s to remove. The first author listed will be the main post author.", 'molongui-authorship' ), '<strong>x</strong>' );
    $short_tip = __( "Type to search for an author. Use controls to reorder or remove.", 'molongui-authorship' );

    if ( $guest_author_enabled and !$co_authors_enabled )
    {
        /*! // translators: %1$s: <strong>. %2$s: </strong>. %3$s: <a href="...">. %4$s: </a>. */
        $tip       = sprintf( __( "Type to search and select an author. To add multiple authors, enable the %1\$sMultiple Authors%2\$s feature in the %3\$splugin settings%4\$s page.", 'molongui-authorship' ), '<strong>', '</strong>', '<a href="'.Settings::url( 'general' ).'" target="_blank">', '</a>' );
        $short_tip = __( "Type to search and select an author.", 'molongui-authorship' );
    }
    elseif ( !$guest_author_enabled and $co_authors_enabled )
    {
        /*! // translators: %1$s: <strong>. %2$s: </strong>. %3$s: <a href="...">. %4$s: </a>. */
        $tip       = sprintf( __( "Type to search for an author. The first author listed will be the main post author. To add authors not registered on your site, enable the %1\$sGuest Author%2\$s feature in the %3\$splugin settings%4\$s page.", 'molongui-authorship' ), '<strong>', '</strong>', '<a href="'.Settings::url( 'general' ).'" target="_blank">', '</a>' );
        $short_tip = __( "Type to search and select an author.", 'molongui-authorship' );
    }
    elseif ( !$guest_author_enabled and !$co_authors_enabled )
    {
        /*! // translators: %1$s: <strong>. %2$s: </strong>. %3$s: <strong>. %4$s: </strong>. %5$s: <a href="...">. %6$s: </a>. */
        $tip       = sprintf( __( "Type to search and select an author. To add multiple authors, enable the %1\$sMultiple Authors%2\$s feature. To add authors not registered on your site, enable the %3\$sGuest Author%4\$s feature in the %5\$splugin settings%6\$s page.", 'molongui-authorship' ), '<strong>', '</strong>', '<strong>', '</strong>', '<a href="'.Settings::url( 'general' ).'" target="_blank">', '</a>' );
        $short_tip = __( "Type to search and select an author.", 'molongui-authorship' );
    }
}

?>

<div id="molongui-post-authors" class="hide-if-no-js <?php echo !$co_authors_enabled ? 'molongui-authorship-no-multiple-authors' : ''; ?>">

    <div class="molongui-post-authors__selector">

        <?php if ( isset( $tip ) ) : ?>
        <p class="molongui-post-authors__tip"><?php echo wp_kses_post( $tip ); ?></p>
        <?php endif; ?>

        <div class="molongui-post-authors__controls">
            <div class="molongui-post-authors__search">
                <svg class="molongui-post-authors__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input class="molongui-post-authors__input" type="text" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
                <span id="authors-loading" class="molongui-post-authors__spinner spinner"></span>
            </div>
            <?php if ( $add_author ) : ?>
                <a class="molongui-post-authors__add-new button" title="<?php esc_html_e( "Quick add a new author. To add an existing author, type their name in the search box on the left.", 'molongui-authorship' ); ?>"><span class="dashicons dashicons-plus-alt2"></span></a>
            <?php endif; ?>
        </div>

        <?php if ( $add_author ) : ?>
            <div class="molongui-post-authors__add-form" style="display:none">
                <p class="molongui-post-authors__tip"><?php esc_html_e( "Use this form only to create a new author. To add an existing author, type their name in the search box above.", 'molongui-authorship' ); ?></p>
                <label for="molongui-new-author-name"><?php esc_html_e( "Display Name", 'molongui-authorship' ); ?></label>
                <input name="molongui-new-author-name" class="" type="text" >
                <?php if ( $guest_author_enabled ) : ?>
                    <label for="molongui-new-author-type"><?php esc_html_e( "Author Type", 'molongui-authorship' ); ?></label>
                    <select name="molongui-new-author-type" class="">
                        <?php if ( current_user_can( 'create_users' ) ) : ?>
                            <option value="user"><?php esc_attr_e( "WP User", 'molongui-authorship' ); ?></option>
                        <?php endif; ?>
                        <option value="guest"><?php esc_attr_e( "Guest Author", 'molongui-authorship' ); ?></option>
                    </select>
                <?php else : ?>
                    <input type="hidden" name="molongui-new-author-type" value="user">
                <?php endif; ?>
                <label for="molongui-new-author-email"><?php esc_html_e( "Email Address", 'molongui-authorship' ); ?></label>
                <input name="molongui-new-author-email" class="" type="email" >
                <?php wp_nonce_field( 'molongui_authorship_quick_add_author', 'molongui_authorship_quick_add_author_nonce' ); ?>
                <button class="button"><?php esc_html_e( "Add New Author", 'molongui-authorship' ); ?></button>
            </div>
        <?php endif; ?>

        <?php if ( isset( $short_tip ) ) : ?>
        <p class="molongui-post-authors__tip molongui-post-authors__tip--quick"><?php echo esc_html( $short_tip ); ?></p>
        <?php endif; ?>
    </div>

    <?php if ( 'box-editor' !== $screen ) : ?>
    <div class="molongui-post-authors__list">
        <?php if ( in_array( $screen, array( 'edit' ) ) ) : ?>
        <span class="molongui-post-authors__list-title">
            <?php esc_html_e( "Post Authors", 'molongui-authorship' ); ?>
        </span>
        <?php endif; ?>
        <?php if ( in_array( $screen, array( 'edit', 'settings' ) ) ) : ?>
            <?php foreach ( $post_authors as $post_author ) : ?>
                <?php $author = new Author( $post_author->id, $post_author->type ); ?>
                <div id="<?php echo esc_attr( $post_author->ref ); ?>" class="molongui-post-authors__item molongui-post-authors__item--<?php echo $post_author->type; ?>" data-author-id="<?php echo esc_attr( $post_author->id ); ?>" data-author-type="<?php echo esc_attr( $post_author->type ); ?>" data-author-ref="<?php echo esc_attr( $post_author->ref ); ?>">
                    <div class="molongui-post-authors__row">
                        <?php
                        $author_gravatar = $author->get_avatar( array( 20, 20 ) );
                        if ( !empty( $author_gravatar ) ) : ?>
                            <div class="molongui-post-authors__avatar">
                                <?php echo $author_gravatar; ?>
                            </div>
                        <?php endif; ?>
                        <div class="molongui-post-authors__name" title="<?php echo esc_attr( $author_tag_title ); ?>">
                            <?php echo esc_html( $author->get_display_name() ); ?>
                        </div>
                        <div class="molongui-post-authors__actions">
                            <?php
                            $delete_icon    = '<span class="dashicons dashicons-no-alt molongui-post-authors__delete" title="' . esc_attr__( "Remove", 'molongui-authorship' ) . '"></span>';
                            $move_up_icon   = '<span class="dashicons dashicons-arrow-up-alt2 molongui-post-authors__up" title="' . esc_attr__( "Move up", 'molongui-authorship' ) . '"></span>';
                            $move_down_icon = '<span class="dashicons dashicons-arrow-down-alt2 molongui-post-authors__down" title="' . esc_attr__( "Move down", 'molongui-authorship' ) . '"></span>';
                            printf ( '%s%s%s'
                                , apply_filters( 'molongui_authorship/authors_selector/show_arrows', true ) ? $move_up_icon : ''
                                , apply_filters( 'molongui_authorship/authors_selector/show_arrows', true ) ? $move_down_icon : ''
                                , $delete_icon
                            );
                            ?>
                        </div>
                        <input type="hidden" name="molongui_post_authors[]" value="<?php echo esc_attr( $post_author->ref ); ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php wp_nonce_field( 'molongui_post_authors', 'molongui_post_authors_nonce', false ); ?>

</div>
