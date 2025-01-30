<?php
/*!
 * WP-CLI integration for Molongui Authorship.
 *
 * This file registers a custom command for WP-CLI to interact with the Molongui Authorship plugin, allowing for tasks
 * such as migrating authorship data from other plugins, updating post counters, and adding default settings via the
 * command line.
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
        $migrator = new \Molongui\Authorship\Migration\Co_Authors_Plus();
        $migrator->run();
        \WP_CLI::success( 'Co-Authors Plus data migration completed.' );
    }
    public function migrate_publishpress_authors( $args, $assoc_args )
    {
    }

}
add_action( 'cli_init', function()
{
    \WP_CLI::add_command( 'molongui-authorship', 'MolonguiAuthorship_CLI' );
});
