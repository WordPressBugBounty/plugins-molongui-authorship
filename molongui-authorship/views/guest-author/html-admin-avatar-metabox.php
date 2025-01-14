<?php

use Molongui\Authorship\Admin\Author_Box_Editor;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
?>

<div class="molongui-metabox">

    <div class="m-field">

        <?php if ( empty( $options['author_box_avatar_show'] ) ) : ?>

            <label class="m-title"><strong><?php _e( "Avatar Display Disabled", 'molongui-authorship' ); ?></strong></label>
            <p class="m-description">
                <?php _e( "Avatar display is disabled", 'molongui-authorship' ); echo ". "; printf( __( "You can enable author avatars at the %sMolongui Author Box Editor%s. There you can select whether to display custom local images, gravatars or author acronym.", 'molongui-authorship' ), '<a href="'.Author_Box_Editor::url().'">', '</a>' ) ; ?>
            </p>
            <a class="button button-large" href="<?php echo Author_Box_Editor::url(); ?>"><?php _e( "Open Customizer", 'molongui-authorship' ); ?></a>

        <?php else : ?>

            <?php if ( 'local' == $options['author_box_avatar_source'] ) : ?>
                <label class="m-title"><strong><?php _e( "Local Avatar Disabled", 'molongui-authorship' ); ?></strong></label>
                <p class="m-description">
                    <?php
                        switch ( $options['author_box_avatar_fallback'] )
                        {
                            case 'gravatar':
                                _e( "Local Avatar feature is disabled", 'molongui-authorship' ); echo ". "; _e( "As configured, author avatar will be taken from Gravatar using author's email address and if none associated, a default image will be shown.", 'molongui-authorship' );
                            break;

                            case 'acronym':
                                _e( "Local Avatar feature is disabled", 'molongui-authorship' ); echo ". "; _e( "As configured, author acronym will be displayed as author profile image.", 'molongui-authorship' );
                            break;

                            case 'none':
                                _e( "Local Avatar feature is disabled", 'molongui-authorship' ); echo ". "; _e( "And as there is no fallback configured, no image will be shown as author avatar.", 'molongui-authorship' );
                            break;
                        }
                    ?>
                </p>
            <?php else : ?>
                <p class="m-description">
                    <?php
                        switch ( $options['author_box_avatar_source'] )
                        {
                            case 'gravatar':
                                _e( "As configured, author avatar will be taken from Gravatar using author's email address and if none associated, a default image will be shown.", 'molongui-authorship' );
                            break;

                            case 'acronym':
                                _e( "As configured, author acronym will be displayed as author profile image.", 'molongui-authorship' );
                            break;
                        }
                    ?>
                </p>
            <?php endif; ?>

            <p class="m-description">
                <?php printf( __( "If you want to upload a custom image for this guest author, enable local avatars for both, users and guests, on the plugin settings page (click %shere%s).", 'molongui-authorship' ), '<a href="'.Settings::url().'">', '</a>' ); ?>
            </p>
            <a class="button button-large" href="<?php echo Settings::url(); ?>"><?php _e( "Settings Page", 'molongui-authorship' ); ?></a>

        <?php endif; ?>
    </div>

</div>
