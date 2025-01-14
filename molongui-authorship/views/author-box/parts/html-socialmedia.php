<?php

use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Settings;
use Molongui\Authorship\Social;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

if ( !empty( $options['author_box_social_show'] ) )
{
	$networks = Social::get( 'enabled' );
    if ( $profile['show_icon_web'] )   $networks['web']   = array ( 'name' => 'Website', 'url' => 'https://www.example.com/', 'color' => '#333', 'premium' => false );
	if ( $profile['show_icon_mail'] )  $networks['mail']  = array ( 'name' => 'E-mail',  'url' => 'your_name@example.com',    'color' => '#333', 'premium' => false );
	if ( $profile['show_icon_phone'] ) $networks['phone'] = array ( 'name' => 'Phone',   'url' => '123456789',                'color' => '#333', 'premium' => false );
    foreach ( $networks as $id => $network )
    {
        if ( !empty( $profile[$id] ) )
        {
            $_networks[$id] = $profile[$id];
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
        foreach ( $_networks as $id => $url )
        {
            if ( 'mail' === $id )
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
            elseif ( 'phone' === $id )
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
                if ( 'wechat' === $id )
                {
                    $url = esc_attr( $url );
                }
                else
                {
                    $url = esc_url( set_url_scheme( $url ) );
                }

                if ( 'twitter' === $id )
                {
                    /*!
                     * DEPRECATED
                     * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
                     *
                     * @since      4.6.19
                     * @deprecated 5.0.0
                     */
                    if ( apply_filters( 'molongui_authorship/apply_filters_deprecated', true ) )
                    {
                        $id = apply_filters_deprecated( 'authorship/twitter_icon', array( $id ), '5.0.0', 'molongui_authorship/twitter_icon' );
                    }

                   /*!
                    * FILTER HOOK
                    * Allows filtering of Twitter's logo to use the original bird logo.
                    *
                    * @param string $id Accepts 'twitter' or 'twitterbird'. Default is 'twitter'.
                    * @since 5.0.0
                    */
                   $id = apply_filters( 'molongui_authorship/twitter_icon', $id );
                }
            }
            ?>
                <div class="m-a-box-social-icon m-a-list-social-icon">
                    <a class="m-icon-container m-ico-<?php echo $id; ?> m-ico-<?php echo $ico_style; ?>" <?php echo $nofollow; ?>
                       href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"
                       target="<?php echo $target; ?>" <?php echo ( $add_microdata ? 'itemprop="sameAs"' : '' ); ?>
                       aria-label="<?php printf( __( "View %s's %s profile", 'molongui-authorship' ), esc_attr( $profile['name'] ), ucfirst( $id ) ); ?>">
                        <i class="m-a-icon-<?php echo $id; ?>"></i>
                    </a>
                </div>
            <?php
        }
    echo '</div>';
}
