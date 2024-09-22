<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'tptn_author', function ( $tptn_author, $author_info, $result, $args )
{
    $by  = sprintf( __( "%sby%s", 'molongui-authorship' ), ' ', ' ' ); //__( ' by ', 'top-10' );
    $pid = $result->ID;

    $byline = get_the_molongui_author_posts_link( $pid );

    return $byline ? '<span class="tptn_author"> ' . $by . $byline . '</span> ' : $tptn_author;
}, 10, 4 );