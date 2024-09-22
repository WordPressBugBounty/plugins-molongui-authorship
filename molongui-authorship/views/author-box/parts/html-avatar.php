<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
?>

<?php if ( $author['img'] and !empty( $options['author_box_avatar_show'] ) ) : ?>
	<div class="m-a-box-item m-a-box-avatar" data-source="<?php echo $options['author_box_avatar_source']; ?>">
		<?php
            if ( empty( $options['author_box_avatar_link'] ) or 'none' === $options['author_box_avatar_link']
                 or
                 ( 'website' === $options['author_box_avatar_link'] and empty( $author['web'] ) )
                 or
                 ( 'custom' === $options['author_box_avatar_link'] and empty( $author['custom_link'] ) )
                 or
                 ( 'archive' === $options['author_box_avatar_link']
                    and
                    ( ( 'guest' === $author['type'] and !authorship_has_pro() )
                        or
                      ( 'guest' === $author['type'] and !$options['guest_pages'] )
                        or
                      ( 'user' === $author['type'] and !$options['user_archive_enabled'] )
                    )
                 )
            ){
	            ?>
                <span>
                    <?php echo $author['img']; ?>
                </span>
                <?php
            }
            else
            {
                switch ( $options['author_box_avatar_link'] )
                {
                    case 'website': $url = $author['web']; break;
                    case 'custom' : $url = $author['custom_link']; break;
                    case 'archive': default: $url = $author['archive']; break;
                }
                ?>
                <a class="m-a-box-avatar-url" href="<?php echo esc_url( $url ); ?>">
                    <?php echo $author['img']; ?>
                </a>
                <?php
            }
        ?>
	</div>
<?php endif; ?>