<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

foreach( $profile['posts'] as $related_id )
{
    ?>
    <li>
        <div class="m-a-box-related-entry" <?php echo ( $add_microdata ? 'itemscope itemtype="http://schema.org/CreativeWork"' : '' ); ?>>

            <?php if ( $add_microdata ) : ?>
            <div class="molongui-display-none" itemprop="author" itemscope itemtype="http://schema.org/Person">
                <div itemprop="name"><?php echo esc_html( $profile['name'] ); ?></div>
                <div itemprop="url"><?php echo esc_url( $profile['archive_url'] ); ?></div>
            </div>
            <?php endif; ?>

            <!-- Related entry thumb -->
            <div class="m-a-box-related-entry-thumb">
                <?php if ( has_post_thumbnail( $related_id ) ) : ?>
                    <a href="<?php echo esc_url( get_permalink( $related_id ) ); ?>">
                        <?php echo get_the_post_thumbnail( $related_id, 'thumbnail', $attr = ( $add_microdata ? array( 'itemprop' => 'thumbnailUrl' ) : array() ) ) ?>
                    </a>
                <?php else : ?>
                    <img src="<?php echo esc_url( MOLONGUI_AUTHORSHIP_URL.'assets/img/related_placeholder.svg' ); ?>" width="<?php echo esc_attr( get_option( 'thumbnail_size_w' ).'px' ); ?>">
                <?php endif; ?>
            </div>

            <div class="m-a-box-related-entry-data">
                <!-- Related entry date -->
                <div class="m-a-box-related-entry-date" <?php echo ( $add_microdata ? 'itemprop="datePublished"' : '' ); ?>>
                    <?php echo get_the_date( '', $related_id ); ?>
                </div>

                <!-- Related entry title -->
                <div class="m-a-box-related-entry-title">
                    <a class="molongui-remove-underline" href="<?php echo esc_url( get_permalink( $related_id ) ); ?>" <?php echo ( $add_microdata ? 'itemprop="url"' : '' ); ?>>
                        <span <?php echo ( $add_microdata ? 'itemprop="headline"' : '' ); ?>>
                            <?php echo wp_kses_post( get_the_title( $related_id ) ); ?>
                        </span>
                    </a>
                </div>
            </div>

        </div>
    </li>
    <?php
}
