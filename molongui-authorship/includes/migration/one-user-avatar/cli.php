<?php
/*!
 * WP-CLI tool for migrating One User Avatar data to Molongui Authorship.
 *
 * This file provides a command-line utility to migrate custom user avatar metadata from the One User Avatar plugin into
 * the Molongui Authorship format.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration/one-user-avatar
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration\One_User_Avatar;

use Molongui\Authorship\Migration\One_User_Avatar;
use Molongui\Authorship\Migration\Utils;
class Cli
{
    protected $name, $id;
    use Utils;
    public function __construct()
    {
        $this->name = One_User_Avatar::instance()->get_name();
        $this->id   = One_User_Avatar::instance()->get_id();
        \WP_CLI::line('');
        \WP_CLI::line( \WP_CLI::colorize( "%BMolongui Authorship%n - The {$this->name} data migration tool is running..." ) );
            $this->migrate_avatars();

            \WP_CLI::line('');
            \WP_CLI::success( sprintf( "%s data migration completed.", $this->name ) );
    }
    public function migrate_avatars()
    {
        \WP_CLI::line('');
        \WP_CLI::line( \WP_CLI::colorize( "%YNotice:%n Starting user avatar data migration..." ) );
        $relationships = One_User_Avatar::instance()->get_user_attachment_relationships();

        if ( !empty( $relationships ) )
        {
            foreach ( $relationships as $relationship )
            {
                One_User_Avatar::instance()->update_user_metadata( array
                (
                    'attachment_id' => $relationship['post_id'],
                    'user_id'       => $relationship['meta_value'],
                ));
            }

            \WP_CLI::success( 'All custom user avatars migrated.' );
        }
        else
        {
            \WP_CLI::warning( 'No user avatars found to migrate.' );
        }
    }

}
