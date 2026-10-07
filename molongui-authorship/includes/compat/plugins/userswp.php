<?php

defined( 'ABSPATH' ) || exit;  


add_filter( 'uwp_check_redirect_author_page', function()
{
    if ( molongui_is_guest_author() ) return false;

    return true;
});
