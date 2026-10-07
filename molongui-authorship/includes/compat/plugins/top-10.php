<?php

defined( 'ABSPATH' ) || exit;  


add_filter( 'tptn_author', function ( $tptn_author, $author_info, $result, $args )
{
    // translators: %s: Either whitespaces or HTML tags.
    $by  = sprintf( __( "%sby%s", 'molongui-authorship' ), ' ', ' ' );  
    $pid = $result->ID;

    $byline = molongui_get_the_author_posts_link( $pid );

    return $byline ? '<span class="tptn_author"> ' . $by . $byline . '</span> ' : $tptn_author;
}, 10, 4 );
