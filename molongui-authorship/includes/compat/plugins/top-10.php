<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'tptn_author', function ( $tptn_author, $author_info, $result, $args )
{
    $by  = sprintf( __( "%sby%s", 'molongui-authorship' ), ' ', ' ' ); //__( ' by ', 'top-10' );
    $pid = $result->ID;

    $byline = molongui_get_the_author_posts_link( $pid );

    return $byline ? '<span class="tptn_author"> ' . $by . $byline . '</span> ' : $tptn_author;
}, 10, 4 );