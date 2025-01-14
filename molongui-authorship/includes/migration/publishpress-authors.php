<?php
/*!
 * Tool for migrating data from the PublishPress Authors plugin to Molongui Authorship.
 *
 * This file provides the functionality to handle the migration process from the PublishPress Authors plugin, allowing
 * users to seamlessly transition their author data to Molongui Authorship.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration;

use Molongui\Authorship\Admin\Post_Count_Updater;
use Molongui\Authorship\Common\Libraries\WP_Background_Process;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Modules\Notice;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class PublishPress_Authors extends WP_Background_Process
{
} // class
new PublishPress_Authors();
