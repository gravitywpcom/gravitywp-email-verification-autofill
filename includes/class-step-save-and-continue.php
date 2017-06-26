<?php

/**
 * Gravity Flow Save And Continue Step
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Step
 * @copyright   Copyright (c) 2017, GravityWP
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0
 */

if ( class_exists( 'Gravity_Flow_Step' ) ) {

	class Gravity_Flow_Step_Save_And_Continue extends Gravity_Flow_Step {
		public $_step_type = 'save_and_continue';

		public function get_label() {
			return esc_html__( 'Save And Continue', 'gravitywpsaveandcontinue' );
		}

		public function get_settings() {
			$forms = $this->get_forms();
			$form_choices[] = array( 'label' => esc_html__( 'Select a Form', 'gravitywpsaveandcontinue' ), 'value' => '' );
			foreach ( $forms  as $form ) {
				$form_choices[] = array( 'label' => $form->title, 'value' => $form->id );
			}

			$settings = array(
				'title'  => esc_html__( 'Save And Continue', 'gravitywpsaveandcontinue' ),
				'fields' => array(
					array(
						'name' => 'target_form_id',
						'label' => esc_html__( 'Continue to Form', 'gravitywpsaveandcontinue' ),
						'tooltip'       => __( 'Select the form to be used for this save and continue step.', 'gravitywpsaveandcontinue' ),
						'type' => 'select',
						'onchange'    => "jQuery(this).closest('form').submit();",
						'choices' => $form_choices,
					),
				),
			);

			// Use Generic Map setting to allow custom values.
			$mapping_field = array(
				'name' => 'mappings',
				'label' => esc_html__( 'Field Mapping', 'gravitywpsaveandcontinue' ),
				'type' => 'generic_map',
				'enable_custom_key' => true,
				'enable_custom_value' => true,
				'key_field_title' => esc_html__( 'Field', 'gravitywpsaveandcontinue' ),
				'value_field_title' => esc_html__( 'Value', 'gravitywpsaveandcontinue' ),
				'value_choices' => $this->value_mappings(),
				'key_choices' => $this->field_mappings(),
				'tooltip'   => '<h6>' . esc_html__( 'Mapping', 'gravitywpsaveandcontinue' ) . '</h6>' . esc_html__( 'Map the fields of this form to the selected form. Values from this form will be saved in the entry in the selected form' , 'gravitywpsaveandcontinue' ),
				'dependency' => array(
					'field'  => 'target_form_id',
					'values' => array( '_notempty_' ),
				),
			);

			$settings['fields'][] = $mapping_field;

			return $settings;
		}

		/**
		 * Prepare field map.
		 *
		 * @return array
		 */
		public function field_mappings() {

			$target_form_id = $this->get_setting( 'target_form_id' );

			if ( empty( $target_form_id ) ) {
				return false;
			}

			$target_form = $this->get_target_form();

			if ( empty( $target_form ) ) {
				return false;
			}

			$fields = $this->get_field_map_choices( $target_form );
			return $fields;
		}

		/**
		 * Prepare value map.
		 *
		 * @return array
		 */
		public function value_mappings() {

			$form = $this->get_form();

			$fields = $this->get_field_map_choices( $form );
			return $fields;
		}

		public function get_forms() {
			$forms = GFFormsModel::get_forms();
			return $forms;
		}

		public function get_target_form() {
			$target_form_id = $this->get_setting( 'target_form_id' );
			$form = GFAPI::get_form( $target_form_id );
			return $form;
		}

		public function get_field_map_choices( $form, $field_type = null, $exclude_field_types = null ) {

			$fields = array();

			// Setup first choice
			if ( rgblank( $field_type ) || ( is_array( $field_type ) && count( $field_type ) > 1 ) ) {

				$first_choice_label = __( 'Select a Field', 'gravitywpsaveandcontinue' );

			} else {

				$type = is_array( $field_type ) ? $field_type[0] : $field_type;
				$type = ucfirst( GF_Fields::get( $type )->get_form_editor_field_title() );

				$first_choice_label = sprintf( __( 'Select a %s Field', 'gravitywpsaveandcontinue' ), $type );

			}

			$fields[] = array( 'value' => '', 'label' => $first_choice_label );

			// if field types not restricted add the default fields and entry meta
			if ( is_null( $field_type ) ) {
				$fields[] = array( 'value' => 'id', 'label' => esc_html__( 'Entry ID', 'gravitywpsaveandcontinue' ) );
				$fields[] = array( 'value' => 'date_created', 'label' => esc_html__( 'Entry Date', 'gravitywpsaveandcontinue' ) );
				$fields[] = array( 'value' => 'ip', 'label' => esc_html__( 'User IP', 'gravitywpsaveandcontinue' ) );
				$fields[] = array( 'value' => 'source_url', 'label' => esc_html__( 'Source Url', 'gravitywpsaveandcontinue' ) );
				$fields[] = array( 'value' => 'created_by', 'label' => esc_html__( 'Created By', 'gravitywpsaveandcontinue' ) );

//				$entry_meta = GFFormsModel::get_entry_meta( $form['id'] );
//				foreach ( $entry_meta as $meta_key => $meta ) {
//					$fields[] = array( 'value' => $meta_key, 'label' => rgars( $entry_meta, "{$meta_key}/label" ) );
//				}
			}

			// Populate form fields
			if ( is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					$input_type = $field->get_input_type();
					$inputs     = $field->get_entry_inputs();
					$field_is_valid_type = ( empty( $field_type ) || ( is_array( $field_type ) && in_array( $input_type, $field_type ) ) || ( ! empty( $field_type ) && $input_type == $field_type ) );

					if ( is_null( $exclude_field_types ) ) {
						$exclude_field = false;
					} elseif ( is_array( $exclude_field_types ) ) {
						if ( in_array( $input_type, $exclude_field_types ) ) {
							$exclude_field = true;
						} else {
							$exclude_field = false;
						}
					} else {
						//not array, so should be single string
						if ( $input_type == $exclude_field_types ) {
							$exclude_field = true;
						} else {
							$exclude_field = false;
						}
					}

					if ( is_array( $inputs ) && $field_is_valid_type && ! $exclude_field ) {
						//If this is an address field, add full name to the list
						if ( $input_type == 'address' ) {
							$fields[] = array(
								'value' => $field->id,
								'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravitywpsaveandcontinue' ) . ')',
							);
						}
						//If this is a name field, add full name to the list
						if ( $input_type == 'name' ) {
							$fields[] = array(
								'value' => $field->id,
								'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravitywpsaveandcontinue' ) . ')',
							);
						}
						//If this is a checkbox field, add to the list
						if ( $input_type == 'checkbox' ) {
							$fields[] = array(
								'value' => $field->id,
								'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Selected', 'gravitywpsaveandcontinue' ) . ')',
							);
						}

						foreach ( $inputs as $input ) {
							$fields[] = array(
								'value' => $input['id'],
								'label' => GFCommon::get_label( $field, $input['id'] ),
							);
						}
					} elseif ( $input_type == 'list' && $field->enableColumns && $field_is_valid_type && ! $exclude_field ) {
						$fields[] = array(
							'value' => $field->id,
							'label' => GFCommon::get_label( $field ) . ' (' . esc_html__( 'Full', 'gravitywpsaveandcontinue' ) . ')',
						);
						$col_index = 0;
						foreach ( $field->choices as $column ) {
							$fields[] = array(
								'value' => $field->id . '.' . $col_index,
								'label' => GFCommon::get_label( $field ) . ' (' . esc_html( rgar( $column, 'text' ) ) . ')',
							);
							$col_index ++;
						}
					} elseif ( ! rgar( $field, 'displayOnly' ) && $field_is_valid_type && ! $exclude_field ) {
						$fields[] = array( 'value' => $field->id, 'label' => GFCommon::get_label( $field ) );
					}
				}
			}

			return $fields;
		}
	}

}
