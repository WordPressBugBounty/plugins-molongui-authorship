<?php

use Molongui\Authorship\Social;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

?>

<div id="molongui-social-profiles" class="hide-if-no-js">

    <div class="molongui-social-profiles__selector">
        <div class="molongui-social-profiles__controls">
            <div class="molongui-social-profiles__search">
                <svg class="molongui-social-profiles__search--icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input class="molongui-social-profiles__search--input" type="text" placeholder="<?php echo esc_attr( __( "Search profiles...", 'molongui-authorship' ) ); ?>" />
                <span id="profiles-loading" class="molongui-social-profiles__search--spinner spinner"></span>
            </div>
        </div>
    </div>

    <div class="molongui-social-profiles__list">
        <?php foreach ( Social::get( 'enabled' ) as $social_profile ) : ?>
            <div id="<?php echo esc_attr( $social_profile['id'] ); ?>" class="molongui-social-profiles__item" data-profile-id="<?php echo esc_attr( $social_profile['id'] ); ?>">
                <div class="molongui-social-profiles__row">
                    <div class="molongui-social-profiles__icon">
                        <i class="<?php echo esc_attr( $social_profile['icon'] ); ?>"></i>
                    </div>
                    <div class="molongui-social-profiles__name" title="<?php echo esc_attr( __( "Drag this profile to reorder", 'molongui-authorship' ) ); ?>">
                        <?php echo esc_html( $social_profile['label'] ); ?>
                    </div>
                    <div class="molongui-social-profiles__actions">
                        <?php
                        $delete_icon    = '<span class="dashicons dashicons-no-alt molongui-social-profiles__delete" title="' . esc_attr__( "Remove", 'molongui-authorship' ) . '"></span>';
                        $move_up_icon   = '<span class="dashicons dashicons-arrow-up-alt2 molongui-social-profiles__up" title="' . esc_attr__( "Move up", 'molongui-authorship' ) . '"></span>';
                        $move_down_icon = '<span class="dashicons dashicons-arrow-down-alt2 molongui-social-profiles__down" title="' . esc_attr__( "Move down", 'molongui-authorship' ) . '"></span>';
                        printf ( '%s%s%s'
                            , apply_filters( 'molongui_authorship/social_profile_selector/show_arrows', true ) ? $move_up_icon : ''
                            , apply_filters( 'molongui_authorship/social_profile_selector/show_arrows', true ) ? $move_down_icon : ''
                            , $delete_icon
                        );
                        ?>
                    </div>
                    <input type="hidden" name="social_profiles[]" value="<?php echo esc_attr( $social_profile['id'] ); ?>">
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
