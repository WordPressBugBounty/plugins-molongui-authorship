<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'molongui_authorship_bypass_original_user_id_if', '__return_true' );