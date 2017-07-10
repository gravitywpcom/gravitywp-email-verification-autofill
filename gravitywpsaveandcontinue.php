<?php
/*
Plugin Name: GravityWP - Save and Continue
Plugin URI: http://gravitywp.com
Description: Save and Continue Extension for Gravity Flow.
Version: 1.0.0
Author: Erik van Beek, Yoren Chang
Author URI: http://gravitywp.com
License: GPL-3.0+

------------------------------------------------------------------------
Copyright 2017 GravityWP

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'GWP_SAVE_AND_CONTINUE_VERSION', '1.2' );

add_action( 'gravityflow_loaded', array( 'GWP_Save_And_Continue_Bootstrap', 'load' ), 1 );

class GWP_Save_And_Continue_Bootstrap {

	public static function load() {

		require_once( 'includes/class-step-save-and-continue.php' );

		Gravity_Flow_Steps::register( new Gravity_Flow_Step_Save_And_Continue() );

		require_once( 'class-save-and-continue.php' );

		gravity_flow_save_and_continue();
	}
}

function gravity_flow_save_and_continue() {
	if ( class_exists( 'Gravity_Flow_Save_And_Continue' ) ) {
		return Gravity_Flow_Save_And_Continue::get_instance();
	}
}
