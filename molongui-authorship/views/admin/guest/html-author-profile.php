<?php

defined( 'ABSPATH' ) || exit;  
?>

<nav	id="molongui-guest-profile-tabs"
		class="nav-tab-wrapper molongui-author-profile-tabs"
		aria-label="<?php esc_attr_e( 'Guest Author profile sections', 'molongui-authorship' ); ?>"
>

	<button	type="button"
			id="molongui-guest-profile-tab-account"
			class="nav-tab"
			aria-disabled="true"
			aria-pressed="false"
			aria-describedby="molongui-guest-profile-tab-account-description"
			title="<?php esc_attr_e( 'Guest Authors do not have a WordPress user account.', 'molongui-authorship' ); ?>"
	>
		<?php esc_html_e( 'User Account', 'molongui-authorship' ); ?>
	</button>

	<button	type="button"
			id="molongui-guest-profile-tab-author"
			class="nav-tab nav-tab-active"
			aria-pressed="true"
	>
		<?php esc_html_e( 'Author Profile', 'molongui-authorship' ); ?>
	</button>

</nav>

<span	id="molongui-guest-profile-tab-account-description"
		class="screen-reader-text"
>
	<?php
	esc_html_e(
		'Guest Authors do not have a WordPress user account.',
		'molongui-authorship'
	);
	?>
</span>

<div
	id="molongui-guest-author-profile"
	class="molongui-author-profile molongui-author-profile--guest"
>

	<div class="molongui-author-profile__main">

		<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-public-name.php'; ?>

		<?php require $biography_template; ?>

		<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-professional-info.php'; ?>

		<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-contact-info.php'; ?>

		<?php if ( ! empty( $social_profiles['fields'] ) ) : ?>

			<?php require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-social-profiles.php'; ?>

		<?php endif; ?>

	</div>

</div>
