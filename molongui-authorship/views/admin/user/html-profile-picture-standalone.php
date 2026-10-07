<?php

defined( 'ABSPATH' ) || exit;  

$profile_picture_show_preview = false;
?>

<!--
	This fallback section is moved next to the native WordPress Profile Picture row by edit-user.js when JavaScript is
	available. Keeping it at the standard plugin hook location provides progressive enhancement when JavaScript is not.
-->
<div id="molongui-local-avatar-standalone">

	<h3>
		<?php esc_html_e( 'Local Profile Picture', 'molongui-authorship' ); ?>
	</h3>

	<table class="form-table" role="presentation">
		<tbody>

		<tr id="molongui-local-avatar-row" class="user-m-avatar-wrap">

			<th>
				<label for="molongui_author_image_id">
					<?php esc_html_e( 'Local picture', 'molongui-authorship' ); ?>
				</label>
			</th>

			<td>

				<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/user/html-profile-picture-picker.php'; ?>

				<p class="description">
					<?php
					esc_html_e(
						'Upload a local picture to override the other enabled avatar sources for this author.',
						'molongui-authorship'
					);
					?>
				</p>

			</td>

		</tr>

		</tbody>
	</table>

</div>
