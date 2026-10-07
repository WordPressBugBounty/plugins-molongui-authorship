<?php

defined( 'ABSPATH' ) || exit;  

$display_value = isset( $author_box['display']['value'] )
	? $author_box['display']['value']
	: 'default';

$display_locked = ! empty( $author_box['display']['locked'] );
?>

<details class="molongui-help">

	<summary>
		<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
		<span><?php esc_html_e( 'How do these settings work?', 'molongui-authorship' ); ?></span>
	</summary>

	<div class="molongui-help-content">
		<p>
			<?php
			printf(
				wp_kses(
				// translators: 1: Opening link tag. 2: Closing link tag.
					__(
						'The %1$splugin settings%2$s define how the author box is displayed by default. The settings below let you override some of those options for this author.',
						'molongui-authorship'
					),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				),
				'<a href="' . esc_url( $author_box['settings_url'] ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);
			?>
		</p>
	</div>

</details>

<div class="molongui-author-profile__fields-stack" data-molongui-author-box-settings>

	<div class="molongui-author-profile__field">

		<label
				for="<?php echo esc_attr( $author_box['display']['id'] ); ?>"
				class="molongui-author-profile__field-label"
		>
			<?php esc_html_e( 'Visibility', 'molongui-authorship' ); ?>

			<?php if ( $display_locked ) : ?>

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

		<?php
		ob_start();
		?>

		<select
				name="<?php echo esc_attr( $author_box['display']['name'] ); ?>"
				id="<?php echo esc_attr( $author_box['display']['id'] ); ?>"
				data-molongui-author-box-display
		>
			<option
					value="default"
				<?php selected( $display_value, 'default' ); ?>
			>
				<?php esc_html_e( 'Default', 'molongui-authorship' ); ?>
			</option>

			<option
					value="show"
				<?php selected( $display_value, 'show' ); ?>
				<?php disabled( $display_locked ); ?>
			>
				<?php esc_html_e( 'Show', 'molongui-authorship' ); ?>
			</option>

			<option
					value="hide"
				<?php selected( $display_value, 'hide' ); ?>
				<?php disabled( $display_locked ); ?>
			>
				<?php esc_html_e( 'Hide', 'molongui-authorship' ); ?>
			</option>
		</select>

		<?php
		$display_control = ob_get_clean();

		if ( ! empty( $author_box['display']['filter'] ) ) {
			$display_control = apply_filters(
				$author_box['display']['filter'],
				$display_control
			);
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The markup is generated above and may be modified by a compatibility filter.
		echo $display_control;
		?>

	</div>

	<div class="molongui-author-profile__field">

		<label for="<?php echo esc_attr( $author_box['custom_link']['id'] ); ?>">
			<?php esc_html_e( 'Custom link', 'molongui-authorship' ); ?>
		</label>

		<input
				type="url"
				name="<?php echo esc_attr( $author_box['custom_link']['name'] ); ?>"
				id="<?php echo esc_attr( $author_box['custom_link']['id'] ); ?>"
				value="<?php echo esc_attr( $author_box['custom_link']['value'] ); ?>"
		>

		<p class="description">
			<?php
			esc_html_e(
				'URL to make the author name and/or avatar link to.',
				'molongui-authorship'
			);
			?>
		</p>

		<?php if ( empty( $author_box['custom_link']['active'] ) ) : ?>

			<p class="description">
				<?php
				esc_html_e(
					'Enable the custom link option in the Author Box Editor to use this field.',
					'molongui-authorship'
				);
				?>
			</p>

		<?php endif; ?>

	</div>

	<fieldset class="molongui-settings-group">

		<legend>
			<?php esc_html_e( 'Author details', 'molongui-authorship' ); ?>
		</legend>

		<p class="description">
			<?php
			esc_html_e(
				'Choose whether each Author Box meta field inherits the global setting, is always shown, or is always hidden for this author.',
				'molongui-authorship'
			);
			?>
		</p>

		<div class="molongui-author-meta-visibility" role="group" aria-label="<?php esc_attr_e( 'Author Box meta field visibility', 'molongui-authorship' ); ?>">

			<div class="molongui-author-meta-visibility__header" aria-hidden="true">
				<span class="molongui-author-meta-visibility__field-label"><?php esc_html_e( 'Field', 'molongui-authorship' ); ?></span>
				<span><?php esc_html_e( 'Default', 'molongui-authorship' ); ?></span>
				<span><?php esc_html_e( 'Show', 'molongui-authorship' ); ?></span>
				<span><?php esc_html_e( 'Hide', 'molongui-authorship' ); ?></span>
			</div>

			<?php
			$visibility_labels = array(
				'default' => __( 'Default', 'molongui-authorship' ),
				'show'    => __( 'Show', 'molongui-authorship' ),
				'hide'    => __( 'Hide', 'molongui-authorship' ),
			);
			?>

			<?php foreach ( $author_box['meta_visibility']['fields'] as $field_id => $field ) : ?>
				<?php
				$field_name  = $author_box['meta_visibility']['name'] . '[' . $field_id . ']';
				$field_value = isset( $field['value'] ) ? $field['value'] : 'default';
				?>

				<div class="molongui-author-meta-visibility__row">
					<span class="molongui-author-meta-visibility__field-label">
						<?php echo esc_html( $field['label'] ); ?>
					</span>

					<?php foreach ( array( 'default', 'show', 'hide' ) as $visibility ) : ?>
						<?php $control_id = sanitize_html_class( $author_box['meta_visibility']['name'] . '-' . $field_id . '-' . $visibility ); ?>
						<label
								for="<?php echo esc_attr( $control_id ); ?>"
								class="molongui-author-meta-visibility__choice"
								title="<?php echo esc_attr( $visibility_labels[ $visibility ] ); ?>"
						>
							<span class="screen-reader-text">
								<?php
								printf(
									esc_html__( '%1$s: %2$s', 'molongui-authorship' ),
									esc_html( $field['label'] ),
									esc_html( $visibility_labels[ $visibility ] )
								);
								?>
							</span>
							<input
									type="radio"
									name="<?php echo esc_attr( $field_name ); ?>"
									id="<?php echo esc_attr( $control_id ); ?>"
									value="<?php echo esc_attr( $visibility ); ?>"
								<?php checked( $field_value, $visibility ); ?>
								<?php disabled( 'hide' === $display_value ); ?>
									data-molongui-author-box-dependent
							>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>

		</div>

	</fieldset>

	<fieldset class="molongui-settings-group">

		<legend>
			<?php esc_html_e( 'Social icons', 'molongui-authorship' ); ?>
		</legend>

		<?php
		$icon_fields = array(
			'website' => array(
				'title'       => __( 'Website', 'molongui-authorship' ),
				'description' => __( 'Show website icon with social icons.', 'molongui-authorship' ),
			),
			'email' => array(
				'title'       => __( 'E-mail', 'molongui-authorship' ),
				'description' => __( 'Show e-mail icon with social icons.', 'molongui-authorship' ),
			),
			'phone' => array(
				'title'       => __( 'Phone', 'molongui-authorship' ),
				'description' => __( 'Show phone icon with social icons.', 'molongui-authorship' ),
			),
		);
		?>

		<?php foreach ( $icon_fields as $key => $presentation ) : ?>

			<?php $field = $author_box['icons'][ $key ]; ?>

			<label
					class="molongui-checkbox-option"
					for="<?php echo esc_attr( $field['id'] ); ?>"
			>

				<input
						type="checkbox"
						name="<?php echo esc_attr( $field['name'] ); ?>"
						id="<?php echo esc_attr( $field['id'] ); ?>"
						value="1"
					<?php checked( ! empty( $field['checked'] ) ); ?>
					<?php disabled( 'hide' === $display_value ); ?>
						data-molongui-author-box-dependent
				>

				<span class="molongui-checkbox-option__content">

					<span class="molongui-checkbox-option__title">
						<?php echo esc_html( $presentation['title'] ); ?>
					</span>

					<span class="description">
						<?php echo esc_html( $presentation['description'] ); ?>
					</span>

				</span>

			</label>

		<?php endforeach; ?>

	</fieldset>

</div>
