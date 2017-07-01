<?php
/**
 * Gravity Flow Save And Continue
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Extension
 * @copyright   Copyright (c) 2015-2017, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0.0
 */

// Make sure Gravity Forms is active and already loaded.
if ( class_exists( 'GFForms' ) ) {

	class Gravity_Flow_Save_And_Continue extends Gravity_Flow_Extension {

		private static $_instance = null;

		public $_version = GWP_SAVE_AND_CONTINUE_VERSION;

		// The Framework will display an appropriate message on the plugins page if necessary
		protected $_min_gravityforms_version = '1.9.10';

		protected $_slug = 'gravitywpsaveandcontinue';

		protected $_path = 'gravitywpsaveandcontinue/saveandcontinue.php';

		protected $_full_path = __FILE__;

		// Title of the plugin to be used on the settings page, form settings and plugins page.
		protected $_title = 'Save And Continue Extension';

		// Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
		protected $_short_title = 'Save And Continue';

		protected $_capabilities = array(
			'gravitywpsaveandcontinue_uninstall',
			'gravitywpsaveandcontinue_settings',
		);

		protected $_capabilities_app_settings = 'gravitywpsaveandcontinue_settings';
		protected $_capabilities_uninstall = 'gravitywpsaveandcontinue_uninstall';

		public static $save_and_continue_validation_error = '';

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new Gravity_Flow_Save_And_Continue();
			}

			return self::$_instance;
		}

		private function __clone() {
		} /* do nothing */
	}
}
