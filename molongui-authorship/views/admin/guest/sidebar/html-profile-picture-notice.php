<?php

defined( 'ABSPATH' ) || exit;  
?>

<div class="molongui-field">

	<?php if ( ! empty( $profile_picture_notice['title'] ) ) : ?>

		<p>
			<strong>
				<?php echo esc_html( $profile_picture_notice['title'] ); ?>
			</strong>
		</p>

	<?php endif; ?>

	<?php if ( ! empty( $profile_picture_notice['description'] ) ) : ?>

		<p class="description">
			<?php echo esc_html( $profile_picture_notice['description'] ); ?>
		</p>

	<?php endif; ?>

	<?php if ( ! empty( $profile_picture_notice['action_url'] ) && ! empty( $profile_picture_notice['action_label'] ) ) : ?>

		<p>
			<a	class="button"
				href="<?php echo esc_url( $profile_picture_notice['action_url'] ); ?>"
			>
				<?php echo esc_html( $profile_picture_notice['action_label'] ); ?>
			</a>
		</p>

	<?php endif; ?>

</div>
