<?php

defined( 'ABSPATH' ) || exit;  
?>

<!-- Author Contact Info -->
<section id="molongui-contact-info" class="molongui-author-profile__section">

	<h2 class="molongui-author-profile__section-title">
		<?php esc_html_e( 'Contact Info', 'molongui-authorship' ); ?>
	</h2>

	<table class="form-table molongui-author-profile__table" role="presentation">
		<tbody>

		<?php if ( ! empty( $contact_info['fields'] ) && is_array( $contact_info['fields'] ) ) : ?>

			<?php foreach ( $contact_info['fields'] as $field ) : ?>

				<tr>
					<th scope="row">

						<?php if ( 'output' === $field['control'] ) : ?>

							<?php echo esc_html( $field['label'] ); ?>

						<?php else : ?>

							<label for="<?php echo esc_attr( $field['id'] ); ?>">
								<?php echo esc_html( $field['label'] ); ?>
							</label>

						<?php endif; ?>

					</th>

					<td>

						<?php if ( 'output' === $field['control'] ) : ?>

							<span	id="<?php echo esc_attr( $field['id'] ); ?>"
									 class="molongui-author-profile__output"
							>
								<?php echo esc_html( $field['value'] ); ?>
							</span>

						<?php else : ?>

							<input	type="<?php echo esc_attr( $field['type'] ); ?>"
									name="<?php echo esc_attr( $field['name'] ); ?>"
									id="<?php echo esc_attr( $field['id'] ); ?>"
									value="<?php echo esc_attr( $field['value'] ); ?>"
									class="regular-text"
							>

						<?php endif; ?>

						<?php if ( ! empty( $field['description'] ) ) : ?>

							<p class="description">
								<?php echo esc_html( $field['description'] ); ?>

								<?php if ( ! empty( $field['action'] ) ) : ?>

									<a	href="<?php echo esc_attr( $field['action']['href'] ); ?>"
										class="<?php echo esc_attr( $field['action']['class'] ); ?>"
										data-target="<?php echo esc_attr( $field['action']['target'] ); ?>"
									>
										<?php echo esc_html( $field['action']['label'] ); ?>
									</a>

								<?php endif; ?>
							</p>

						<?php endif; ?>

					</td>
				</tr>

			<?php endforeach; ?>

		<?php endif; ?>

		</tbody>
	</table>

</section>
