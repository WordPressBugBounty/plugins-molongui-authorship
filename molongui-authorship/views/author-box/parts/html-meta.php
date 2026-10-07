<?php

use Molongui\Authorship\Author_Meta_Fields;

defined( 'ABSPATH' ) || exit;  

if ( empty( $options['author_box_meta_show'] ) ) {
	return;
}

$visibility_args = array(
	'options' => $options,
);

if ( array_key_exists( 'meta_fields', $options ) ) {
	$visibility_args['fields'] = $options['meta_fields'];
}

/*!
 * [PRIVATE] FILTER HOOK
 * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
 * If you choose to use it, you do so at your own risk, as it may cause code issues.
 */
$visibility_args['apply_author_override'] = (bool) apply_filters(
	'_authorship/author_box/apply_author_meta_field_overrides',
	true,
	$profile,
	$options
);

$visible_fields = Author_Meta_Fields::get_visible_fields(
	$profile,
	Author_Meta_Fields::CONTEXT_AUTHOR_BOX,
	$visibility_args
);

$professional_headline = in_array( 'headline', $visible_fields, true )
	? trim( (string) Author_Meta_Fields::get_value( 'headline', $profile ) )
	: '';
$job = in_array( 'job', $visible_fields, true )
	? trim( (string) Author_Meta_Fields::get_value( 'job', $profile ) )
	: '';
$company = in_array( 'company', $visible_fields, true )
	? trim( (string) Author_Meta_Fields::get_value( 'company', $profile ) )
	: '';
$company_link = '' !== $company
	? trim( (string) $profile->get_meta( 'company_link' ) )
	: '';
$department = in_array( 'department', $visible_fields, true )
	? trim( (string) Author_Meta_Fields::get_value( 'department', $profile ) )
	: '';
$location = in_array( 'location', $visible_fields, true )
	? trim( (string) Author_Meta_Fields::get_value( 'location', $profile ) )
	: '';
$phone = in_array( 'phone', $visible_fields, true )
	? trim( (string) Author_Meta_Fields::get_value( 'phone', $profile ) )
	: '';
$email = in_array( 'email', $visible_fields, true )
	? trim( (string) Author_Meta_Fields::get_value( 'email', $profile ) )
	: '';
$website = in_array( 'website', $visible_fields, true )
	? trim( (string) Author_Meta_Fields::get_value( 'website', $profile ) )
	: '';

$nofollow      = ! empty( $options['social_profiles_nofollow'] ) ? 'rel="nofollow"' : '';
$nofollow_attr = '' !== $nofollow ? ' ' . $nofollow : '';
$external_rel  = ! empty( $options['social_profiles_nofollow'] ) ? 'nofollow noopener' : 'noopener';
$external_attr = ' rel="' . $external_rel . '"';
$divider       = isset( $options['author_box_meta_divider'] ) ? (string) $options['author_box_meta_divider'] : '|';
$separator     = sprintf(
	'&nbsp;<span class="m-a-box-meta-divider">%s</span>&nbsp;',
	esc_html( $divider )
);

$author_headline   = '';
$author_job        = '';
$author_company    = '';
$author_department = '';
$author_location   = '';
$author_phone      = '';
$author_email      = '';
$author_web        = '';
$author_more       = '';

if ( '' !== $professional_headline ) {
	$author_headline = sprintf(
		'<span class="m-a-box-professional-headline">%s</span>',
		esc_html( $professional_headline )
	);

	$author_headline = apply_filters(
		'authorship/box/meta/professional_headline',
		$author_headline,
		$professional_headline,
		$profile
	);
}

if ( '' !== $job ) {
	$author_job = sprintf(
		'<span%s>%s</span>',
		$add_microdata ? ' itemprop="jobTitle"' : '',
		esc_html( $job )
	);
}

if ( '' !== $company ) {
	$company_open  = '';
	$company_close = '';

	if ( '' !== $company_link ) {
		$company_open = sprintf(
			'<a href="%1$s" target="_blank"%2$s%3$s>',
			esc_url( $company_link ),
			$add_microdata ? ' itemprop="url"' : '',
			$external_attr
		);
		$company_close = '</a>';
	}

	$author_company = sprintf(
		'<span%s>%s<span%s>%s</span>%s</span>',
		$add_microdata ? ' itemprop="worksFor" itemscope itemtype="https://schema.org/Organization"' : '',
		$company_open,
		$add_microdata ? ' itemprop="name"' : '',
		esc_html( $company ),
		$company_close
	);
}

if ( '' !== $department ) {
	$author_department = sprintf(
		'<span class="m-a-box-department">%s</span>',
		esc_html( $department )
	);

	$author_department = apply_filters(
		'authorship/box/meta/department',
		$author_department,
		$department,
		$profile
	);
}

if ( '' !== $location ) {
	$author_location = sprintf(
		'<span class="m-a-box-location">%s</span>',
		esc_html( $location )
	);

	$author_location = apply_filters(
		'authorship/box/meta/location',
		$author_location,
		$location,
		$profile
	);
}

if ( '' !== $phone ) {
	$author_phone = sprintf(
		'<a href="tel:%1$s"%2$s%3$s>%4$s</a>',
		esc_attr( $phone ),
		$add_microdata ? ' itemprop="telephone"' : '',
		$nofollow_attr,
		esc_html( $phone )
	);

	$author_phone = apply_filters( 'authorship/box/meta/phone', $author_phone, $phone, $add_microdata, $nofollow );
}

if ( '' !== $email ) {
	$author_email = sprintf(
		'<a href="mailto:%1$s" target="_top"%2$s%3$s>%4$s</a>',
		esc_attr( $email ),
		$add_microdata ? ' itemprop="email"' : '',
		$nofollow_attr,
		esc_html( $email )
	);

	$author_email = apply_filters( 'authorship/box/meta/email', $author_email, $email, $add_microdata, $nofollow );
}

if ( '' !== $website ) {
	$website_label = ! empty( $options['author_box_meta_web'] )
		? $options['author_box_meta_web']
		: __( 'Website', 'molongui-authorship' );

	$website_label = apply_filters( 'authorship/box/meta/web', $website_label, $profile );

	$author_web = sprintf(
		'<a href="%1$s" target="_blank"%2$s><span class="m-a-box-string-web">%3$s</span></a>',
		esc_url( $website ),
		$external_attr,
		wp_kses_post( $website_label )
	);
}

if (
	'slim' === $options['author_box_layout']
	&& ! empty( $options['author_box_show_related_posts'] )
	&& ( $profile->has_posts() || ! empty( $options['author_box_related_show_empty'] ) )
) {
	$more_posts_label = ! empty( $options['author_box_meta_posts'] )
		? apply_filters( 'authorship/box/meta/more', $options['author_box_meta_posts'], $profile )
		: __( '+ posts', 'molongui-authorship' );

	$bio_label = ! empty( $options['author_box_meta_bio'] )
		? apply_filters( 'authorship/box/meta/bio', $options['author_box_meta_bio'], $profile )
		: __( 'Bio ⮌', 'molongui-authorship' );

	$author_more = sprintf(
		'<a href="#" class="m-a-box-data-toggle" rel="nofollow"><span class="m-a-box-string-more-posts">%1$s</span><span class="m-a-box-string-bio" style="display:none">%2$s</span></a>',
		esc_html( $more_posts_label ),
		esc_html( $bio_label )
	);
}

$meta_items        = array();
$author_employment = '';

if ( '' !== $author_job ) {
	$author_employment = $author_job;
}

if ( '' !== $author_company ) {
	if ( '' !== $author_employment ) {
		$at_label = ! empty( $options['author_box_meta_at'] )
			? $options['author_box_meta_at']
			: __( 'at', 'molongui-authorship' );

		$at_label = apply_filters( 'authorship/box/meta/at', $at_label, $profile );

		$author_employment .= sprintf(
			'&nbsp;<span class="m-a-box-string-at">%s</span>&nbsp;',
			wp_kses_post( $at_label )
		);
	}

	$author_employment .= $author_company;
}

if ( '' !== $author_employment ) {
	$meta_items['employment'] = $author_employment;
}

if ( '' !== $author_department ) {
	$meta_items['department'] = $author_department;
}

if ( '' !== $author_location ) {
	$meta_items['location'] = $author_location;
}

if ( '' !== $author_phone ) {
	$meta_items['phone'] = $author_phone;
}

if ( '' !== $author_email ) {
	$meta_items['email'] = $author_email;
}

if ( '' !== $author_web ) {
	$meta_items['web'] = $author_web;
}

if ( '' !== $author_more ) {
	$meta_items['more'] = $author_more;
}

$meta_details = '';

foreach ( $meta_items as $item => $markup ) {
	if ( '' !== $meta_details ) {
		$meta_details .= apply_filters( 'molongui_authorship/author_meta_separator', $separator, $item );
	}

	$meta_details .= $markup;
}

$meta = $author_headline;

if ( '' !== $author_headline && '' !== $meta_details ) {
	$meta .= '<br class="m-a-box-professional-headline-break">';
}

$meta .= $meta_details;

if ( '' === $meta ) {
	return;
}

?>

<div class="m-a-box-item m-a-box-meta">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $meta;
	?>
	<?php if ( '' !== $author_more && ! did_action( 'molongui_authorship/slim_content_toggle' ) ) : ?>
		<?php do_action( 'molongui_authorship/slim_content_toggle' ); ?>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function () {
				document.addEventListener('click', function (event) {
					var target = event.target.closest ? event.target.closest('.m-a-box-data-toggle') : null;

					if (!target) {
						return;
					}

					event.preventDefault();

					var authorBox = target.closest('.m-a-box');

					if (!authorBox) {
						return;
					}

					/*
					 * Scope the toggle to the current profile only when several authors share the same Author Box. Comparing the
					 * attribute value explicitly avoids treating data-multiauthor="false" as true.
					 */
					if ('true' === authorBox.getAttribute('data-multiauthor')) {
						authorBox = target.closest('[data-author-ref]');
					}

					if (!authorBox) {
						return;
					}

					var postLabel = target.querySelector('.m-a-box-string-more-posts');
					var bioLabel  = target.querySelector('.m-a-box-string-bio');
					var bio       = authorBox.querySelector('.m-a-box-bio');
					var related   = authorBox.querySelector('.m-a-box-related-entries');

					/*
					 * Bail safely if a custom layout or third-party filter removed any element required by the toggle.
					 */
					if (!postLabel || !bioLabel || !bio || !related) {
						return;
					}

					if ('none' === postLabel.style.display) {
						postLabel.style.display = 'inline';
						bioLabel.style.display  = 'none';
					} else {
						postLabel.style.display = 'none';
						bioLabel.style.display  = 'inline';
					}

					if ('none' === related.style.display) {
						related.style.display = 'block';
						bio.style.display     = 'none';
					} else {
						related.style.display = 'none';
						bio.style.display     = 'block';
					}
				});
			});
		</script>
	<?php endif; ?>
</div>
