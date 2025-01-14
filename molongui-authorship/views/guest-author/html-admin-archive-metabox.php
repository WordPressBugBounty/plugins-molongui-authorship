<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

?>

<div class="molongui-metabox">

    <!-- Archive this author -->
    <div class="m-field">
        <div class="input-wrap">
            <input type="checkbox" id="_molongui_guest_author_archived" name="_molongui_guest_author_archived" value="1" <?php checked( $guest_author_archived, 1 ); ?>>
            <label class="checkbox-label" for="_molongui_guest_author_archived"><?php _e( "Check to archive this author and prevent them from being eligible as an author for your posts.", 'molongui-authorship' ); ?></label>
        </div>
    </div>

</div>
