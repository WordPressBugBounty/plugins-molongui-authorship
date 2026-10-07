<?php

defined( 'ABSPATH' ) || exit;  
?>

<label
	class="molongui-checkbox-option"
	for="<?php echo esc_attr( $author_status['id'] ); ?>"
>

	<input
		type="checkbox"
		name="<?php echo esc_attr( $author_status['name'] ); ?>"
		id="<?php echo esc_attr( $author_status['id'] ); ?>"
		value="1"
		<?php checked( ! empty( $author_status['checked'] ) ); ?>
	>

	<span class="molongui-checkbox-option__content">

		<span class="molongui-checkbox-option__title">
			<?php esc_html_e( 'Archive author', 'molongui-authorship' ); ?>
		</span>

		<span class="description">
			<?php
			esc_html_e(
				'Remove this author from author selectors without deleting the author.',
				'molongui-authorship'
			);
			?>
		</span>

	</span>

</label>
