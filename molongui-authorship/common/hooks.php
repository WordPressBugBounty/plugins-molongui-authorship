<?php

use Molongui\Authorship\Common\Utils\Assets;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

add_action( 'admin_enqueue_scripts', array( Assets::class, 'register_media_uploader' ) );
add_action( 'admin_enqueue_scripts', array( Assets::class, 'register_sweetalert' ) );
add_action( 'init', array( Assets::class, 'register_element_queries' ) );
