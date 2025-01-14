<?php

use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'get_the_author_nickname', function()
{
    return Post::get_byline();
});

