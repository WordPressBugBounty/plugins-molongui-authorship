<?php

use Molongui\Authorship\Post;

defined( 'ABSPATH' ) || exit;  


add_filter( 'get_the_author_nickname', function()
{
    return Post::get_byline();
});
