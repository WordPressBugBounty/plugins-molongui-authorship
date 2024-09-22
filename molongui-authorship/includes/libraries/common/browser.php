<?php

namespace Molongui\Authorship\Includes\Libraries\Common;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !class_exists( 'Molongui\Authorship\Includes\Libraries\Common\Browser' ) )
{
	class Browser extends \Molongui\Authorship\Common\Libraries\Browser
	{
		public function __construct( $userAgent = '' )
		{
			parent::__construct( $userAgent );
		}
	}
}