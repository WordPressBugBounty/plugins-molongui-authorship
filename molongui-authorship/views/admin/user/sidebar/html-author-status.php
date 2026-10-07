<?php

defined( 'ABSPATH' ) || exit;  
?>

<div id="molongui-author-status" class="postbox molongui-postbox">

	<div class="postbox-header">

		<h2 class="hndle molongui-postbox-title">
			<span><?php esc_html_e( 'Author Status', 'molongui-authorship' ); ?></span>
		</h2>

	</div>

	<div class="inside">
		<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/sidebar/html-status.php'; ?>
	</div>

</div>
