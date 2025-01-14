<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
?>

<div class="m-a-box-content-top">

	<?php if ( $options['author_box_layout'] == 'stacked' ) include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-profile-title.php'; ?>

</div><!-- End of .m-a-box-content-top -->

<div class="m-a-box-content-middle">

    <!-- Author picture -->
    <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-avatar.php'; ?>

    <!-- Author social -->
    <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-socialmedia.php'; ?>

    <!-- Author data -->
    <div class="m-a-box-item m-a-box-data">

        <!-- Author name -->
        <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-name.php'; ?>

        <!-- Author metadata -->
        <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-meta.php'; ?>

        <!-- Author bio -->
        <?php include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-bio.php'; ?>

        <?php if ( $options['author_box_layout'] == 'slim' and !empty( $options['author_box_show_related_posts'] ) ) : ?>

            <!-- Author related posts -->
            <div class="m-a-box-related" data-related-layout="<?php echo $options['author_box_related_layout']; ?>">
                <div class="m-a-box-item m-a-box-related-entries" <?php echo ( $options['author_box_layout'] == 'slim' ? 'style="display: none;"' : '' ); ?>>

                    <ul>
                        <?php
                        if ( !empty( $profile['posts'] ) )
                        {
                            /*!
                             * FILTER HOOK
                             * Allows filtering the related posts markup before it is generated.
                             *
                             * @param string $related_layout Default related posts layout markup. Defaults to null.
                             * @param array  $options        Plugin settings.
                             * @param array  $profile        Author data.
                             * @since 5.0.0
                             */
                            $related_layout = apply_filters( 'molongui_authorship/author_box_related_markup', null, $options, $profile );

                            if ( is_null( $related_layout ) )
                            {
                                $file = MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/related/html-'.$options['author_box_related_layout'].'.php';

                                if ( !file_exists( $file ) )
                                {
                                    $file = MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/related/html-layout-1.php';
                                }

                                ob_start();
                                include $file;
                                $related_layout = ob_get_clean();
                            }

                            echo $related_layout;
                        }
                        else
                        {
                            echo ' <span class="m-a-box-string-no-related-posts">'. ( $options['author_box_related_none'] ? $options['author_box_related_none'] : __( "This author does not have any more posts.", 'molongui-authorship' ) ).'</span>';
                        }
                        ?>
                    </ul>

                </div><!-- End of .m-a-box-related-entries -->
            </div><!-- End of .m-a-box-related -->

        <?php endif; ?>

    </div><!-- End of .m-a-box-data -->

</div><!-- End of .m-a-box-content-middle -->

<div class="m-a-box-content-bottom"></div><!-- End of .m-a-box-content-bottom -->
