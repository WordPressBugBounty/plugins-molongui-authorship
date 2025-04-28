<?php
/*!
 * WP-CLI integration for Molongui Authorship.
 *
 * Registers custom WP-CLI commands to interact with the Molongui Authorship plugin, enabling tasks like migrating
 * authorship data, updating post counters, and adding default authorship settings via the command line.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/integrations
 * @since      5.1.0
 */

namespace Molongui\Authorship\Integrations;
defined( 'WP_CLI' ) && WP_CLI or exit; // Exit if WP-CLI not active
class MolonguiAuthorship_CLI extends \WP_CLI_Command
{
    protected $version = '1.0.0';
    protected $dry_run;
    public function init_post_authorship( $args, $assoc_args )
    {
    }
    public function update_post_count( $args, $assoc_args )
    {
    }
    public function migrate_coauthors_plus( $args, $assoc_args )
    {
        if ( apply_filters( 'molongui_authorship/migrate_coauthors_plus', false ) )
        {
            new \Molongui\Authorship\Migration\Co_Authors_Plus\Cli();
        }
    }
    public function migrate_publishpress_authors( $args, $assoc_args )
    {
        if ( apply_filters( 'molongui_authorship/migrate_publishpress_authors', false ) )
        {
            new \Molongui\Authorship\Migration\Publishpress_Authors\Cli();
        }
    }
    public function migrate_one_user_avatar( $args, $assoc_args )
    {
        if ( apply_filters( 'molongui_authorship/migrate_one_user_avatar', false ) )
        {
            new \Molongui\Authorship\Migration\One_User_Avatar\Cli();
        }
    }

}
add_action( 'cli_init', function()
{
    \WP_CLI::add_command( 'molongui-authorship', __NAMESPACE__ . '\\MolonguiAuthorship_CLI' );
});
