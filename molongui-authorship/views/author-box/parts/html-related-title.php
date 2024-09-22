<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$label = apply_filters( 'authorship/box/related/title', $options['author_box_related_title'], $author );
if ( empty( $label ) )
{
    return;
}
?>

<div class="m-a-box-related-title">
    <?php echo esc_html( $label ); ?>
</div>