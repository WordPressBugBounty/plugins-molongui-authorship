<?php

defined( 'ABSPATH' ) || exit;  
?>

<?php if ( $profile_picture['can_manage_local_picture'] ) : ?>

	<div
		class="molongui-media-picker molongui-guest-profile-picture<?php echo $profile_picture['has_local_picture'] ? ' has-media' : ''; ?>"
		data-molongui-media-picker
		data-molongui-profile-picture
		data-molongui-media-picker-type="image"
		data-molongui-media-picker-preview-size="medium"
		data-molongui-media-picker-title="<?php esc_attr_e( 'Select Profile Picture', 'molongui-authorship' ); ?>"
		data-molongui-media-picker-button-text="<?php esc_attr_e( 'Use this picture', 'molongui-authorship' ); ?>"
		data-molongui-media-picker-upload-label="<?php esc_attr_e( 'Upload Picture', 'molongui-authorship' ); ?>"
		data-molongui-media-picker-change-label="<?php esc_attr_e( 'Change Picture', 'molongui-authorship' ); ?>"
		data-molongui-media-picker-fallback-url="<?php echo esc_url( $profile_picture['fallback_url'] ); ?>"
		data-molongui-profile-picture-local-source="<?php echo esc_attr( $profile_picture['local_source'] ); ?>"
		data-molongui-profile-picture-fallback-source="<?php echo esc_attr( $profile_picture['fallback_source'] ); ?>"
		data-molongui-profile-picture-browser-fallback="<?php echo esc_url( $profile_picture['fallback_browser_url'] ); ?>"
		data-molongui-profile-picture-browser-fallback-source="<?php echo esc_attr( $profile_picture['custom_default_source'] ); ?>"
	>

		<span class="molongui-media-picker__preview" data-molongui-avatar-container>

			<img
				class="molongui-media-picker__image"
				<?php if ( $profile_picture['preview_url'] ) : ?>
					src="<?php echo esc_url( $profile_picture['preview_url'] ); ?>"
				<?php else : ?>
					hidden
				<?php endif; ?>
				<?php if ( ! $profile_picture['has_local_picture'] && $profile_picture['browser_fallback'] ) : ?>
					data-molongui-avatar-fallback="<?php echo esc_url( $profile_picture['browser_fallback'] ); ?>"
					data-molongui-avatar-fallback-label="<?php echo esc_attr( $profile_picture['custom_default_source'] ); ?>"
				<?php endif; ?>
				alt=""
				data-molongui-media-picker-preview
			>

			<span
				class="molongui-profile-picture-source"
				data-molongui-avatar-source
				<?php echo $profile_picture['source'] ? '' : 'hidden'; ?>
			>
				<?php echo esc_html( $profile_picture['source'] ); ?>
			</span>

		</span>

		<input
			type="hidden"
			name="_thumbnail_id"
			id="_thumbnail_id"
			value="<?php echo esc_attr( $profile_picture['thumbnail_id'] ); ?>"
			data-molongui-media-picker-id
		>

		<input
			type="hidden"
			value="<?php echo esc_url( $profile_picture['local_edit_url'] ); ?>"
			data-molongui-media-picker-edit-url
		>

		<div class="molongui-media-picker__actions">

			<div class="molongui-media-picker__secondary-actions">

				<a
					class="molongui-media-picker__edit"
					<?php if ( $profile_picture['local_edit_url'] ) : ?>
						href="<?php echo esc_url( $profile_picture['local_edit_url'] ); ?>"
					<?php else : ?>
						hidden
					<?php endif; ?>
					target="_blank"
					rel="noopener noreferrer"
					data-molongui-media-picker-edit
				>
					<?php esc_html_e( 'Edit', 'molongui-authorship' ); ?>
				</a>

				<button
					type="button"
					class="button-link-delete molongui-media-picker__remove"
					<?php if ( ! $profile_picture['has_local_picture'] ) : ?>
						hidden
					<?php endif; ?>
					data-molongui-media-picker-remove
				>
					<?php esc_html_e( 'Remove', 'molongui-authorship' ); ?>
				</button>

			</div>

			<button
				type="button"
				class="button button-secondary molongui-media-picker__select"
				data-molongui-media-picker-select
			>
				<?php
				echo esc_html(
					$profile_picture['has_local_picture']
						? __( 'Change Picture', 'molongui-authorship' )
						: __( 'Upload Picture', 'molongui-authorship' )
				);
				?>
			</button>

		</div>

	</div>

<?php else : ?>

	<div class="molongui-media-picker molongui-media-picker--readonly molongui-guest-profile-picture">

		<span class="molongui-media-picker__preview" data-molongui-avatar-container>

			<img
				class="molongui-media-picker__image"
				<?php if ( $profile_picture['preview_url'] ) : ?>
					src="<?php echo esc_url( $profile_picture['preview_url'] ); ?>"
				<?php else : ?>
					hidden
				<?php endif; ?>
				<?php if ( $profile_picture['browser_fallback'] ) : ?>
					data-molongui-avatar-fallback="<?php echo esc_url( $profile_picture['browser_fallback'] ); ?>"
					data-molongui-avatar-fallback-label="<?php echo esc_attr( $profile_picture['custom_default_source'] ); ?>"
				<?php endif; ?>
				alt=""
			>

			<span
				class="molongui-profile-picture-source"
				data-molongui-avatar-source
				<?php echo $profile_picture['source'] ? '' : 'hidden'; ?>
			>
				<?php echo esc_html( $profile_picture['source'] ); ?>
			</span>

		</span>

		<?php if ( ! $profile_picture['preview_url'] ) : ?>
			<p class="description">
				<?php esc_html_e( 'No profile picture is currently available.', 'molongui-authorship' ); ?>
			</p>
		<?php endif; ?>

		<p class="description">
			<?php
			esc_html_e(
				'You do not have permission to upload a custom profile picture. Please contact the site administrator.',
				'molongui-authorship'
			);
			?>
		</p>

	</div>

<?php endif; ?>
