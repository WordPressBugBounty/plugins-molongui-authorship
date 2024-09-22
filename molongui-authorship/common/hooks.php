<?php

use Molongui\Authorship\Common\Utils\Assets;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly


add_action( 'admin_enqueue_scripts', array( Assets::class, 'register_media_uploader' ) );
add_action( 'admin_enqueue_scripts', array( Assets::class, 'register_sweetalert' ) );
add_action( 'admin_init', array( Assets::class, 'register_selectr' ) );
add_action( 'admin_init', array( Assets::class, 'register_sortable' ) );
add_action( 'admin_init', array( Assets::class, 'register_semantic_ui_dropdown' ) );
add_action( 'admin_init', array( Assets::class, 'register_semantic_ui_transition' ) );
add_action( 'admin_init', array( Assets::class, 'register_semantic_ui_icon' ) );
add_action( 'admin_init', array( Assets::class, 'register_semantic_ui_label' ) );
add_action( 'admin_init', array( Assets::class, 'register_semantic_ui_popup' ) );
add_action( 'init', array( Assets::class, 'register_element_queries' ) );
