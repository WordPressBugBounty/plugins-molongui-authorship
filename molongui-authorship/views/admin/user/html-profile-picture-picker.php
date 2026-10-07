<?php

use Molongui\Authorship\Author;
use Molongui\Authorship\Avatar;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) || exit;  

$user_id = ! empty( $user->ID ) ? absint( $user->ID ) : 0;

$show_preview = ! isset( $profile_picture_show_preview ) || (bool) $profile_picture_show_preview;

$local_avatar_enabled = Settings::is_enabled( 'local-avatar' );

$img_id  = absint( get_the_author_meta( 'molongui_author_image_id', $user_id ) );
$img_url = get_the_author_meta( 'molongui_author_image_url', $user_id );

if ( $img_id ) {
	$attachment_url = wp_get_attachment_url( $img_id );

	if ( $attachment_url ) {
		$img_url = $attachment_url;
	}
}

$has_local_picture = ! empty( $img_id ) && ! empty( $img_url );

$img_edit_url = $img_id
	? get_edit_post_link( $img_id, 'raw' )
	: '';

$author = $user_id
	? new Author( $user_id, 'user' )
	: null;

$avatar = $author
	? new Avatar( $author )
	: null;

$effective_resolution = $avatar
	? $avatar->get_resolution( array( 256, 256 ), 'url', 'auto' )
	: array(
		'value'            => '',
		'source'           => 'none',
		'browser_fallback' => '',
	);

$fallback_resolution = $avatar
	? $avatar->get_resolution( array( 256, 256 ), 'url', 'gravatar' )
	: array(
		'value'            => '',
		'source'           => 'none',
		'browser_fallback' => '',
	);

$source_labels = array(
	'local'          => __( 'Local', 'molongui-authorship' ),
	'gravatar'       => __( 'Gravatar', 'molongui-authorship' ),
	'custom-default' => __( 'Default avatar', 'molongui-authorship' ),
	'none'           => '',
);

$source_label = isset( $source_labels[ $effective_resolution['source'] ] )
	? $source_labels[ $effective_resolution['source'] ]
	: '';

$fallback_source_label = isset( $source_labels[ $fallback_resolution['source'] ] )
	? $source_labels[ $fallback_resolution['source'] ]
	: '';

$preview_url = ! empty( $effective_resolution['value'] )
	? $effective_resolution['value']
	: '';

if ( 'local' === $effective_resolution['source'] && $img_id ) {
	$attachment_preview_url = wp_get_attachment_image_url( $img_id, 'medium' );

	if ( $attachment_preview_url ) {
		$preview_url = $attachment_preview_url;
	}
}

$can_manage_local_picture = $local_avatar_enabled && current_user_can( 'upload_files' );
?>

<?php if ( $can_manage_local_picture ) : ?>

	<div
		class="molongui-media-picker<?php echo $has_local_picture ? ' has-media' : ''; ?>"
		data-molongui-media-picker
		data-molongui-profile-picture
		data-molongui-media-picker-type="image"
		data-molongui-media-picker-preview-size="medium"
		data-molongui-media-picker-title="<?php esc_attr_e( 'Select Profile Picture', 'molongui-authorship' ); ?>"
		data-molongui-media-picker-button-text="<?php esc_attr_e( 'Use this picture', 'molongui-authorship' ); ?>"
		data-molongui-media-picker-upload-label="<?php esc_attr_e( 'Upload Picture', 'molongui-authorship' ); ?>"
		data-molongui-media-picker-change-label="<?php esc_attr_e( 'Change Picture', 'molongui-authorship' ); ?>"
		data-molongui-media-picker-fallback-url="<?php echo esc_url( $fallback_resolution['value'] ); ?>"
		data-molongui-profile-picture-local-source="<?php echo esc_attr( $source_labels['local'] ); ?>"
		data-molongui-profile-picture-fallback-source="<?php echo esc_attr( $fallback_source_label ); ?>"
		data-molongui-profile-picture-browser-fallback="<?php echo esc_url( $fallback_resolution['browser_fallback'] ); ?>"
		data-molongui-profile-picture-browser-fallback-source="<?php echo esc_attr( $source_labels['custom-default'] ); ?>"
	>

		<?php if ( $show_preview ) : ?>

			<span class="molongui-media-picker__preview" data-molongui-avatar-container>

				<img
					class="molongui-media-picker__image"
					<?php if ( $preview_url ) : ?>
						src="<?php echo esc_url( $preview_url ); ?>"
					<?php else : ?>
						hidden
					<?php endif; ?>
					<?php if ( ! $has_local_picture && ! empty( $effective_resolution['browser_fallback'] ) ) : ?>
						data-molongui-avatar-fallback="<?php echo esc_url( $effective_resolution['browser_fallback'] ); ?>"
						data-molongui-avatar-fallback-label="<?php echo esc_attr( $source_labels['custom-default'] ); ?>"
					<?php endif; ?>
					alt=""
					data-molongui-media-picker-preview
				>

				<span
					class="molongui-profile-picture-source"
					data-molongui-avatar-source
					<?php echo $source_label ? '' : 'hidden'; ?>
				>
					<?php echo esc_html( $source_label ); ?>
				</span>

			</span>

		<?php endif; ?>

		<input
			type="hidden"
			name="molongui_author_image_id"
			id="molongui_author_image_id"
			value="<?php echo esc_attr( $img_id ); ?>"
			data-molongui-media-picker-id
		>

		<input
			type="hidden"
			name="molongui_author_image_url"
			id="molongui_author_image_url"
			value="<?php echo esc_url( $img_url ); ?>"
			data-molongui-media-picker-url
		>

		<input
			type="hidden"
			name="molongui_author_image_edit"
			id="molongui_author_image_edit"
			value="<?php echo esc_url( $img_edit_url ); ?>"
			data-molongui-media-picker-edit-url
		>

		<div class="molongui-media-picker__actions">

			<div class="molongui-media-picker__secondary-actions">

				<a
					class="molongui-media-picker__edit"
					<?php if ( $img_edit_url ) : ?>
						href="<?php echo esc_url( $img_edit_url ); ?>"
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
					<?php if ( ! $has_local_picture ) : ?>
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
					$has_local_picture
						? __( 'Change Picture', 'molongui-authorship' )
						: __( 'Upload Picture', 'molongui-authorship' )
				);
				?>
			</button>

		</div>

	</div>

<?php else : ?>

	<?php if ( $show_preview ) : ?>

		<div class="molongui-media-picker molongui-media-picker--readonly">

			<span class="molongui-media-picker__preview" data-molongui-avatar-container>

				<img
					class="molongui-media-picker__image"
					<?php if ( $preview_url ) : ?>
						src="<?php echo esc_url( $preview_url ); ?>"
					<?php else : ?>
						hidden
					<?php endif; ?>
					<?php if ( ! empty( $effective_resolution['browser_fallback'] ) ) : ?>
						data-molongui-avatar-fallback="<?php echo esc_url( $effective_resolution['browser_fallback'] ); ?>"
						data-molongui-avatar-fallback-label="<?php echo esc_attr( $source_labels['custom-default'] ); ?>"
					<?php endif; ?>
					alt=""
				>

				<span
					class="molongui-profile-picture-source"
					data-molongui-avatar-source
					<?php echo $source_label ? '' : 'hidden'; ?>
				>
					<?php echo esc_html( $source_label ); ?>
				</span>

			</span>

			<?php if ( ! $preview_url ) : ?>
				<p class="description">
					<?php esc_html_e( 'No profile picture is currently available.', 'molongui-authorship' ); ?>
				</p>
			<?php endif; ?>

		</div>

	<?php endif; ?>

	<?php if ( $local_avatar_enabled && ! current_user_can( 'upload_files' ) ) : ?>
		<p class="description">
			<?php
			esc_html_e(
				'You do not have permission to upload a custom profile picture. Please contact the site administrator.',
				'molongui-authorship'
			);
			?>
		</p>
	<?php endif; ?>

<?php endif; ?>
