<?php
namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class Group_Control_Base implements Group_Control_Interface {

	private $args = [];

	/**
	 * @since 1.0.0
	 * @access public
	*/
	final public function add_controls( Controls_Stack $element, array $user_args, array $options = [] ) {
		$this->init_args( $user_args );

		// Filter witch controls to display
		$filtered_fields = $this->filter_fields();

		$filtered_fields = $this->prepare_fields( $filtered_fields );

		// For php < 7
		reset( $filtered_fields );

		if ( isset( $this->args['separator'] ) ) {
			$filtered_fields[ key( $filtered_fields ) ]['separator'] = $this->args['separator'];
		}

		$has_injection = false;

		if ( ! empty( $options['position'] ) ) {
			$has_injection = true;

			$element->start_injection( $options['position'] );

			unset( $options['position'] );
		}

		foreach ( $filtered_fields as $field_id => $field_args ) {
			// Add the global group args to the control
			$field_args = $this->add_group_args_to_field( $field_id, $field_args );

			// Register the control
			$id = $this->get_controls_prefix() . $field_id;

			if ( ! empty( $field_args['responsive'] ) ) {
				unset( $field_args['responsive'] );

				$element->add_responsive_control( $id, $field_args, $options );
			} else {
				$element->add_control( $id , $field_args, $options );
			}
		}

		if ( $has_injection ) {
			$element->end_injection();
		}
	}

	/**
	 * @since 1.0.0
	 * @access public
	*/
	final public function get_args() {
		return $this->args;
	}

	/**
	 * @since 1.2.2
	 * @access public
	*/
	final public function get_fields() {
		// TODO: Temp - compatibility for posts group
		if ( method_exists( $this, '_get_controls' ) ) {
			return $this->_get_controls( $this->get_args() );
		}

		if ( null === static::$fields ) {
			static::$fields = $this->init_fields();
		}

		return static::$fields;
	}

	/**
	 * @since 1.0.0
	 * @access public
	*/
	public function get_controls_prefix() {
		return $this->args['name'] . '_';
	}

	/**
	 * @since 1.0.0
	 * @access public
	*/
	public function get_base_group_classes() {
		return 'elementor-group-control-' . static::get_type() . ' elementor-group-control';
	}

	// TODO: Temp - Make it abstract
	/**
	 * @since 1.2.2
	 * @access protected
	*/
	protected function init_fields() {}

	/**
	 * @since 1.2.2
	 * @access protected
	*/
	protected function get_child_default_args() {
		return [];
	}

	/**
	 * @since 1.2.2
	 * @access protected
	*/
	protected function filter_fields() {
		$args = $this->get_args();

		$fields = $this->get_fields();

		if ( ! empty( $args['include'] ) ) {
			$fields = array_intersect_key( $fields, array_flip( $args['include'] ) );
		}

		if ( ! empty( $args['exclude'] ) ) {
			$fields = array_diff_key( $fields, array_flip( $args['exclude'] ) );
		}

		foreach ( $fields as $field_key => $field ) {
			if ( empty( $field['condition'] ) ) {
				continue;
			}

			foreach ( $field['condition'] as $condition_key => $condition_value ) {
				preg_match( '/^\w+/', $condition_key, $matches );

				if ( empty( $fields[ $matches[0] ] ) ) {
					unset( $fields[ $field_key ] );

					continue 2;
				}
			}
		}

		return $fields;
	}

	/**
	 * @since 1.2.2
	 * @access protected
	*/
	protected function add_group_args_to_field( $control_id, $field_args ) {
		$args = $this->get_args();

		if ( ! empty( $args['tab'] ) ) {
			$field_args['tab'] = $args['tab'];
		}

		if ( ! empty( $args['section'] ) ) {
			$field_args['section'] = $args['section'];
		}

		$field_args['classes'] = $this->get_base_group_classes() . ' elementor-group-control-' . $control_id;

		if ( ! empty( $args['condition'] ) ) {
			if ( empty( $field_args['condition'] ) ) {
				$field_args['condition'] = [];
			}

			$field_args['condition'] += $args['condition'];
		}

		return $field_args;
	}

	/**
	 * @since 1.2.2
	 * @access protected
	*/
	protected function prepare_fields( $fields ) {
		foreach ( $fields as $field_key => &$field ) {
			if ( ! empty( $field['condition'] ) ) {
				$field = $this->add_conditions_prefix( $field );
			}

			if ( ! empty( $field['selectors'] ) ) {
				$field['selectors'] = $this->handle_selectors( $field['selectors'] );
			}

			if ( isset( $this->args['fields_options']['__all'] ) ) {
				$field = array_merge( $field, $this->args['fields_options']['__all'] );
			}

			if ( isset( $this->args['fields_options'][ $field_key ] ) ) {
				$field = array_merge( $field, $this->args['fields_options'][ $field_key ] );
			}
		}

		return $fields;
	}

	/**
	 * @since 1.2.2
	 * @access private
	*/
	private function init_args( $args ) {
		$this->args = array_merge( $this->get_default_args(), $this->get_child_default_args(), $args );
	}

	/**
	 * @since 1.2.2
	 * @access private
	*/
	private function get_default_args() {
		return [
			'default' => '',
			'selector' => '{{WRAPPER}}',
			'fields_options' => [],
		];
	}

	/**
	 * @since 1.2.0
	 * @access private
	*/
	private function add_conditions_prefix( $field ) {
		$controls_prefix = $this->get_controls_prefix();

		$prefixed_condition_keys = array_map(
			function( $key ) use ( $controls_prefix ) {
				return $controls_prefix . $key;
			},
			array_keys( $field['condition'] )
		);

		$field['condition'] = array_combine(
			$prefixed_condition_keys,
			$field['condition']
		);

		return $field;
	}

	/**
	 * @since 1.2.2
	 * @access private
	*/
	private function handle_selectors( $selectors ) {
		$args = $this->get_args();

		$selectors = array_combine(
			array_map(
				function( $key ) use ( $args ) {
						return str_replace( '{{SELECTOR}}', $args['selector'], $key );
				}, array_keys( $selectors )
			),
			$selectors
		);

		if ( ! $selectors ) {
			return $selectors;
		}

		$controls_prefix = $this->get_controls_prefix();

		foreach ( $selectors as &$selector ) {
			$selector = preg_replace_callback(
				'/(?:\{\{)\K[^.}]+(?=\.[^}]*}})/', function( $matches ) use ( $controls_prefix ) {
					return $controls_prefix . $matches[0];
				}, $selector
			);
		}

		return $selectors;
	}
}
