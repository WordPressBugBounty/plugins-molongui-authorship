<?php

use Molongui\Authorship\Admin\Admin_Post;
use Molongui\Authorship\Author;
use Molongui\Authorship\Authors;
use Molongui\Authorship\Author_Box;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$options = Settings::get();
$profile = array();// new stdClass();

if ( !empty( $_GET['author'] ) )
{
    $get           = explode( '-', sanitize_text_field( $_GET['author'] ) );
    $author        = new Author( $get[1], $get[0] );
    $profile[$get] = $author->get_data();
}
else
{
    $current_user = wp_get_current_user();
    if ( apply_filters( 'molongui_authorship/preview_dummy_data', true ) )
    {
        $profile['dummy-0'] = $this->get_dummy_data( $options );
    }
    elseif ( array_intersect( (array)$current_user->roles, explode(",", $options['user_roles'] ) ) )
    {
        $author = new Author( $current_user->ID, 'user' );
        $profile['user-'.$current_user->ID] = $author->get_data();
    }
    else
    {
        $random = Authors::get_random_author( 'author', $options['user_roles'] );
        $type   = is_a( $random, 'WP_User' ) ? 'user' : 'guest';
        $author = new Author( $random->ID, $type );
        $profile[$type.'-'.$random->ID] = $author->get_data();
    }
}

add_filter( 'molongui_authorship/is_author_box_preview', '__return_true' );

?>

<div id="m-editor-preview">

    <div id="m-editor-author-select">
        <?php Admin_Post::author_selector( null, 'box-editor' ); ?>
        <input type="hidden" id="active-author" value="">
    </div>

    <div id="m-editor-live-preview">
        <div class="m-editor-preview__label">
            <?php _e( "Preview", 'molongui-authorship' ); ?>
        </div>
        <div id="m-editor_preview__warning" class="m-editor-warning" data-pro-options="" style="display:none;">
            <?php _e( "Your author box design uses premium features available only in the Pro version. You can preview them, but they won't be saved and will revert to defaults unless you upgrade.", 'molongui-authorship' ); ?>
        </div>
        <div id="m-editor-live-preview__box">
            <?php echo Author_Box::markup( $profile ); ?>
        </div>
        <div id="m-editor-live-preview__loader"><div></div><div></div></div>

        <div id="m-editor-disclaimer">
            <span class="dashicons dashicons-warning"></span>
            <?php _e( "The way the author box is displayed on your site's frontend may differ slightly from what is shown here.", 'molongui-authorship' ); ?>
        </div>
    </div>

</div>

<?php

remove_filter( 'molongui_authorship/is_author_box_preview', '__return_true' );
