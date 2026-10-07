<?php

defined( 'ABSPATH' ) || exit;  
?>

<!-- Author Social Profiles -->
<section id="molongui-social-profiles" class="molongui-author-profile__section">

	<h2 class="molongui-author-profile__section-title">
		<?php esc_html_e( 'Social Profiles', 'molongui-authorship' ); ?>
	</h2>

	<table class="form-table molongui-author-profile__table" role="presentation">
		<tbody>

		<tr>
			<th scope="row">

				<p class="description">
					<?php esc_html_e( 'Add the social profiles you want to display publicly for this author.', 'molongui-authorship' ); ?>
					<br>
					<?php
					printf(
						wp_kses(
						// translators: 1: Opening link tag. 2: Closing link tag.
							__( 'Manage available profiles in the %1$splugin settings%2$s.', 'molongui-authorship' ),
							array(
								'a' => array(
									'href' => array(),
								),
							)
						),
						'<a href="' . esc_url( $social_profiles['settings_url'] ) . '">',
						'</a>'
					);
					?>
				</p>

				<?php if ( ! empty( $social_profiles['has_inherited_profiles'] ) ) : ?>

					<details class="molongui-help molongui-help--field">

						<summary>
							<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Inherited profiles', 'molongui-authorship' ); ?></span>
						</summary>

						<div class="molongui-help-content">
							<p>
								<?php
								esc_html_e(
									'Compatible Contact Info values are used when these fields are empty. Values entered here take precedence.',
									'molongui-authorship'
								);
								?>
							</p>
						</div>

					</details>

				<?php endif; ?>

			</th>

			<td>

				<div id="m-social" class="molongui-author-profile__fields-grid molongui-author-profile__fields-grid--social">

					<?php foreach ( $social_profiles['fields'] as $field ) : ?>

						<div class="molongui-author-profile__field<?php echo ! empty( $field['locked'] ) ? ' m-premium' : ''; ?>">

							<label for="<?php echo esc_attr( $field['id'] ); ?>">
								<?php echo esc_html( $field['label'] ); ?>

								<?php if ( ! empty( $field['locked'] ) ) : ?>

									<a
										class="molongui-pro-badge"
										href="<?php echo esc_url( MOLONGUI_AUTHORSHIP_WEB ); ?>"
										target="_blank"
										rel="noopener noreferrer"
									>
										<?php esc_html_e( 'PRO', 'molongui-authorship' ); ?>
									</a>

								<?php endif; ?>
							</label>

							<div class="molongui-input-icon">

								<span class="molongui-input-icon__prefix" aria-hidden="true">
									<i class="<?php echo esc_attr( $field['icon_class'] ); ?>"></i>
								</span>

								<?php if ( ! empty( $field['locked'] ) ) : ?>

									<div class="m-tooltip">

										<input
											type="text"
											id="<?php echo esc_attr( $field['id'] ); ?>"
											class="text"
											disabled
											value=""
										>

										<span class="m-tooltip__text m-tooltip__top">
											<?php
											printf(
											// translators: %s: Social network name.
												esc_html__(
													'Upgrade to add a %s profile',
													'molongui-authorship'
												),
												esc_html( $field['label'] )
											);
											?>
										</span>

									</div>

								<?php else : ?>

									<div class="molongui-social-profile-field<?php echo ! empty( $field['fallback_value'] ) ? ' m-social-fallback-field' : ''; ?>">

										<input
											type="text"
											class="text"
											id="<?php echo esc_attr( $field['id'] ); ?>"
											name="<?php echo esc_attr( $field['name'] ); ?>"
											value="<?php echo esc_attr( $field['value'] ); ?>"
											aria-describedby="<?php echo esc_attr( $field['example_id'] ); ?>"
										>

										<?php if ( ! empty( $field['fallback_value'] ) ) : ?>

											<button
												type="button"
												class="m-social-fallback-info"
												aria-label="<?php esc_attr_e( 'About this inherited social profile', 'molongui-authorship' ); ?>"
												aria-describedby="<?php echo esc_attr( $field['fallback_id'] ); ?>"
											>
												<span aria-hidden="true">&#x2139;</span>
											</button>

											<span
												id="<?php echo esc_attr( $field['fallback_id'] ); ?>"
												class="m-social-fallback-tooltip"
												role="tooltip"
											>
												<?php
												printf(
												// translators: %s: Inherited social profile value.
													esc_html__(
														'Using the compatible value from Contact Info: %s. Enter a value here to override it for Molongui Authorship.',
														'molongui-authorship'
													),
													esc_html( $field['fallback_value'] )
												);
												?>
											</span>

										<?php endif; ?>

										<span
											id="<?php echo esc_attr( $field['example_id'] ); ?>"
											class="molongui-social-profile-example"
										>
											<?php
											printf(
											// translators: %s: Example social profile URL.
												esc_html__( 'Example: %s', 'molongui-authorship' ),
												esc_html( $field['example'] )
											);
											?>
										</span>

									</div>

								<?php endif; ?>

							</div>

						</div>

					<?php endforeach; ?>

				</div>

			</td>
		</tr>

		</tbody>
	</table>

</section>
