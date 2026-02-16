<?php

use Molongui\Authorship\Admin\Author_Box_Editor;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$has_pro = did_action( 'authorship_pro/loaded' );

Author_Box_Editor::render_heading( __( "Ready-to-use templates", 'molongui-authorship' ) );
Author_Box_Editor::render_notice( 'author_box_preset_notice', sprintf( __( "You can optionally select a preset to load ready-to-use templates for your author box. Then, you can further customize it, or you can directly use the settings in the %sContent%s, %sLayout%s and %sAdvanced%s tabs to design your author box from scratch.", 'molongui-authorship' ), '<strong>', '</strong>', '<strong>', '</strong>', '<strong>', '</strong>' ) );
Author_Box_Editor::render_select( 'author_box_preset', __( "Preset", 'molongui-authorship' ),
    array
    (
        ''        => __( "-- Select a Preset --", 'molongui-authorship' ),
        'default' => __( "Default", 'molongui-authorship' ),
        'blank'   => __( "Blank", 'molongui-authorship' ),
        'p1'      => __( "Preset 1", 'molongui-authorship' ),
        'p2'      => __( "Preset 2", 'molongui-authorship' ),
        'p3'      => __( "Preset 3", 'molongui-authorship' ),
        'p4'      => $has_pro ? __( "Preset 4", 'molongui-authorship' ) : __( "Preset 4 - Requires PRO", 'molongui-authorship' ),
        'p5'      => __( "Preset 5", 'molongui-authorship' ),
        'p6'      => __( "Preset 6", 'molongui-authorship' ),
        'p7'      => __( "Preset 7", 'molongui-authorship' ),
        'p8'      => __( "Preset 8", 'molongui-authorship' ),
        'p9'      => $has_pro ? __( "Preset 9", 'molongui-authorship' ) : __( "Preset 9 - Requires PRO", 'molongui-authorship' ),
        'p10'     => $has_pro ? __( "Preset 10", 'molongui-authorship' ) : __( "Preset 10 - Requires PRO", 'molongui-authorship' ),
        'p11'     => $has_pro ? __( "Preset 11", 'molongui-authorship' ) : __( "Preset 11 - Requires PRO", 'molongui-authorship' ),
        'p12'     => __( "Preset 12", 'molongui-authorship' ),
        'p13'     => __( "Preset 13", 'molongui-authorship' ),
    ),
    array
    (
        'parent' => '',
    )
);
