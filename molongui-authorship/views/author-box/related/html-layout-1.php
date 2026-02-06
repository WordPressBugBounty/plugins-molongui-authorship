<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$related_posts_ids = $profile->get_posts_ids( array
(
    'post_type' => 'related',
) );

if ( empty( $related_posts_ids ) )
{
    return;
}

foreach( $related_posts_ids as $related_id )
{
    ?>
    <li>
        <div class="m-a-box-related-entry" <?php echo ( $add_microdata ? 'itemscope itemtype="http://schema.org/CreativeWork"' : '' ); ?>>
            <?php if ( $add_microdata ) : ?>
                <div class="molongui-display-none" itemprop="author" itemscope itemtype="http://schema.org/Person">
                    <div itemprop="name"><?php echo esc_html( $profile->get_display_name() ); ?></div>
                    <div itemprop="url"><?php echo esc_url( $profile->get_meta( 'archive_url' ) ); ?></div>
                </div>
            <?php endif; ?>
            <div class="m-a-box-related-entry-title">
                <i class="m-a-icon-doc"></i>
                <a class="molongui-remove-underline" href="<?php echo esc_url( get_permalink( $related_id ) ); ?>" <?php echo ( $add_microdata ? 'itemprop="url"' : '' ); ?>>
                    <span <?php echo ( $add_microdata ? 'itemprop="headline"' : '' ); ?>>
                        <?php echo wp_kses_post( get_the_title( $related_id ) ); ?>
                    </span>
                </a>
            </div>
        </div>
    </li>
    <?php
}
