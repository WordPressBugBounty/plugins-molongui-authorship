<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'get_the_author_nickname', function()
{
    return authorship_get_byline();
});

