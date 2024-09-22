<?php

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class MolonguiPostContributors
{
    public function __construct()
    {
        add_filter( 'authorship/pre_get_user_by', array( $this, 'disable_get_user_by_override' ), 10, 4 );
        add_filter( 'authorship/pre_author_link', array( $this, 'prevent_filtering_get_author_posts_url' ), 10, 4 );
    }
    public function disable_get_user_by_override( $user, $original_user, $field, $value )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
        $fn    = 'get_by';
        $class = 'Molongui\Contributors\Contributor';

        if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
             and
             isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
        {
            return $original_user;
        }

        return $user;
    }
    public function prevent_filtering_get_author_posts_url( $link, $original_link, $author_id, $author_nicename )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
        $fn    = 'get_the_contributor_name';
        $class = 'Molongui\Contributors\Template';

        if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            if ( isset( $dbt[$i]['class'] ) and $dbt[$i]['class'] === $class )
            {
                return $original_link;
            }
        }
        return $link;
    }

} // class
new MolonguiPostContributors;