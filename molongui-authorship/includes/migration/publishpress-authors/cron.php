<?php
/*!
 * Migration tool for PublishPress Authors data.
 *
 * Handles the migration of guest authors and post authorship metadata from the PublishPress Authors plugin to
 * Molongui Authorship format.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration/publishpress-authors
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration\Publishpress_Authors;

use Molongui\Authorship\Admin\Post_Count_Updater;
use Molongui\Authorship\Migration\Background_Process;
use Molongui\Authorship\Migration\Publishpress_Authors;
use Molongui\Authorship\Migration\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Cron extends Background_Process
{
    protected $author_tax;
    use Utils;
    public function __construct()
    {
        $this->name       = Publishpress_Authors::instance()->get_name();
        $this->id         = Publishpress_Authors::instance()->get_id();
        $this->path       = Publishpress_Authors::instance()->get_path();
        $this->author_tax = Publishpress_Authors::instance()->get_author_tax();

        parent::__construct();
    }
    protected function migrate_data()
    {
        $r1 = $r2 = true;
        if ( !taxonomy_exists( $this->author_tax ) )
        {
            register_taxonomy( $this->author_tax, 'post', array() );
        }
        if ( apply_filters( 'molongui_authorship/migrate_ppma_guest_authors', true ) )
        {
            $r1 = $this->migrate_guests();
        }
        if ( apply_filters( 'molongui_authorship/migrate_ppma_postmeta', true ) )
        {
            $r2 = $this->migrate_postmeta();
        }

        return $r1 and $r2;
    }
    public function migrate_guests()
    {
        $r = true;
        $guests = Publishpress_Authors::instance()->get_guest_authors();

        if ( !empty( $guests ) )
        {
            foreach ( $guests as $guest )
            {
                $this->push_to_queue( array( 'type' => 'guest', 'id' => $guest ) );
            }
            $r = $this->save()->dispatch();
        }

        return $r;
    }
    public function migrate_postmeta()
    {
        $r = true;
        $post_ids = Publishpress_Authors::instance()->get_posts();

        if ( !empty( $post_ids ) )
        {
            foreach ( $post_ids as $post_id )
            {
                $this->push_to_queue( array( 'type' => 'post', 'id' => $post_id ) );
            }
            $r = $this->save()->dispatch();
        }

        return $r;
    }
    protected function task( $item )
    {
        if ( !is_array( $item ) or !isset( $item['type'] ) or !isset( $item['id'] ) )
        {
            return false;
        }

        $type = $item['type'];
        $id   = $item['id'];

        switch ( $type )
        {
            case 'guest':
                Publishpress_Authors::instance()->convert_guest( $id );
                break;

            case 'post':
                Publishpress_Authors::instance()->convert_postmeta( $id );
                break;
        }
        return false;
    }
    protected function complete()
    {
        parent::complete();
        $count_updater = Post_Count_Updater::instance();
        $count_updater->handle_internal_request();
    }

} // class
new Cron();
