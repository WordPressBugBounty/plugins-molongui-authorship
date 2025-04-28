<?php
/*!
 * Migration CLI tool for PublishPress Authors data.
 *
 * Provides WP-CLI commands to manually migrate guest authors and postmeta data from PublishPress Authors to
 * Molongui Authorship.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration/publishpress-authors
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration\Publishpress_Authors;

use Molongui\Authorship\Migration\Publishpress_Authors;
use Molongui\Authorship\Migration\Utils;
class Cli
{
    protected $name, $id;
    use Utils;
    public function __construct()
    {
        $this->name = Publishpress_Authors::instance()->get_name();
        $this->id   = Publishpress_Authors::instance()->get_id();
        \WP_CLI::line('');
        \WP_CLI::line( \WP_CLI::colorize( "%BMolongui Authorship%n - The {$this->name} data migration tool is running..." ) );

        if ( is_plugin_active( Publishpress_Authors::instance()->get_path() ) )
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

        $guests = Publishpress_Authors::instance()->get_guest_authors();

        if ( !empty( $guests ) )
        {
            foreach ( $guests as $guest )
            {
                Publishpress_Authors::instance()->convert_guest( $guest );
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
        $post_ids = Publishpress_Authors::instance()->get_posts();

        if ( !empty( $post_ids ) )
        {
            foreach ( $post_ids as $post_id )
            {
                Publishpress_Authors::instance()->convert_postmeta( $post_id );
            }

            \WP_CLI::success( 'Post authorship data migrated.' );
        }
        else
        {
            \WP_CLI::warning( 'No post authorship data requires migration. All posts already have valid authorship settings.' );
        }
    }

}
