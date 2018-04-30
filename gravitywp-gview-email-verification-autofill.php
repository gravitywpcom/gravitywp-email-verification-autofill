<?php
/**
 * Plugin Name: GravityWP - Email Verification Autofill
 * Plugin URI: http://gravitywp.com/
 * Description: Autofill email verification field when loading the form on the front end (editing entry with Gravity View shortcode).
 * Version: 1.0.0
 * Author: GravityWP
 * Author URI: http://gravitywp.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Class GravityWP_Email_Verfication_Autofill
 * @todo revision date merge tag
 */
class GravityWP_Email_Verfication_Autofill {

	/**
	 * Instantiate the class
	 *
	 * @since 1.0.0
	 */
	public static function load() {
		if ( ! did_action( 'gravitywp_email_verification_autofill_loaded' ) ) {
			new self;
			do_action( 'gravitywp_email_verification_autofill_loaded' );
		}
	}

	/**
	 * GravityWP_Email_Verfication_Autofill constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->add_hooks();
	}

	/**
	 * Add hooks on the single entry screen
	 *
	 * @since 1.0.0
	 */
	private function add_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue JS scripts
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'gravitywp_email_verification_autofill', plugins_url( '/js/scripts.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
	}
}

add_action( 'gform_loaded', array( 'GravityWP_Email_Verfication_Autofill', 'load' ) );
