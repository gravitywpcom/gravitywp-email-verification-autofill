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


		public function init() {
			parent::init();
			add_action( 'gform_after_submission', array( $this, 'action_gform_after_submission' ), 999, 2 );
			add_filter( 'gform_form_tag', array( $this, 'filter_gform_form_tag' ), 10, 2 );
			add_filter( 'gform_validation', array( $this, 'filter_gform_validation' ) );
			add_filter( 'gform_save_field_value', array( $this, 'filter_save_field_value' ), 10, 5 );
		}

		/**
		 * Callback for the gform_after_submission action.
		 *
		 * If appropriate, completes the step and processes the workflow.
		 *
		 * @param $entry
		 * @param $form
		 */
		public function action_gform_after_submission( $entry, $form ) {
			$this->log_debug( __METHOD__ . '() starting' );
			if ( ! isset( $_POST['workflow_parent_entry_id'] ) ) {
				return;
			}

			$parent_entry_id = absint( rgpost( 'workflow_parent_entry_id' ) );

			$hash = rgpost( 'workflow_hash' );

			if ( empty( $hash ) ) {
				return;
			}

			$parent_entry = GFAPI::get_entry( $parent_entry_id );

			$api = new Gravity_Flow_API( $parent_entry['form_id'] );

			$current_step = $api->get_current_step( $parent_entry );

			if ( empty( $current_step ) || $current_step->get_type() != 'save_and_continue' ) {
				return;
			}

			$verify_hash = $this->get_workflow_hash( $parent_entry_id, $current_step );
			if ( ! hash_equals( $hash, $verify_hash ) ) {
				return;
			}

			$note = esc_html__( 'Submission received.', 'gravitywpsaveandcontinue' );

			$current_step->add_note( $note, false, $current_step->get_type() );

			$api->process_workflow( $parent_entry_id );
		}

		/**
		 * Target for the gform_form_tag filter. Adds the parent entry ID and hash as a hidden fields.
		 *
		 * @param $form_tag
		 * @param $form
		 *
		 * @return string
		 */
		public function filter_gform_form_tag( $form_tag, $form ) {
			if ( ! isset( $_REQUEST['workflow_parent_entry_id'] ) ) {
				return $form_tag;
			}

			$parent_entry_id = absint( rgget( 'workflow_parent_entry_id' ) );

			$parent_entry = GFAPI::get_entry( $parent_entry_id );

			$api = new Gravity_Flow_API( $parent_entry['form_id'] );

			$current_step = $api->get_current_step( $parent_entry );

			if ( empty( $current_step ) ) {
				return $form_tag;
			}

			if ( $current_step->get_type() != 'save_and_continue' ) {
				$form_tag .= sprintf( '<div class="validation_error">%s</div>', esc_html__( 'The link to this form is no longer valid.' ) );
				return $form_tag;
			}

			$hash = sanitize_text_field( rgget( 'workflow_hash' ) );

			$hash_tag = sprintf( '<input type="hidden" name="workflow_hash" value="%s"/>', $hash );
			$parent_entry_id_tag = sprintf( '<input type="hidden" name="workflow_parent_entry_id" value="%s"/>',  $parent_entry_id );

			return $form_tag . $parent_entry_id_tag . $hash_tag;
		}


		/**
		 * Callback for the gform_validation filter.
		 *
		 * Validates that the parent ID is valid and that the entry is on a save and continue step.
		 *
		 * @param $validation_result
		 *
		 * @return mixed
		 */
		public function filter_gform_validation( $validation_result ) {
			$parent_entry_id = absint( rgpost( 'workflow_parent_entry_id' ) );

			if ( empty( $parent_entry_id ) ) {
				return $validation_result;
			}

			$hash = rgpost( 'workflow_hash' );

			if ( empty( $hash ) ) {
				return $validation_result;
			}

			$parent_entry = GFAPI::get_entry( $parent_entry_id );

			if ( is_wp_error( $parent_entry ) ) {
				$validation_result['is_valid'] = false;
				$this->customize_validation_message( __( 'This form is no longer valid.', 'gravitywpsaveandcontinue' ) );
				add_filter( 'gform_validation_message', array( $this, 'filter_gform_validation_message' ), 10, 2 );
				return $validation_result;
			}

			$api = new Gravity_Flow_API( $parent_entry['form_id'] );

			$current_step = $api->get_current_step( $parent_entry );

			if ( empty( $current_step ) ) {
				$this->customize_validation_message( __( 'This form is no longer accepting submissions.', 'gravitywpsaveandcontinue' ) );
				$validation_result['is_valid'] = false;
				return $validation_result;
			}

			$verify_hash = $this->get_workflow_hash( $parent_entry_id, $current_step );
			if ( ! hash_equals( $hash, $verify_hash ) ) {
				$this->customize_validation_message( __( 'There was a problem with you submission. Please use the link provided.', 'gravitywpsaveandcontinue' ) );
				$validation_result['is_valid'] = false;
			}

			return $validation_result;
		}

		/**
		 * Returns a hash based on the current entry ID and the step timestamp.
		 *
		 * @param int $parent_entry_id
		 * @param Gravity_Flow_Step $step
		 *
		 * @return string
		 */
		public function get_workflow_hash( $parent_entry_id, $step ) {
			return wp_hash( 'workflow_parent_entry_id:' . $parent_entry_id . $step->get_step_timestamp() );

		}

		/**
		 * Sets up the custom validation message.
		 *
		 * @param $message
		 */
		public function customize_validation_message( $message ) {
			self::$save_and_continue_validation_error = $message;
			add_filter( 'gform_validation_message', array( $this, 'filter_gform_validation_message' ), 10, 2 );
		}

		/**
		 * Callback for the gform_validation_message filter.
		 *
		 * Customizes the validation message.
		 *
		 * @param $message
		 * @param $form
		 *
		 * @return string
		 */
		public function filter_gform_validation_message( $message, $form ) {

			return "<div class='validation_error'>" . esc_html( self::$save_and_continue_validation_error ) . '</div>';
		}

		/**
		 * Target for the gform_save_field_value filter.
		 *
		 * Ensures that the values for hidden and administrative fields are mapped from the source entry.
		 *
		 *
		 * @param string $value
		 * @param array $entry
		 * @param GF_Field $field
		 * @param array $form
		 * @param string $input_id
		 *
		 * @return mixed
		 */
		public function filter_save_field_value( $value, $entry, $field, $form, $input_id ) {
			$parent_entry_id = absint( rgpost( 'workflow_parent_entry_id' ) );

			if ( empty( $parent_entry_id ) ) {
				return $value;
			}

			$hash = rgpost( 'workflow_hash' );

			if ( empty( $hash ) ) {
				return $value;
			}

			if ( ! $field instanceof GF_Field ) {
				return $value;
			}

			if ( ! ( $field->get_input_type() == 'hidden' || $field->is_administrative() || $field->visibility == 'hidden' ) ) {
				return $value;
			}

			$parent_entry = GFAPI::get_entry( $parent_entry_id );

			if ( is_wp_error( $parent_entry ) ) {
				return $value;
			}

			$api = new Gravity_Flow_API( $parent_entry['form_id'] );

			/* @var Gravity_Flow_Step_Form_Submission $current_step */
			$current_step = $api->get_current_step( $parent_entry );

			if ( empty( $current_step ) || $current_step->get_type() != 'save_and_continue' ) {
				return $value;
			}

			$parent_entry = $current_step->get_entry();
			$mapped_entry = $current_step->do_mapping( $form, $parent_entry );

			return isset( $mapped_entry[ $input_id ] ) ? $mapped_entry[ $input_id ] : $value;
		}
	}
}
