<?php

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Plugin;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Settings extends \Molongui\Authorship\Common\Modules\Settings
{
    public function __construct()
    {
    }
    public static function display_contributors_ad()
    {
        ?>
            <div>
                <img src="https://ps.w.org/molongui-post-contributors/assets/banner-772x250.png">
                <p><?php printf( __( "Need to add custom attributions to your posts? The %sMolongui Post Contributors%s plugin allows you to add contributors to your posts and display them towards the post author. Reviewers, fact-checkers, editors, photographers, whatever you need to acknowledge.", 'molongui-authorship' ), '<strong>', '</strong>' ); ?></p>
                <p><?php _e( "Install now to acknowledge every contribution and take your articles to the next level.", 'molongui-authorship' ); ?></p>
                <p><?php printf( __( "It's %sfree%s!", 'molongui-authorship' ), '<strong>', '</strong>' ); ?></p>
                <a class="button button-secondary" href="<?php echo wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=molongui-post-contributors' ), 'install-plugin_molongui-post-contributors' ); ?>"><?php _e( "Install Now", 'molongui-authorship' ); ?></a>
            </div>
        <?php
    }

} // class
new Settings();