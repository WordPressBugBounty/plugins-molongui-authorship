<?php

defined( 'ABSPATH' ) || exit;  
?>

<div id="molongui-box-settings" class="postbox molongui-postbox">

	<div class="postbox-header">

		<h2 class="hndle molongui-postbox-title">
			<span><?php esc_html_e( 'Author Box', 'molongui-authorship' ); ?></span>
		</h2>

	</div>

	<div class="inside">
		<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/sidebar/html-author-box.php'; ?>
	</div>

</div>
