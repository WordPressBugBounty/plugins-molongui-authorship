<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( empty( $options['author_box_meta_show'] ) )
{
    return;
}

$meta      = '';
$nofollow  = ( $options['add_nofollow'] ? 'rel="nofollow"' : '' );
$separator = sprintf( '&nbsp;%s&nbsp;', '<span class="m-a-box-meta-divider">'.$options['author_box_meta_divider'].'</span>' );

if ( !empty( $author['job'] ) )
{
    $author_job = '<span ' . ( $add_microdata ? 'itemprop="jobTitle"' : '' ) . '>' . esc_html( $author['job'] ) . '</span>';
}
if ( !empty( $author['company'] ) )
{
    $author_company = sprintf(
        '%s%s%s%s%s'
        , '<span ' . ( $add_microdata ? 'itemprop="worksFor" itemscope itemtype="https://schema.org/Organization"' : '' ) . '>'
        , $author['company_link'] ? '<a href="' . esc_url( $author['company_link'] ) . '" target="_blank" '.( $add_microdata ? 'itemprop="url"' : '' ). $nofollow . '>' : ''
        , '<span ' . ( $add_microdata ? 'itemprop="name"' : '' ) . '>' . esc_html( $author['company'] ) . '</span>'
        , $author['company_link'] ? '</a>' : ''
        , '</span>'
    );
}
if ( !empty( $author['phone'] ) )
{
    $author_phone = '<a href="tel:'.esc_attr( $author['phone'] ).'"'. ( $add_microdata ? ' itemprop="telephone"' : '' ) . ' content="'.esc_attr( $author['phone'] ).'" '.$nofollow.'>' . esc_html( $author['phone'] ) . '</a>';
    $author_phone = apply_filters( 'authorship/box/meta/phone', $author_phone, $author['phone'], $add_microdata, $nofollow );
}
if ( !empty( $author['mail'] ) )
{
    $author_email = '<a href="mailto:'.esc_attr( $author['mail'] ).'" target="_top"'. ( $add_microdata ? ' itemprop="email"' : '' ) . ' content="'.esc_attr( $author['mail'] ).'" '.$nofollow.'>' . esc_html( $author['mail'] ) . '</a>';
    $author_email = apply_filters( 'authorship/box/meta/email', $author_email, $author['mail'], $add_microdata, $nofollow );

}
if ( !empty( $author['web'] ) )
{
    $author_web = '<a href="' . esc_attr( $author['web'] ) . '" target="_blank" '. $nofollow . '>'
                  . '<span class="m-a-box-string-web">' . apply_filters( 'authorship/box/meta/web', ( $options['author_box_meta_web'] ? $options['author_box_meta_web'] : __( "Website", 'molongui-authorship' ) ), $author ) . '</span>'
                  . '</a>';
}
if ( 'slim' === $options['author_box_layout'] and $options['author_box_related_show'] and ( !empty( $author['posts'] ) or !empty( $options['author_box_related_show_empty'] ) ) )
{
    $author_more = '<a href="javascript:ToggleAuthorshipData(' . $random_id . ', \'' . $author['type'].'-'.$author['id'] . '\')" class="m-a-box-data-toggle" ' . $nofollow . '><span class="m-a-box-string-more-posts">' . ( $options[ 'author_box_meta_posts' ] ? esc_html( apply_filters( 'authorship/box/meta/more', $options['author_box_meta_posts'], $author ) ) : __( "+ posts", 'molongui-authorship' ) ) . '</span></a>';
}
if ( !empty( $author_job ) )
{
    $showing_job = true;
    $meta .= $author_job;
}
if ( !empty( $author_company ) )
{
    if ( !empty( $showing_job ) )
    {
        $meta .= sprintf( '%s%s%s'
            , '&nbsp;'
            , '<span class="m-a-box-string-at">' . apply_filters( 'authorship/box/meta/at', ( $options['author_box_meta_at'] ? esc_attr( $options['author_box_meta_at'] ) : __( "at", 'molongui-authorship' ) ), $author ).'</span>'
            , '&nbsp;'
        );
    }

    $showing_company = true;
    $meta .= $author_company;
}
if ( !empty( $author_phone ) and ( $options['author_box_meta_show_phone'] or $author['show_meta_phone'] ) )
{
    if ( !empty( $showing_job ) or !empty( $showing_company ) )
    {
        $meta .= apply_filters( 'molongui_authorship/author_meta_separator', $separator, 'phone' );
    }

    $showing_phone = true;
    $meta .= $author_phone;
}
if ( !empty( $author_email ) and ( $options['author_box_meta_show_email'] or $author['show_meta_mail'] ) )
{
    if ( !empty( $showing_job ) or !empty( $showing_company ) or !empty( $showing_phone ) )
    {
        $meta .= apply_filters( 'molongui_authorship/author_meta_separator', $separator, 'email' );
    }

    $showing_email = true;
    $meta .= $author_email;
}
if ( !empty( $author_web ) )
{
    if ( !empty( $showing_job ) or !empty( $showing_company ) or !empty( $showing_phone ) or !empty( $showing_email ) )
    {
        $meta .= apply_filters( 'molongui_authorship/author_meta_separator', $separator, 'web' );
    }

    $showing_web = true;
    $meta .= $author_web;
}
if ( !empty( $author_more ) )
{
    if ( !empty( $showing_job ) or !empty( $showing_company ) or !empty( $showing_phone ) or !empty( $showing_email ) or !empty( $showing_web ) )
    {
        $meta .= apply_filters( 'molongui_authorship/author_meta_separator', $separator, 'more' );
    }

    $showing_more = true;
    $meta .= $author_more;
}
?>

<div class="m-a-box-item m-a-box-meta">
    <?php echo $meta; ?>
    <?php if ( !empty( $author_more ) ) : ?>
    <script type="text/javascript">
        if ( typeof window.ToggleAuthorshipData === 'undefined' )
        {
            function ToggleAuthorshipData(id, author)
            {
                let box_selector = '#mab-' + id;
                let box = document.querySelector(box_selector);
                if ( box.getAttribute('data-multiauthor') ) box_selector = '#mab-' + id + ' [data-author-ref="' + author + '"]';
                let label = document.querySelector(box_selector + ' ' + '.m-a-box-data-toggle');
                label.innerHTML = ( label.text.trim() === "<?php echo ( $options['author_box_meta_posts'] ? apply_filters( 'authorship/box/meta/more', $options['author_box_meta_posts'], $author ) : __( "+ posts", 'molongui-authorship' ) ); ?>" ? " <span class=\"m-a-box-string-bio\"><?php echo ( $options['author_box_meta_bio'] ? esc_html( apply_filters( 'authorship/box/meta/bio', $options['author_box_meta_bio'], $author ) ) : __( "Bio", 'molongui-authorship' ) ); ?></span>" : " <span class=\"m-a-box-string-more-posts\"><?php echo ( $options['author_box_meta_posts'] ? apply_filters( 'authorship/box/meta/more', $options['author_box_meta_posts'], $author ) : __( "+ posts", 'molongui-authorship' ) ); ?></span>" );
                let bio     = document.querySelector(box_selector + ' ' + '.m-a-box-bio');
                let related = document.querySelector(box_selector + ' ' + '.m-a-box-related-entries');

                if ( related.style.display === "none" )
                {
                    related.style.display = "block";
                    bio.style.display     = "none";
                }
                else
                {
                    related.style.display = "none";
                    bio.style.display     = "block";
                }
            }
        }
    </script>
    <?php endif; ?>
</div>