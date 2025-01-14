<?php

use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

if ( empty( $options['author_box_name_show'] ) )
{
    return;
}
$name_tag = Settings::get( 'author_box_name_tag', 'h5' );

?>

<div class="m-a-box-name m-a-box-title">
	<<?php echo $name_tag; ?> <?php echo ( $add_microdata ? 'itemprop="name"' : '' ); ?>>
        <?php
            if ( empty( $options['author_box_name_link'] ) or 'none' === $options['author_box_name_link']
                 or
                 ( 'website' === $options['author_box_name_link'] and empty( $profile['web'] ) )
                 or
                 ( 'custom' === $options['author_box_name_link'] and empty( $profile['custom_link'] ) )
                 or
                 ( 'archive' === $options['author_box_name_link']
                    and
                    ( ( 'guest' === $profile['type'] and !Plugin::has_pro() )
                        or
                      ( 'guest' === $profile['type'] and !Settings::get( 'guest_archive_enabled', false ) )
                        or
                      ( 'user' === $profile['type'] and !Settings::get( 'user_archive_enabled', true )  )
                    )
                )
            ){
	            ?>
                <span>
			        <?php echo esc_html( $profile['name'] ); ?>
                </span>
	            <?php
            }
            else
            {
                switch ( $options['author_box_name_link'] )
                {
                    case 'website': $url = $profile['web']; break;
                    case 'custom' : $url = $profile['custom_link']; break;
                    case 'archive': default: $url = $profile['archive_url']; break;
                }
                ?>
                <a class="m-a-box-name-url <?php echo ( $options['author_box_name_underline'] == 'remove' ? 'molongui-remove-underline' : '' ); ?>" href="<?php echo esc_url( $url ); ?>" <?php echo ( $add_microdata ? 'itemprop="url"' : '' ); ?>>
		            <?php echo esc_html( $profile['name'] ); ?>
                </a>
	            <?php
            }
        ?>
	</<?php echo $name_tag; ?>>
</div>
