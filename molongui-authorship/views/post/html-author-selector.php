<?php

use Molongui\Authorship\Author;
use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$post = Post::get( $post );
if ( !$post )
{
    return;
}

$post_authors = authorship_get_post_authors( $post->ID );
$co_authors   = authorship_is_feature_enabled( 'multi' );
$guest_author = authorship_is_feature_enabled( 'guest' );

$author_tag_title = ( 1 === count( (array)$post_authors ) ? '' : __( "Drag this author to reorder", 'molongui-authorship' ) );

/*! // translators: %s: The icon used to remove an author */
$tip       = sprintf( __( "Type to search for an author. Add as many as needed. Drag to reorder, or click the %s to remove. The first author listed will be the main post author.", 'molongui-authorship' ), '<strong>x</strong>' );
$short_tip = __( "Type to search for an author. Use controls to reorder or remove.", 'molongui-authorship' );

if ( $guest_author and !$co_authors )
{
    /*! // translators: %1$s: <strong>. %2$s: </strong>. %3$s: <a href="...">. %4$s: </a>. */
    $tip       = sprintf( __( "Type to search and select an author. To add multiple authors, enable the %1\$sMultiple Authors%2\$s feature in the %3\$splugin settings%4\$s page.", 'molongui-authorship' ), '<strong>', '</strong>', '<a href="'.authorship_options_url( 'co-authors' ).'" target="_blank">', '</a>' );
    $short_tip = __( "Type to search and select an author.", 'molongui-authorship' );
}
elseif ( !$guest_author and $co_authors )
{
    /*! // translators: %1$s: <strong>. %2$s: </strong>. %3$s: <a href="...">. %4$s: </a>. */
    $tip       = sprintf( __( "Type to search for an author. The first author listed will be the main post author. To add authors not registered on your site, enable the %1\$sGuest Author%2\$s feature in the %3\$splugin settings%4\$s page.", 'molongui-authorship' ), '<strong>', '</strong>', '<a href="'.authorship_options_url( 'guest-authors' ).'" target="_blank">', '</a>' );
    $short_tip = __( "Type to search and select an author.", 'molongui-authorship' );
}
elseif ( !$guest_author and !$co_authors )
{
    /*! // translators: %1$s: <strong>. %2$s: </strong>. %3$s: <strong>. %4$s: </strong>. %5$s: <a href="...">. %6$s: </a>. */
    $tip       = sprintf( __( "Type to search and select an author. To add multiple authors, enable the %1\$sMultiple Authors%2\$s feature. To add authors not registered on your site, enable the %3\$sGuest Author%4\$s feature in the %5\$splugin settings%6\$s page.", 'molongui-authorship' ), '<strong>', '</strong>', '<strong>', '</strong>', '<a href="'.authorship_options_url().'" target="_blank">', '</a>' );
    $short_tip = __( "Type to search and select an author.", 'molongui-authorship' );
}

?>

<style>
    #molongui-post-authors { /*width: 100%; max-width: 25rem;*/ }
    #molongui-post-authors p.molongui-post-authors__tip { margin-top: 6px; line-height: 18px; font-size: 12px; color: #666; }
    .molongui-post-authors__tip--quick { display: none; }
    .molongui-post-authors__selector {  }
    .molongui-post-authors__controls { height: 40px; display: flex; justify-content: space-between; }
    .molongui-post-authors__add-form { display: flex; flex-direction: column; row-gap: 10px; margin-top: 1em; padding: 10px; background: #f6f7f7; border: 1px dashed lightgray; border-radius: 3px; }
    .molongui-post-authors__add-form label { font-size: 12px; font-weight: 600; margin-bottom: -8px; }
    .molongui-post-authors__add-form button.button { margin-top: 5px; height: 40px; }
    .molongui-post-authors__search { flex-grow: 2; }
    .molongui-post-authors__input
    {
        display: block;
        width: 100%;
        max-width: 25rem; /*max-width: none;*/
        height: 40px;
        min-height: 40px;
        margin: 0;
        padding: 0 24px 0 8px; /*padding: 0px 34px 0px 16px; */
        appearance: none;
        -webkit-appearance: none;
        border-color: #949494; /*#8c8f94;*/
        border-radius: 3px;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
        box-sizing: border-box;
        box-shadow: none !important;
        line-height: 2;
        font-family: inherit;
        color: rgb(30, 30, 30); /*color: #2c3338;*/
        cursor: pointer;
        vertical-align: middle;
    }
    .molongui-post-authors__input:disabled { background: #f4f4f4; }
    .molongui-post-authors__input:disabled::placeholder { color: #b7b7b7; }
    .molongui-post-authors__spinner { position: relative; top: -30px; float: right; margin: 0 10px; }
    .molongui-post-authors__controls .molongui-post-authors__add-new { margin-left: 1em; padding: 0 9px; flex-shrink: 0; align-content: center; line-height: 1; cursor: pointer; }
    .molongui-post-authors__controls .molongui-post-authors__add-new .dashicons { font-size: 14px; height: 14px; }
    .molongui-post-authors__list { margin-top: 1em; }
    .molongui-post-authors__list .molongui-post-authors__list-title { display: block; margin-bottom: .5em; font-size: 11px; font-weight: 500; text-transform: uppercase; /*color: #1e1e1e;*/ }
    .molongui-post-authors__item { padding: 10px 0; /*border: 1px solid lightgray; border-left: 0; border-right: 0;*/ }
    .molongui-post-authors__item:not(:only-child) { cursor: move; }
    .molongui-post-authors__item:not(:last-of-type) { border-bottom: 1px solid lightgray; }
    .molongui-post-authors__row { display: flex; align-items: center; line-height: 1; }
    .molongui-post-authors__actions { flex-shrink: 0; margin-left: 10px; padding-left: 5px; border-left: 1px solid #ccc; }
    .molongui-authorship-no-multiple-authors .molongui-post-authors__actions .dashicons:not(.dashicons-no-alt) { display: none; }
    .molongui-post-authors__item .dashicons { /*padding-right: 5px; color: red;*/ vertical-align: middle; color: gray; cursor: pointer; }
    .molongui-post-authors__item .dashicons:hover { background: #f0f0f0; }
    .molongui-post-authors__item .molongui-post-authors__delete { color: red; }
    .molongui-post-authors__item .molongui-post-authors__delete:hover { background: red; color: white; }
    .molongui-post-authors__item:only-child .dashicons:not(.dashicons-no-alt) { color: #ccc; cursor: not-allowed; }
    .molongui-post-authors__item:only-child .dashicons:not(.dashicons-no-alt):hover { background: transparent !important; }
    .molongui-post-authors__item:first-of-type .molongui-post-authors__up { color: #ccc; cursor: not-allowed; }
    .molongui-post-authors__item:first-of-type .molongui-post-authors__up:hover { background: transparent; }
    .molongui-post-authors__item:last-of-type .molongui-post-authors__down { color: #ccc; cursor: not-allowed; }
    .molongui-post-authors__item:last-of-type .molongui-post-authors__down:hover { background: transparent; }
    .molongui-post-authors__avatar { flex-shrink: 0; margin-right: 5px; }
    .molongui-post-authors__avatar img { width: 20px; height: 20px; }
    .molongui-post-authors__name { flex-grow: 2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ui-sortable-helper { background: white; border: 2px dashed gray !important; }
    .ui-state-highlight { height: 30px; background: #f4e2c9; border: 2px dashed orange; opacity: 0.4; }
    .inline-edit-col #molongui-post-authors { margin-left: 6em; }
    .inline-edit-col .molongui-post-authors__list { margin-top: 0; }
    .inline-edit-col .molongui-post-authors__tip { display: none; }
    .inline-edit-col .molongui-post-authors__tip--quick { display: block; }
    @keyframes molongui-post-authors-spinner
    {
        to { transform: rotate(360deg); }
    }
    .molongui-post-authors-spinner:before
     {
         content: '';
         box-sizing: border-box;
         position: absolute;
         width: 20px;
         height: 20px;
         margin-top: -10px;
         margin-left: -10px;
         border-radius: 50%;
         border: 2px solid #ccc;
         border-top-color: #07d;
         animation: molongui-post-authors-spinner .6s linear infinite;
     }
</style>

<div id="molongui-post-authors" class="hide-if-no-js <?php echo !$co_authors ? 'molongui-authorship-no-multiple-authors' : ''; ?>">

    <div class="molongui-post-authors__selector">

        <p class="molongui-post-authors__tip"><?php echo wp_kses_post( $tip ); ?></p>

        <div class="molongui-post-authors__controls">
            <div class="molongui-post-authors__search">
                <input class="molongui-post-authors__input" type="text" placeholder="<?php esc_attr_e( "Search for an author", 'molongui-authorship' ); ?>" />
                <span id="authors-loading" class="molongui-post-authors__spinner spinner"></span>
            </div>
            <?php if ( ( current_user_can( 'edit_others_pages' ) or current_user_can( 'edit_others_posts' ) ) and !current_user_can( 'authorship_granted_edit_others_posts' ) and 'edit' === $screen ) : ?>
            <a class="molongui-post-authors__add-new button" title="<?php esc_html_e( "Quick add a new author. To add an existing author, type their name in the search box on the left.", 'molongui-authorship' ); ?>"><span class="dashicons dashicons-plus-alt2"></span></a>
            <?php endif; ?>
        </div>

        <?php if ( ( current_user_can( 'edit_others_pages' ) or current_user_can( 'edit_others_posts' ) ) and !current_user_can( 'authorship_granted_edit_others_posts' ) ) : ?>
        <div class="molongui-post-authors__add-form" style="display:none">
            <p class="molongui-post-authors__tip"><?php esc_html_e( "Use this form only to create a new author. To add an existing author, type their name in the search box above.", 'molongui-authorship' ); ?></p>
            <label for="molongui-new-author-name"><?php esc_html_e( "Display Name", 'molongui-authorship' ); ?></label>
            <input name="molongui-new-author-name" class="" type="text" >
            <label for="molongui-new-author-type"><?php esc_html_e( "Author Type", 'molongui-authorship' ); ?></label>
            <select name="molongui-new-author-type" class="">
                <?php if ( current_user_can( 'create_users' ) ) : ?>
                <option value="user"><?php esc_attr_e( "WP User", 'molongui-authorship' ); ?></option>
                <?php endif; ?>
                <option value="guest"><?php esc_attr_e( "Guest Author", 'molongui-authorship' ); ?></option>
            </select>
            <label for="molongui-new-author-email"><?php esc_html_e( "Email Address", 'molongui-authorship' ); ?></label>
            <input name="molongui-new-author-email" class="" type="email" >
            <?php wp_nonce_field( 'molongui_authorship_quick_add_author', 'molongui_authorship_quick_add_author_nonce' ); ?>
            <button class="button"><?php esc_html_e( "Add New Author", 'molongui-authorship' ); ?></button>
        </div>
        <?php endif; ?>

        <p class="molongui-post-authors__tip molongui-post-authors__tip--quick"><?php echo esc_html( $short_tip ); ?></p>
    </div>

    <div class="molongui-post-authors__list">
        <span class="molongui-post-authors__list-title">
            <?php esc_html_e( "Post Authors", 'molongui-authorship' ); ?>
        </span>
        <?php if ( 'edit' === $screen ) : ?>
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
                            <?php echo esc_html( $author->get_name() ); ?>
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

    <?php wp_nonce_field( 'molongui_post_authors', 'molongui_post_authors_nonce' ); ?>

</div>

<?php
//enqueue_authors_metabox_scripts();