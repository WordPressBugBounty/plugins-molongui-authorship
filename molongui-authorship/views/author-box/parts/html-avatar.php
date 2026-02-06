<?php

use Molongui\Authorship\Common\Utils\Plugin;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
?>

<?php if ( !empty( $options['author_box_avatar_show'] ) ) : ?>
	<div class="m-a-box-item m-a-box-avatar" data-source="<?php echo $options['author_box_avatar_source']; ?>">
		<?php
            if ( empty( $options['author_box_avatar_link'] ) or 'none' === $options['author_box_avatar_link']
                 or
                 ( 'website' === $options['author_box_avatar_link'] and empty( $profile->get_website() ) )
                 or
                 ( 'custom' === $options['author_box_avatar_link'] and empty( $profile->get_meta( 'custom_link' ) ) )
                 or
                 ( 'archive' === $options['author_box_avatar_link']
                    and
                    ( ( 'guest' === $profile->get_type() and !Plugin::has_pro() )
                        or
                      ( 'guest' === $profile->get_type() and !$options['guest_archive_enabled'] )
                        or
                      ( 'user' === $profile->get_type() and !$options['user_archive_enabled'] )
                    )
                 )
            ){
	            ?>
                <span>
                    <?php echo $profile->get_avatar( '', 'box' ); ?>
                </span>
                <?php
            }
            else
            {
                switch ( $options['author_box_avatar_link'] )
                {
                    case 'website': $url = $profile->get_website(); break;
                    case 'custom' : $url = $profile->get_meta( 'custom_link' ); break;
                    case 'archive': default: $url = $profile->get_archive_url(); break;
                }
                ?>
                <a class="m-a-box-avatar-url" href="<?php echo esc_url( $url ); ?>">
                    <?php echo $profile->get_avatar( '', 'box' ); ?>
                </a>
                <?php
            }
        ?>
	</div>
<?php endif; ?>
