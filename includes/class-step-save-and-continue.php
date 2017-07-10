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
			return esc_html__( 'Save and Continue', 'gravitywpsaveandcontinue' );
		}

		public function get_icon_url() {
			return '<i class="fa fa-step-forward"></i>';
		}

		public function get_settings() {
			$forms = $this->get_forms();
			$form_choices[] = array( 'label' => esc_html__( 'Select a Form', 'gravitywpsaveandcontinue' ), 'value' => '' );
			foreach ( $forms  as $form ) {
				$form_choices[] = array( 'label' => $form->title, 'value' => $form->id );
			}

			$fields = $this->get_field_map_choices( $this->get_form(), 'source', 'email' );
			$email_choices = array();
			foreach ( $fields  as $field ) {
				$email_choices[] = array( 'label' => $field['label'], 'value' => $field['value'] );
			}

			$fields = $this->get_field_map_choices( $this->get_form(), 'source', 'uid' );
			$uid_choices = array();
			foreach ( $fields  as $field ) {
				$uid_choices[] = array( 'label' => $field['label'], 'value' => $field['value'] );
			}

			$settings = array(
				'title'  => esc_html__( 'Save and Continue', 'gravitywpsaveandcontinue' ),
				'fields' => array(
					array(
						'type'              => 'text',
						'id'                => 'date_created',
						'name'              => 'date_created',
						'label'             => esc_html__( 'Date Created', 'gravitywpsaveandcontinue' ),
						'required'          => false,
						'class'             => 'small',
						'tooltip'           => esc_html__( 'Specify the date_created fields in the rg_incomplete_submissions table. It needs to be in the ISO date format: YYYY-mm-dd HH:ii:ss (eg. 2017-07-31 23:59:59). <br /><br /> Leave empty if you don\'t need to force the date.', 'gravitywpsaveandcontinue' ),
						'feedback_callback' => array( $this, 'validate_date_created' ),
					),
					array(
						'name' => 'email',
						'label' => esc_html__( 'Email Mapping', 'gravitywpsaveandcontinue' ),
						'tooltip'       => __( 'Map an email field from the current form to the target form. You must have an Email field in the current form.', 'gravitywpsaveandcontinue' ),
						'type' => 'select',
						'onchange'    => "jQuery(this).closest('form').submit();",
						'choices' => $email_choices,
						'required' => true
					),
					array(
						'name' => 'uuid',
						'label' => esc_html__( 'Unique ID Mapping', 'gravitywpsaveandcontinue' ),
						'tooltip'       => __( 'Map an Unique ID field from the current form to the target form. You must have a Unique ID field in the current form.', 'gravitywpsaveandcontinue' ),
						'type' => 'select',
						'onchange'    => "jQuery(this).closest('form').submit();",
						'choices' => $uid_choices,
						'required' => true
					),
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
				'enable_custom_key' => false,
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

			$fields = $this->get_field_map_choices( $target_form, 'target', null, array( 'fileupload' ) );
			return $fields;
		}

		/**
		 * Prepare value map.
		 *
		 * @return array
		 */
		public function value_mappings() {

			$form = $this->get_form();

			$fields = $this->get_field_map_choices( $form, 'source', null, array( 'fileupload' ) );
			return $fields;
		}

		function process() {
			$result = $this->insert_incomplete_submission();
			$note = $this->get_name() . ': ' . esc_html__( 'Processed.', 'gravityflow' );
			$this->add_note( $note, 0, $this->get_type() );

			return $result;
		}

		public function insert_incomplete_submission() {
			$entry = $this->get_entry();

			$form = $this->get_form();
			$target_form = $this->get_target_form();

			$new_entry = $this->do_mapping( $form, $entry );

			if ( ! empty( $new_entry ) ) {
				$_submission = $new_entry;

				$form_unique_id = GFFormsModel::get_form_unique_id( $form['id'] );
				$ip             = GFFormsModel::get_ip();
				$source_url     = GFFormsModel::get_current_page_url();

				$files = GFCommon::json_decode( stripslashes( RGForms::post( 'gform_uploaded_files' ) ) );
				if ( ! is_array( $files ) ) {
					$files = array();
				}

				$submission['submitted_values'] = $_submission;
				$submission['partial_entry']    = GFFormsModel::get_current_lead();
				$submission['field_values']     = "";
				$submission['page_number']      = 1;
				$submission['files']            = $files;
				$submission['gform_unique_id']  = $form_unique_id;

				$uuid = GFFormsModel::get_field( $form, $this->get_setting( 'uuid' ) );
				$email = GFFormsModel::get_field( $form, $this->get_setting( 'email' ) );

				global $wpdb;
				$result = $wpdb->insert(
					GFFormsModel::get_incomplete_submissions_table_name(),
					array(
						'uuid'         => $this->get_source_field_value( $entry, $uuid, $this->get_setting( 'uuid' ) ),
						'email'         => $this->get_source_field_value( $entry, $email, $this->get_setting( 'email' ) ),
						'form_id'      => $target_form['id'],
						'date_created' => ( $this->validate_date_created( $this->get_setting( 'date_created' ) ) ) ? $this->get_setting( 'date_created' ) : current_time( 'mysql', true ),
						'submission'   => json_encode( $submission ),
						'ip'           => $ip,
						'source_url'   => $source_url,
					),
					array(
						'%s',
						'%s',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
					)
				);

				if ( is_wp_error( $result ) ) {
					$this->log_debug( __METHOD__ .'(): failed to add incomplete submissions' );
				}
			}

			return true;
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

		public function get_field_map_choices( $form, $form_type = '', $field_type = null, $exclude_field_types = null ) {

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
//				$fields[] = array( 'value' => 'date_created', 'label' => esc_html__( 'Entry Date', 'gravitywpsaveandcontinue' ) );
//				$fields[] = array( 'value' => 'ip', 'label' => esc_html__( 'User IP', 'gravitywpsaveandcontinue' ) );
//				$fields[] = array( 'value' => 'source_url', 'label' => esc_html__( 'Source Url', 'gravitywpsaveandcontinue' ) );
				$fields[] = array( 'value' => 'created_by', 'label' => esc_html__( 'Created By', 'gravitywpsaveandcontinue' ) );

				if ( 'target' == $form_type ) { // SF for special fields that helps to identify or validate entry
					$fields[] = array( 'value' => 'email', 'label' => esc_html__( 'SF: Email', 'gravitywpsaveandcontinue' ) );
					$fields[] = array( 'value' => 'uuid', 'label' => esc_html__( 'SF: UUID', 'gravitywpsaveandcontinue' ) );
				}
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

//						foreach ( $inputs as $input ) {
//							$fields[] = array(
//								'value' => $input['id'],
//								'label' => GFCommon::get_label( $field, $input['id'] ),
//							);
//						}
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

		/**
		 * Maps the field values of the entry to the target form.
		 *
		 * @param $form
		 * @param $entry
		 *
		 * @return array $new_entry
		 */
		public function do_mapping( $form, $entry ) {
			$new_entry = array();

			if ( ! is_array( $this->mappings ) ) {

				return $new_entry;
			}

			$target_form = $this->get_target_form();

			if ( ! $target_form ) {
				$this->log_debug( __METHOD__ . '(): aborting; unable to get target form.' );

				return $new_entry;
			}

			foreach ( $this->mappings as $mapping ) {
				if ( rgblank( $mapping['key'] ) ) {
					continue;
				}

				$new_entry = $this->add_mapping_to_entry( $mapping, $entry, $new_entry, $form, $target_form );
			}

			return apply_filters( 'gravitywpsaveandcontinue_' . $this->get_type(), $new_entry, $entry, $form, $target_form, $this );
		}

		/**
		 * Add the mapped value to the new entry.
		 *
		 * @param array $mapping The properties for the mapping being processed.
		 * @param array $entry The entry being processed by this step.
		 * @param array $new_entry The entry to be added or updated.
		 * @param array $form The form being processed by this step.
		 * @param array $target_form The target form for the entry being added or updated.
		 *
		 * @return array
		 */
		public function add_mapping_to_entry( $mapping, $entry, $new_entry, $form, $target_form ) {
			$target_field_id = trim( $mapping['key'] );
			$source_field_id = (string) $mapping['value'];

			$source_field = GFFormsModel::get_field( $form, $source_field_id );

			if ( is_object( $source_field ) ) {
				$is_full_source      = $source_field_id === (string) intval( $source_field_id );
				$source_field_inputs = $source_field->get_entry_inputs();
				$target_field        = GFFormsModel::get_field( $target_form, $target_field_id );

				if ( $is_full_source && is_array( $source_field_inputs ) ) {
					$is_full_target      = $target_field_id === (string) intval( $target_field_id );
					$target_field_inputs = is_object( $target_field ) ? $target_field->get_entry_inputs() : false;

					if ( $is_full_target && is_array( $target_field_inputs ) ) {
						foreach ( $source_field_inputs as $input ) {
							$input_id               = str_replace( $source_field_id . '.', $target_field_id . '.', $input['id'] );
							$source_field_value     = $this->get_source_field_value( $entry, $source_field, $input['id'] );
							$new_entry[ $target_field_id ][ $input_id ] = $this->get_target_field_value( $source_field_value, $target_field, $input_id );
						}
					} else {
						$new_entry[ $target_field_id ] = $source_field->get_value_export( $entry, $source_field_id, true );
					}
				} else {
					$source_field_value            = $this->get_source_field_value( $entry, $source_field, $source_field_id );
					$new_entry[ $target_field_id ] = $this->get_target_field_value( $source_field_value, $target_field, $target_field_id );
				}
			} elseif ( $source_field_id == 'gf_custom' ) {
				$new_entry[ $target_field_id ] = GFCommon::replace_variables( $mapping['custom_value'], $form, $entry, false, false, false, 'text' );
			} else {
				$new_entry[ $target_field_id ] = $entry[ $source_field_id ];
			}

			return $new_entry;
		}

		/**
		 * Get the source field value.
		 *
		 * Returns the choice text instead of the unique value for choice based poll, quiz and survey fields.
		 *
		 * The source field choice unique value will not match the target field unique value.
		 *
		 * @param array $entry The entry being processed by this step.
		 * @param GF_Field $source_field The source field being processed.
		 * @param string $source_field_id The ID of the source field or input.
		 *
		 * @return string
		 */
		public function get_source_field_value( $entry, $source_field, $source_field_id ) {

			if ( ! isset( $entry[ $source_field_id ] ) ) {
				return '';
			}
			$field_value = $entry[ $source_field_id ];

			if ( in_array( $source_field->type, array( 'poll', 'quiz', 'survey' ) ) ) {
				if ( $source_field->inputType == 'rank' ) {
					$values = explode( ',', $field_value );
					foreach ( $values as &$value ) {
						$value = $this->get_source_choice_text( $value, $source_field );
					}

					return implode( ',', $values );
				}

				if ( $source_field->inputType == 'likert' && $source_field->gsurveyLikertEnableMultipleRows ) {
					list( $row_value, $field_value ) = rgexplode( ':', $field_value, 2 );
				}

				return $this->get_source_choice_text( $field_value, $source_field );
			}

			return $field_value;
		}

		/**
		 * Get the value to be set for the target field.
		 *
		 * Returns the target fields choice unique value instead of the source field choice text for choice based poll, quiz and survey fields.
		 *
		 * @param string $field_value The source field value.
		 * @param GF_Field $target_field The target field being processed.
		 * @param string $target_field_id The ID of the target field or input.
		 *
		 * @return string
		 */
		public function get_target_field_value( $field_value, $target_field, $target_field_id ) {
			if ( is_object( $target_field ) && in_array( $target_field->type, array( 'poll', 'quiz', 'survey' ) ) ) {
				if ( $target_field->inputType == 'rank' ) {
					$values = explode( ',', $field_value );
					foreach ( $values as &$value ) {
						$value = $this->get_target_choice_value( $value, $target_field );
					}

					return implode( ',', $values );
				}

				$field_value = $this->get_target_choice_value( $field_value, $target_field );

				if ( $target_field->inputType == 'likert' && $target_field->gsurveyLikertEnableMultipleRows ) {
					$row_value   = $target_field->get_row_id( $target_field_id );
					$field_value = sprintf( '%s:%s', $row_value, $field_value );
				}
			} elseif ( is_object( $target_field ) && $target_field->type == 'multiselect' && ! empty( $field_value ) && ! is_array( $field_value ) ) {
				// Convert the comma-delimited string into an array.
				$field_value = json_decode( $field_value );
			} elseif ( is_object( $target_field ) && $target_field->type == 'email' && ! empty( $field_value ) && ! is_array( $field_value ) ) {
				//@todo temporarily set email value to array even it doesn't support confirmation
				$field_value = array( $field_value, $field_value );
			}

			return $field_value;
		}

		/**
		 * Gets the choice text for the supplied choice value.
		 *
		 * @param string $selected_choice The choice value from the source field.
		 * @param GF_Field $source_field The source field being processed.
		 *
		 * @return string
		 */
		public function get_source_choice_text( $selected_choice, $source_field ) {
			return $this->get_choice_property( $selected_choice, $source_field->choices, 'value', 'text' );
		}

		/**
		 * Gets the choice value for the supplied choice text.
		 *
		 * @param string $selected_choice The choice text from the source field.
		 * @param GF_Field $target_field The target field being processed.
		 *
		 * @return string
		 */
		public function get_target_choice_value( $selected_choice, $target_field ) {
			return $this->get_choice_property( $selected_choice, $target_field->choices, 'text', 'value' );
		}

		/**
		 * Helper to get the specified choice property for the selected choice.
		 *
		 * @param string $selected_choice The selected choice value or text.
		 * @param array $choices The field choices.
		 * @param string $compare_property The choice property the $selected_choice is to be compared against.
		 * @param string $return_property The choice property to be returned.
		 *
		 * @return string
		 */
		public function get_choice_property( $selected_choice, $choices, $compare_property, $return_property ) {
			if ( $selected_choice && is_array( $choices ) ) {
				foreach ( $choices as $choice ) {
					if ( $choice[ $compare_property ] == $selected_choice ) {
						return $choice[ $return_property ];
					}
				}
			}

			return $selected_choice;
		}

		public function validate_date_created( $dateStr ) {
			if ( preg_match( '/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/', $dateStr ) > 0 ) {
				return true;
			} else {
				return false;
			}
		}
	}

}
