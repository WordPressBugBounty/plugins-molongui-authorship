<?php

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

if ( !empty( $options['author_box_social_show'] ) )
{
    $_networks = $profile->get_social();
    if ( $profile->get_meta( 'show_icon_web' ) )
    {
        $website = $profile->get_meta( 'web' );

        if ( !empty( $website ) )
        {
            $_networks['web'] = $website;
        }
    }

    if ( $profile->get_meta( 'show_icon_mail' ) )
    {
        $email = $profile->get_meta( 'mail' );

        if ( !empty( $email ) )
        {
            $_networks['mail'] = $email;
        }
    }

    if ( $profile->get_meta( 'show_icon_phone' ) )
    {
        $phone = $profile->get_meta( 'phone' );

        if ( !empty( $phone ) )
        {
            $_networks['phone'] = $phone;
        }
    }
    if ( empty( $_networks ) )
    {
        return;
    }
	if ( isset( $options['author_box_social_style'] ) )
	{
		$ico_style = $options['author_box_social_style'];
		if ( $ico_style == 'default' )
        {
            $ico_style = '';
        }
	}
	$nofollow = $options['social_profiles_nofollow'] ? 'rel="nofollow"' : '' ;
    $target = !empty( $options['author_box_social_target'] ) ? '_blank' : '_self' ;
	echo '<div class="m-a-box-item m-a-box-social '.( ( isset( $options['author_box_profile_layout'] ) and !in_array( $options['author_box_profile_layout'], array( 'layout-7', 'layout-8' ) ) and isset( $options['author_box_profile_valign'] ) and !empty( $options['author_box_profile_valign'] ) and $options['author_box_profile_valign'] != 'center' ) ? 'molongui-align-self-'.$options['author_box_profile_valign'] : '' ).'">';
        foreach ( $_networks as $network_id => $url )
        {
            if ( 'mail' === $network_id )
            {
                $mail = sanitize_email( $url );
                if ( !empty( $options['author_email_encoded'] ) )
                {
                    $url = esc_attr( Helpers::ascii_encode( 'mailto:'.$mail ) );
                }
                else
                {
                    $url = esc_url( 'mailto:'.$mail );
                }
            }
            elseif ( 'phone' === $network_id )
            {
                $phone = $url;
                if ( !empty( $options['author_phone_encoded'] ) )
                {
                    $url = esc_attr( Helpers::ascii_encode( 'tel:'.$phone ) );
                }
                else
                {
                    $url = esc_url( 'tel:'.$phone );
                }
            }
            else
            {
                if ( 'wechat' === $network_id )
                {
                    $url = esc_attr( $url );
                }
                else
                {
                    $url = esc_url( set_url_scheme( $url ) );
                }

                if ( 'twitter' === $network_id )
                {
                    /*!
                    * FILTER HOOK
                    * Allows filtering Twitter logo to use the original bird logo.
                    *
                    * @param string $network_id Accepts 'twitter' or 'twitterbird'. Default is 'twitter'.
                    * @since 5.0.0
                    */
                   $network_id = apply_filters( 'molongui_authorship/twitter_icon', $network_id );
                }
            }
            ?>
                <div class="m-a-box-social-icon m-a-list-social-icon">
                    <a class="m-icon-container m-ico-<?php echo $network_id; ?> m-ico-<?php echo $ico_style; ?>" <?php echo $nofollow; ?>
                       href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
                       target="<?php echo $target; ?>" <?php echo ( $add_microdata ? 'itemprop="sameAs"' : '' ); ?>
                       aria-label="<?php printf( __( "View %s's %s profile", 'molongui-authorship' ), esc_attr( $profile->get_display_name() ), ucfirst( $network_id ) ); ?>">
                        <i class="m-a-icon-<?php echo $network_id; ?>"></i>
                    </a>
                </div>
            <?php
        }
    echo '</div>';
}
