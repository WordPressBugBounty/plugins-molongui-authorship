<?php

use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) || exit;  
?>

<div id="molongui-local-avatar" class="postbox molongui-postbox">

	<div class="postbox-header">

		<h2 class="hndle molongui-postbox-title">
			<span><?php esc_html_e( 'Profile Picture', 'molongui-authorship' ); ?></span>
		</h2>

	</div>

	<div class="inside">

		<details class="molongui-help">

			<summary>
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				<span><?php esc_html_e( 'How is the profile picture chosen?', 'molongui-authorship' ); ?></span>
			</summary>

			<div class="molongui-help-content">

				<p>
					<?php
					esc_html_e(
						'The picture shown here follows the global Author Avatar Settings. Local pictures take priority when enabled, followed by Gravatar and the configured default avatar.',
						'molongui-authorship'
					);
					?>
				</p>

				<?php if ( Settings::is_enabled( 'local-avatar' ) ) : ?>
					<p>
						<?php
						esc_html_e(
							'Use the controls below to upload or replace the local profile picture for this author.',
							'molongui-authorship'
						);
						?>
					</p>
				<?php endif; ?>

			</div>

		</details>

		<div class="molongui-author-profile__profile-picture">

			<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/user/html-profile-picture-picker.php'; ?>

		</div>

	</div>

</div>
