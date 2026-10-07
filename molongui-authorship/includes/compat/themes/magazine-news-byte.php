<?php

defined( 'ABSPATH' ) || exit;  


add_filter( 'molongui_edit_main_query_only', function( $default, &$query )
{
    return false;
}, 10, 2 );
