<?php

defined( 'ABSPATH' ) || exit;  
?>

<!-- Author Professional Info -->
<section id="molongui-pro-info" class="molongui-author-profile__section">

	<h2 class="molongui-author-profile__section-title">
		<?php esc_html_e( 'Professional Info', 'molongui-authorship' ); ?>
	</h2>

	<table class="form-table molongui-author-profile__table" role="presentation">
		<tbody>

		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $professional_info['professional_headline']['id'] ); ?>">
					<?php esc_html_e( 'Professional headline', 'molongui-authorship' ); ?>
				</label>
			</th>

			<td>
				<input
					type="text"
					name="<?php echo esc_attr( $professional_info['professional_headline']['name'] ); ?>"
					id="<?php echo esc_attr( $professional_info['professional_headline']['id'] ); ?>"
					value="<?php echo esc_attr( $professional_info['professional_headline']['value'] ); ?>"
					class="regular-text"
				>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $professional_info['job']['id'] ); ?>">
					<?php esc_html_e( 'Job title', 'molongui-authorship' ); ?>
				</label>
			</th>

			<td>
				<input
					type="text"
					name="<?php echo esc_attr( $professional_info['job']['name'] ); ?>"
					id="<?php echo esc_attr( $professional_info['job']['id'] ); ?>"
					value="<?php echo esc_attr( $professional_info['job']['value'] ); ?>"
					class="regular-text"
				>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $professional_info['company']['id'] ); ?>">
					<?php esc_html_e( 'Company', 'molongui-authorship' ); ?>
				</label>
			</th>

			<td>
				<input
					type="text"
					name="<?php echo esc_attr( $professional_info['company']['name'] ); ?>"
					id="<?php echo esc_attr( $professional_info['company']['id'] ); ?>"
					value="<?php echo esc_attr( $professional_info['company']['value'] ); ?>"
					class="regular-text"
				>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $professional_info['department']['id'] ); ?>">
					<?php esc_html_e( 'Department', 'molongui-authorship' ); ?>
				</label>
			</th>

			<td>
				<input
					type="text"
					name="<?php echo esc_attr( $professional_info['department']['name'] ); ?>"
					id="<?php echo esc_attr( $professional_info['department']['id'] ); ?>"
					value="<?php echo esc_attr( $professional_info['department']['value'] ); ?>"
					class="regular-text"
				>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $professional_info['company_link']['id'] ); ?>">
					<?php esc_html_e( 'Company website', 'molongui-authorship' ); ?>
				</label>
			</th>

			<td>
				<input
					type="url"
					name="<?php echo esc_attr( $professional_info['company_link']['name'] ); ?>"
					id="<?php echo esc_attr( $professional_info['company_link']['id'] ); ?>"
					value="<?php echo esc_attr( $professional_info['company_link']['value'] ); ?>"
					class="regular-text"
				>
			</td>
		</tr>

		</tbody>
	</table>

</section>
