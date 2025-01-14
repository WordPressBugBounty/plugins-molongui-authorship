<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'molongui_edit_main_query_only', '__return_false' );