<?php
/*!
 * WP-CLI migration tool for Co-Authors Plus data into Molongui Authorship.
 *
 * This script provides a command-line interface to migrate guest authors and post author meta from Co-Authors Plus to
 * Molongui Authorship, bypassing the WordPress admin.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration/co-authors-plus
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration\Co_Authors_Plus;

use Molongui\Authorship\Migration\Co_Authors_Plus;
use Molongui\Authorship\Migration\Utils;
class Cli
{
    protected $name, $id;
    use Utils;
    public function __construct()
    {
        $this->name = Co_Authors_Plus::instance()->get_name();
        $this->id   = Co_Authors_Plus::instance()->get_id();
        \WP_CLI::line('');
        \WP_CLI::line( \WP_CLI::colorize( "%BMolongui Authorship%n - The {$this->name} data migration tool is running..." ) );

        if ( is_plugin_active( Co_Authors_Plus::instance()->get_path() ) )
        {
            $this->migrate_guests();
            $this->migrate_postmeta();

            \WP_CLI::line('');
            \WP_CLI::success( sprintf( "%s data migration completed.", $this->name ) );
        }
        else
        {
            \WP_CLI::line('');
            \WP_CLI::error( sprintf( "To migrate data, please first activate the %s plugin.", $this->name ) );
        }
    }
    public function migrate_guests()
    {
        \WP_CLI::line('');
        \WP_CLI::line( \WP_CLI::colorize( "%YNotice:%n Starting guest author data migration..." ) );

        $post_ids = Co_Authors_Plus::instance()->get_guest_authors();

        if ( !empty( $post_ids ) )
        {
            foreach ( $post_ids as $post_id )
            {
                Co_Authors_Plus::instance()->convert_guest( $post_id );
            }

            \WP_CLI::success( 'Guest Authors data migrated.' );
        }
        else
        {
            \WP_CLI::warning( 'No guest authors found to migrate. All existing authors are already registered users.' );
        }
    }
    public function migrate_postmeta()
    {
        \WP_CLI::line('');
        \WP_CLI::line( \WP_CLI::colorize( "%YNotice:%n Starting post authorship data migration..." ) );
        $post_ids = Co_Authors_Plus::instance()->get_posts();

        if ( !empty( $post_ids ) )
        {
            foreach ( $post_ids as $post_id )
            {
                Co_Authors_Plus::instance()->convert_postmeta( $post_id );
            }

            \WP_CLI::success( 'Post authorship data migrated.' );
        }
        else
        {
            \WP_CLI::warning( 'No post authorship data requires migration. All posts already have valid authorship settings.' );
        }
    }

}
