<?php
/*!
 * Migration tool for One User Avatar plugin data into Molongui Authorship.
 *
 * Provides background migration functionality to seamlessly transition custom user avatars from One User Avatar to
 * Molongui Authorship.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration/one-user-avatar
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration\One_User_Avatar;

use Molongui\Authorship\Migration\Background_Process;
use Molongui\Authorship\Migration\One_User_Avatar;
use Molongui\Authorship\Migration\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Cron extends Background_Process
{
    use Utils;
    public function __construct()
    {
        $this->name = One_User_Avatar::instance()->get_name();
        $this->id   = One_User_Avatar::instance()->get_id();
        $this->path = One_User_Avatar::instance()->get_path();

        parent::__construct();
    }
    protected function migrate_data()
    {
        $r = true;
        if ( apply_filters( 'molongui_authorship/migrate_oua_avatars', true ) )
        {
            $r = $this->migrate_avatars();
        }

        return $r;
    }
    public function migrate_avatars()
    {
        $r = true;
        $relationships = One_User_Avatar::instance()->get_user_attachment_relationships();

        if ( !empty( $relationships ) )
        {
            foreach ( $relationships as $relationship )
            {
                $this->push_to_queue( array( 'attachment_id' => $relationship['post_id'], 'user_id' => $relationship['meta_value'] ) );
            }
            $r = $this->save()->dispatch();
        }

        return $r;
    }
    protected function task( $item )
    {
        if ( !is_array( $item ) or !isset( $item['user_id'] ) or !isset( $item['attachment_id'] ) )
        {
            return false;
        }
        One_User_Avatar::instance()->update_user_metadata( $item );
        return false;
    }

} // class
new Cron();
