<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
$header_html_tag = ( !empty( $options['author_box_header_tag'] ) ? $options['author_box_header_tag'] : 'h3' );
?>

<!-- Author Box Header -->
<div class="m-a-box-item m-a-box-header">
    <<?php echo $header_html_tag; ?>>
        <?php if ( !empty( $options['author_box_header_url'] ) ) : ?><a class="m-a-box-header-url" href="<?php echo esc_url( $options['author_box_header_url'] ); ?>"><?php endif; ?>
        <span class="m-a-box-header-title">
            <?php
            if ( !isset( $profile ) )
            {
                $profile = null;

                if ( !empty( $profiles[0] ) )
                {
                    $profile = $profiles[0];
                }
            }
            echo esc_html( apply_filters( 'authorship/box/header', ( $options['author_box_header_title'] ? $options['author_box_header_title'] : __( "About the author", 'molongui-authorship' ) ), $profile ) );
            ?>
        </span>
        <?php if ( !empty( $options['author_box_header_url'] ) ) : ?></a><?php endif; ?>
    </<?php echo $header_html_tag; ?>>
</div>
