<?php

defined( 'ABSPATH' ) || exit;  
?>

<!-- Author Biography -->
<section id="molongui-bio-info" class="molongui-author-profile__section">

	<h2 class="molongui-author-profile__section-title">
		<?php esc_html_e( 'Biography', 'molongui-authorship' ); ?>
	</h2>

	<table class="form-table molongui-author-profile__table" role="presentation">
		<tbody>

		<tr class="molongui-author-profile__biography-row">

			<th scope="row">

				<label for="<?php echo esc_attr( $biography['full']['id'] ); ?>">
					<?php esc_html_e( 'Full biography', 'molongui-authorship' ); ?>
				</label>

				<p class="description molongui-author-profile__field-description">
					<?php echo esc_html( $biography['full']['description'] ); ?>
				</p>

			</th>

			<td>

				<?php if ( 'editor' === $biography['full']['control'] ) : ?>

					<?php
					wp_editor(
						$biography['full']['value'],
						$biography['full']['id'],
						$biography['full']['settings']
					);
					?>

				<?php else : ?>

					<textarea
							name="<?php echo esc_attr( $biography['full']['name'] ); ?>"
							id="<?php echo esc_attr( $biography['full']['id'] ); ?>"
							rows="<?php echo esc_attr( $biography['full']['rows'] ); ?>"
					><?php echo esc_textarea( $biography['full']['value'] ); ?></textarea>

				<?php endif; ?>

			</td>

		</tr>

		<?php if ( ! empty( $biography['short']['show'] ) ) : ?>

			<tr class="molongui-author-profile__biography-row">

				<th scope="row">

					<label for="<?php echo esc_attr( $biography['short']['id'] ); ?>">
						<?php esc_html_e( 'Short biography', 'molongui-authorship' ); ?>

						<?php if ( ! empty( $biography['short']['locked'] ) ) : ?>

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

					<p class="description molongui-author-profile__field-description">
						<?php echo esc_html( $biography['short']['description'] ); ?>
					</p>

				</th>

				<td>

					<?php if ( 'editor' === $biography['short']['control'] ) : ?>

						<?php
						wp_editor(
							$biography['short']['value'],
							$biography['short']['id'],
							$biography['short']['settings']
						);
						?>

					<?php else : ?>

						<textarea
							<?php if ( ! empty( $biography['short']['name'] ) ) : ?>
								name="<?php echo esc_attr( $biography['short']['name'] ); ?>"
							<?php endif; ?>
							id="<?php echo esc_attr( $biography['short']['id'] ); ?>"
							rows="<?php echo esc_attr( $biography['short']['rows'] ); ?>"
							<?php disabled( ! empty( $biography['short']['locked'] ) ); ?>
						><?php echo esc_textarea( $biography['short']['value'] ); ?></textarea>

					<?php endif; ?>

				</td>

			</tr>

		<?php endif; ?>

		</tbody>
	</table>

</section>
