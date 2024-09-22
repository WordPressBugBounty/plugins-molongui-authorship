<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$label = apply_filters( 'authorship/box/profile/title', $options['author_box_profile_title'], $author );
if ( empty( $label ) )
{
    return;
}
?>

<div class="m-a-box-profile-title">
    <?php echo esc_html( $label ); ?>
</div>