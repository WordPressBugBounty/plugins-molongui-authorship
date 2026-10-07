<?php

defined( 'ABSPATH' ) || exit;  
?>

<div id="molongui-user-conversion" class="postbox molongui-postbox">

	<div class="postbox-header">

		<h2 class="hndle molongui-postbox-title">
			<span><?php esc_html_e( 'Convert to Guest', 'molongui-authorship' ); ?></span>
		</h2>

	</div>

	<div class="inside">
		<?php
		$default_conversion_template = MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/sidebar/html-conversion.php';

		$conversion_template = apply_filters(
			'molongui_authorship/admin/author/conversion_template',
			$default_conversion_template,
			'user',
			(int) $user->ID,
			$author_conversion
		);

		if ( ! is_string( $conversion_template ) || ! is_readable( $conversion_template ) ) {
			$conversion_template = $default_conversion_template;
		}

		require $conversion_template;
		?>
	</div>

</div>
