<?php

use Molongui\Authorship\Author;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) || exit;  
?>

<div	id="molongui-user-fields"
		class="molongui-author-profile molongui-author-profile--user"
>

	<div class="molongui-author-profile__layout">

		<div class="molongui-author-profile__main">

			<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-public-name.php'; ?>

			<?php require $biography_template; ?>

			<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-professional-info.php'; ?>

			<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-contact-info.php'; ?>

			<?php if ( ! empty( $social_profiles['fields'] ) ) : ?>

				<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-social-profiles.php'; ?>

			<?php endif; ?>

			<?php
			/*!
			 * ACTION HOOK
			 * Allows third parties to add custom fields below the built-in Author Profile sections.
			 *
			 * @param int $user_id ID of the user being edited.
			 * @since 5.3.0
			 */
			do_action( 'authorship/edit_user/fields', $user->ID );

			/*!
			 * DEPRECATED ACTION HOOK
			 * Fires at the end of the main Author Profile content area.
			 *
			 * @param int $user_id ID of the user being edited.
			 * @since      4.0.0
			 * @deprecated 5.3.0 Use the {@see 'authorship/edit_user/fields'} action instead.
			 */
			do_action_deprecated(
				'authorship/edit/user/fields',
				array( $user->ID ),
				'5.3.0',
				'authorship/edit_user/fields'
			);
			?>

		</div> <!-- .molongui-author-profile__main -->

		<!-- Sidebar -->
		<div class="molongui-author-profile__sidebar">

			<?php if ( ! empty( $user->ID ) ) : ?>

				<?php
				$is_own_profile = defined( 'IS_PROFILE_PAGE' ) && IS_PROFILE_PAGE;

				$author = new Author( $user->ID, 'user' );

				$is_archived      = ! empty( $author_status['checked'] );
				$has_local_avatar = $author->has_local_avatar( true );

				$author_box_status = $author_box['status'];

				$registered_date = '';

				if ( ! empty( $user->user_registered ) && '0000-00-00 00:00:00' !== $user->user_registered
				) {
					$registered_date = get_date_from_gmt(
						$user->user_registered,
						get_option( 'date_format' )
					);
				}

				$delete_user_url = '';

				if ( ! is_multisite() && get_current_user_id() !== (int) $user->ID && current_user_can( 'delete_user', $user->ID ) ) {
					$delete_user_url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'delete',
								'user'   => (int) $user->ID,
							),
							admin_url( 'users.php' )
						),
						'bulk-users'
					);
				}
				?>

				<!-- Save -->
				<div id="molongui-profile-save" class="postbox molongui-postbox">

					<div class="postbox-header">
						<h2 class="hndle molongui-postbox-title">
							<span><?php esc_html_e( 'Save', 'molongui-authorship' ); ?></span>
						</h2>
					</div>

					<div class="inside">

						<div class="molongui-profile-save-info">

							<!-- User -->
							<div class="molongui-profile-save-row">

								<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>

								<div class="molongui-profile-save-row-content">

									<span class="molongui-profile-save-label">
										<?php esc_html_e( 'Editing:', 'molongui-authorship' ); ?>
									</span>

									<span>
										<?php
										if ( $is_own_profile ) {
											esc_html_e( 'Your profile', 'molongui-authorship' );
										} else {
											echo esc_html( $user->display_name );
										}
										?>
									</span>

								</div>

							</div>

							<!-- Status -->
							<div class="molongui-profile-save-row">

								<span class="dashicons <?php echo $is_archived ? 'dashicons-archive' : 'dashicons-yes-alt'; ?>" aria-hidden="true"></span>

								<div class="molongui-profile-save-row-content">

								<span class="molongui-profile-save-label">
									<?php esc_html_e( 'Status:', 'molongui-authorship' ); ?>
								</span>

									<span>
										<?php
										echo esc_html(
											$is_archived
												? __( 'Archived', 'molongui-authorship' )
												: __( 'Active', 'molongui-authorship' )
										);
										?>
									</span>

									<a class="molongui-profile-save-edit" href="#molongui-author-status">
										<?php esc_html_e( 'Edit', 'molongui-authorship' ); ?>
									</a>

								</div>

							</div>

							<!-- Avatar source -->
							<div class="molongui-profile-save-row">

								<span class="dashicons dashicons-format-image" aria-hidden="true"></span>

								<div class="molongui-profile-save-row-content">

									<span class="molongui-profile-save-label">
										<?php esc_html_e( 'Profile picture:', 'molongui-authorship' ); ?>
									</span>

									<span>
										<?php
										echo esc_html(
											$has_local_avatar
												? __( 'Custom picture', 'molongui-authorship' )
												: __( 'Gravatar', 'molongui-authorship' )
										);
										?>
									</span>

									<a class="molongui-profile-save-edit" href="#molongui-local-avatar">
										<?php esc_html_e( 'Edit', 'molongui-authorship' ); ?>
									</a>

								</div>

							</div>

							<!-- Author Box -->
							<div class="molongui-profile-save-row">

								<span class="dashicons dashicons-id-alt" aria-hidden="true"></span>

								<div class="molongui-profile-save-row-content">

									<span class="molongui-profile-save-label">
										<?php esc_html_e( 'Author box:', 'molongui-authorship' ); ?>
									</span>

									<span>
										<?php echo esc_html( $author_box_status ); ?>
									</span>

									<?php if ( ! empty( $author_box['enabled'] ) ) : ?>

										<a class="molongui-profile-save-edit" href="#molongui-box-settings">
											<?php esc_html_e( 'Edit', 'molongui-authorship' ); ?>
										</a>

									<?php endif; ?>

								</div>

							</div>

							<?php if ( $registered_date ) : ?>

								<!-- Registered -->
								<div class="molongui-profile-save-row">

									<span class="dashicons dashicons-calendar" aria-hidden="true"></span>

									<div class="molongui-profile-save-row-content">

										<span class="molongui-profile-save-label">
											<?php esc_html_e( 'Registered:', 'molongui-authorship' ); ?>
										</span>

										<span>
											<?php echo esc_html( $registered_date ); ?>
										</span>

									</div>

								</div>

							<?php endif; ?>

							<!-- Update info -->
							<div class="molongui-profile-save-row molongui-profile-save-row--hint">

								<span class="dashicons dashicons-update" aria-hidden="true"></span>

								<div class="molongui-profile-save-row-content">

									<span class="molongui-profile-save-label">
										<?php esc_html_e( 'Changes:', 'molongui-authorship' ); ?>
									</span>

									<span>
										<?php esc_html_e( 'Both tabs are saved together', 'molongui-authorship' ); ?>
									</span>

								</div>

							</div>

						</div>

						<div class="molongui-profile-save-actions">

							<?php if ( $delete_user_url ) : ?>

								<a class="molongui-profile-delete-user submitdelete deletion" href="<?php echo esc_url( $delete_user_url ); ?>">
									<?php esc_html_e( 'Delete User', 'molongui-authorship' ); ?>
								</a>

							<?php endif; ?>

							<div class="molongui-profile-save-target"></div>

						</div>

					</div>

				</div>

			<?php endif; ?>

			<?php require __DIR__ . '/sidebar/html-profile-picture.php'; ?>

			<?php if ( ! empty( $author_box['enabled'] ) ) : ?>

				<?php require __DIR__ . '/sidebar/html-author-box.php'; ?>

			<?php endif; ?>

			<?php require __DIR__ . '/sidebar/html-author-status.php'; ?>

			<?php
			if ( 0 !== (int) $user->ID ) {
				$default_conversion_template = __DIR__ . '/sidebar/html-author-conversion.php';

				$conversion_template = apply_filters_deprecated(
					'authorship/edit_user/conversion/tmpl',
					array( $default_conversion_template ),
					'5.3.0',
					'molongui_authorship/admin/author/conversion_template'
				);

				if ( ! is_string( $conversion_template ) || ! is_readable( $conversion_template ) ) {
					$conversion_template = $default_conversion_template;
				}

				require $conversion_template;
			}
			?>

			<?php
			/*!
			 * ACTION HOOK
			 * Allows third parties to add custom content to the end of the Author Profile sidebar.
			 *
			 * @param int   $user_id ID of the user being edited.
			 * @since 5.3.0
			 */
			do_action( 'authorship/edit_user/sidebar', $user->ID );
			?>

		</div> <!-- .molongui-author-profile__sidebar -->

	</div> <!-- .molongui-author-profile__layout -->

</div> <!-- #molongui-user-fields -->
