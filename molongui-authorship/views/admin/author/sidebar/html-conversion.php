<?php

defined( 'ABSPATH' ) || exit;  
?>

<p class="description">
	<?php echo esc_html( $author_conversion['description'] ); ?>
</p>

<div class="molongui-author-tool-actions">

	<button type="button" class="button" disabled>
		<?php echo esc_html( $author_conversion['button_label'] ); ?>
	</button>

	<p class="molongui-pro-feature-notice">
		<?php esc_html_e( 'Available in Pro.', 'molongui-authorship' ); ?>

		<a	href="<?php echo esc_url( MOLONGUI_AUTHORSHIP_WEB ); ?>"
			target="_blank"
			rel="noopener noreferrer"
		>
			<?php esc_html_e( 'Upgrade', 'molongui-authorship' ); ?>
		</a>
	</p>

</div>
