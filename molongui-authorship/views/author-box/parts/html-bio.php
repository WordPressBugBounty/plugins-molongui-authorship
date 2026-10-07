<?php

defined( 'ABSPATH' ) || exit;  

if ( isset( $options['author_box_bio_source'] ) && 'none' === $options['author_box_bio_source'] ) {
	return;
}

$bio = apply_filters( 'authorship/box/bio', $profile->get_description(), $profile, $options );

$bio = str_replace( array( '<br>', '<br/>', '<br />' ), '', $bio );
$bio = wpautop( $bio );
$bio = str_replace( array( "\n\r", "\r\n", "\n\n", "\r\r" ), '<br>', $bio );

?>

<div class="m-a-box-bio" <?php echo ( $add_microdata ? 'itemprop="description"' : '' ); ?>>
	<?php
	echo $bio; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bio HTML is intentionally supported and filtered upstream.

	if ( ! empty( $options['extra_content'] ) ) {
		echo $options['extra_content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode-provided HTML is intentionally supported.
	}
	?>
</div>
