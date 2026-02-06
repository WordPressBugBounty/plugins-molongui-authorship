<?php

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$add_microdata = ( !empty( $options['seo_settings_enabled'] ) and !empty( $options['schema_markup_enabled'] ) );
$show_related  = ( $options['author_box_layout'] != 'slim' and !empty( $options['author_box_show_related_posts'] ) and ( $profile->has_posts() or !empty( $options['author_box_related_show_empty'] ) ) );
?>

<?php if ( apply_filters( 'authorship/add_html_comments', true ) ) : ?>
<!-- MOLONGUI AUTHORSHIP PLUGIN <?php echo MOLONGUI_AUTHORSHIP_VERSION; ?> -->
<!-- <?php echo MOLONGUI_AUTHORSHIP_WEB; ?> -->
<?php endif; ?>

<?php ob_start(); ?>

<div class="molongui-clearfix"></div>
<div class="m-a-box <?php echo ( !empty( $options['author_box_custom_css_class'] ) ? $options['author_box_custom_css_class'] : '' ); ?>"
     <?php echo ( apply_filters( '_authorship/doing_shortcode/author_box', false ) ? 'data-is-shortcode="yes"' : '' ); ?>
     data-box-layout="<?php echo ( isset( $options['author_box_layout'] ) ? $options['author_box_layout'] : '' ); ?>"
     data-box-position="<?php echo ( isset( $options['author_box_position'] ) ? $options['author_box_position'] : '' ); ?>"
     data-multiauthor="<?php echo ( $multiple ? 'true' : 'false' ); ?>"
     data-author-id="<?php echo $profile->get_id(); ?>"
     data-author-type="<?php echo $profile->get_type(); ?>"
     data-author-archived="<?php echo $profile->is_archived(); ?>">

	<?php
    if ( !empty( $options['author_box_header_title'] ) )
    {
        if ( $profile === reset($profiles ) )
        {
            include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-header.php';
        }
    }
    if ( $show_tabs = ( !empty( $options['author_box_layout'] ) and $options['author_box_layout'] == 'tabbed' ) )
    {
        echo '<div class="m-a-box-tabs">';

        include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-tabs.php';
    }

	?>

    <div class="m-a-box-container">

        <div class="m-a-box-tab m-a-box-content m-a-box-profile"
             data-profile-layout="<?php echo $options['author_box_profile_layout']; ?>"
             data-author-ref="<?php echo $profile->get_type().'-'.$profile->get_id(); ?>"
             <?php echo ( $add_microdata ? 'itemscope itemid="'.$profile->get_archive_url().'" itemtype="https://schema.org/Person"' : '' ); ?>
        >
            <?php

            /*!
             * FILTER HOOK
             * Allows filtering the author profile markup before it is generated.
             *
             * @param string $profile_layout Default author profile layout markup. Defaults to null.
             * @param array  $options        Plugin settings.
             * @param array  $profile        Author data.
             * @since 5.0.0
             */
            $profile_layout = apply_filters( 'molongui_authorship/author_box_profile_markup', null, $options, $profile );

            if ( is_null( $profile_layout ) )
            {
                ob_start();
                include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/profile/html-layout-1.php';
                $profile_layout = ob_get_clean();
            }

            echo $profile_layout;

            ?>
        </div><!-- End of .m-a-box-profile -->

        <?php if ( $show_related ) : ?>

        <div class="m-a-box-tab m-a-box-content m-a-box-related" data-related-layout="<?php echo $options['author_box_related_layout']; ?>">

            <div class="m-a-box-content-top">

		        <?php if ( $options['author_box_layout'] == 'stacked' ) include MOLONGUI_AUTHORSHIP_DIR . 'views/author-box/parts/html-related-title.php'; ?>

            </div><!-- End of .m-a-box-content-top -->

            <div class="m-a-box-content-middle">

                <!-- Related entries -->
                <div class="m-a-box-item m-a-box-related-entries" <?php echo ( $options['author_box_layout'] === 'slim' ? 'style="display: none;"' : '' ); ?>>

                    <ul>
                        <?php
                        if ( $profile->has_posts() )
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

            </div><!-- End of .m-a-box-content-middle -->

            <div class="m-a-box-content-bottom"></div><!-- End of .m-a-box-content-bottom -->

        </div><!-- End of .m-a-box-related -->

        <?php endif; ?>

    </div><!-- End of .m-a-box-container -->

	<?php if ( $show_tabs ) echo '</div><!-- End of .m-a-box-tabs -->'; ?>

</div><!-- End of .m-a-box -->

<?php echo Helpers::minify_html( ob_get_clean() ); ?>
