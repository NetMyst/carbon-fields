<?php

namespace Carbon_Fields\Field;

use Carbon_Fields\Datastore\Datastore_Interface;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;

/**
 * Base field class.
 * Defines the key container methods and their default implementations.
 * Implements factory design pattern
 *
 **/
class Field {
	/**
	 * Stores all the field backbone templates
	 *
	 * @see factory()
	 * @see add_template()
	 * @var array
	 */
	protected $templates = array();

	/**
	 * Globally unique field identificator. Generated randomly
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * Stores the initial <kbd>$type</kbd> variable passed to the <code>factory()</code> method
	 *
	 * @see factory
	 * @var string
	 */
	public $type;

	/**
	 * Field value
	 *
	 * @var mixed
	 */
	protected $value;

	/**
	 * Default field value
	 *
	 * @var mixed
	 */
	protected $default_value;

	/**
	 * Sanitized field name used as input name attribute during field render
	 *
	 * @see factory()
	 * @see set_name()
	 * @var string
	 */
	protected $name;

	/**
	 * The base field name which is used in the container.
	 *
	 * @see set_base_name()
	 * @var string
	 */
	protected $base_name;

	/**
	 * Field name used as label during field render
	 *
	 * @see factory()
	 * @see set_label()
	 * @var string
	 */
	protected $label;

	/**
	 * Additional text containing information and guidance for the user
	 *
	 * @see help_text()
	 * @var string
	 */
	protected $help_text;

	/**
	 * Field DataStore instance to which save, load and delete calls are delegated
	 *
	 * @see set_datastore()
	 * @see get_datastore()
	 * @var Carbon_DataStore
	 */
	protected $store;

	/**
	 * The type of the container this field is in
	 *
	 * @see get_context()
	 * @var string
	 */
	protected $context;

	/**
	 * Whether or not this value should be auto loaded. Applicable to theme options only.
	 *
	 * @see set_autoload()
	 * @var bool
	 **/
	protected $autoload = false;

	/**
	 * Whether or not this field will be initialized when the field is in the viewport (visible).
	 * 
	 * @see set_lazyload()
	 * @var bool
	 **/
	protected $lazyload = false;

	/**
	 * The width of the field.
	 * 
	 * @see set_width()
	 * @var int
	 **/
	protected $width = 0;

	/**
	 * Custom CSS classes.
	 * 
	 * @see add_class()
	 * @var array
	 **/
	protected $classes = array();

	/**
	 * Whether or not this field is required.
	 *
	 * @see set_required()
	 * @var bool
	 **/
	protected $required = false;

	/**
	 * Prefix to be pretended to the field name during load, save, delete and <strong>render</strong>
	 *
	 * @var string
	 **/
	protected $name_prefix = '_';

	/**
	 * Stores the field conditional logic rules.
	 *
	 * @var array
	 **/
	protected $conditional_logic = array();

	/**
	 * Stores the field options (if any)
	 *
	 * @var string
	 **/
	protected $options = array();

	/**
	 * Create a new field of type $type and name $name and label $label.
	 *
	 * @param string $type
	 * @param string $name lower case and underscore-delimited
	 * @param string $label (optional) Automatically generated from $name if not present
	 * @return object $field
	 **/
	static function factory($type, $name, $label=null) {
		$type = str_replace(" ", '_', ucwords(str_replace("_", ' ', $type)));

		$class = __NAMESPACE__ . "\\" . $type . '_Field';

		if (!class_exists($class)) {
			throw new Incorrect_Syntax_Exception ('Unknown field "' . $type . '".');
		}

		if ( strpos($name, '-') !== false ) {
			throw new Incorrect_Syntax_Exception ('Forbidden character "-" in name "' . $name . '".');
		}

		$field = new $class($name, $label);
		$field->type = $type;
		$field->add_template($field->get_type(), array($field, 'template'));

		return $field;
	}

	private function __construct($name, $label) {
		$this->set_name($name);
		$this->set_label($label);
		$this->set_base_name($name);

		// Pick random ID
		$random_string = md5(mt_rand() . $this->get_name() . $this->get_label());
		$random_string = substr($random_string, 0, 5); // 5 chars should be enough
		$this->id = 'carbon-' . $random_string;

		$this->init();
		if (is_admin()) {
			$this->admin_init();
		}

		add_action('admin_print_scripts', array($this, 'admin_hook_scripts'));
		add_action('admin_print_styles', array($this, 'admin_hook_styles'));
	}

	/**
	 * Perform instance initialization after calling setup()
	 *
	 * @return void
	 **/
	function init() {}

	/**
	 * Instance initialization when in the admin area. Called during object construction
	 *
	 * @return void
	 **/
	function admin_init() {}

	/**
	 * Enqueue admin scripts.
	 * Called once per field type.
	 *
	 * @return void
	 **/
	function admin_enqueue_scripts() {}

	/**
	 * Prints the main Underscore template
	 *
	 * @return void
	 **/
	function template() { }

	/**
	 * Returns all the backbone templates
	 *
	 * @return array
	 **/
	function get_templates() {
		return $this->templates;
	}

	/**
	 * Adds a new backbone template
	 *
	 * @return void
	 **/
	function add_template($name, $callback) {
		$this->templates[$name] = $callback;
	}

	/**
	 * Delegate load to the field DataStore instance
	 *
	 * @return void
	 **/
	function load() {
		$this->store->load($this);

		if ( $this->get_value() === false ) {
			$this->set_value( $this->default_value );
		}
	}

	/**
	 * Delegate save to the field DataStore instance
	 *
	 * @return void
	 **/
	function save() {
		return $this->store->save($this);
	}

	/**
	 * Delegate delete to the field DataStore instance
	 *
	 * @return void
	 **/
	function delete() {
		return $this->store->delete($this);
	}

	/**
	 * Load the field value from an input array based on it's name
	 *
	 * @param array $input (optional) Array of field names and values. Defaults to $_POST
	 * @return void
	 **/
	function set_value_from_input($input = null) {
		if ( is_null($input) ) {
			$input = $_POST;
		}

		if ( !isset($input[$this->name]) ) {
			$this->set_value(null);
		} else {
			$this->set_value( stripslashes_deep($input[$this->name]) );
		}
	}

	/**
	 * Assign DataStore instance for use during load, save and delete
	 *
	 * @param object $store
	 * @return object $this
	 **/
	function set_datastore(Datastore_Interface $store) {
		$this->store = $store;
		return $this;
	}

	/**
	 * Return the DataStore instance used by the field
	 *
	 * @return object $store
	 **/
	function get_datastore() {
		return $this->store;
	}

	/**
	 * Assign the type of the container this field is in
	 *
	 * @param string
	 * @return object $this
	 **/
	function set_context($context) {
		$this->context = $context;
		return $this;
	}

	/**
	 * Return the type of the container this field is in
	 *
	 * @return string
	 **/
	function get_context() {
		return $this->context;
	}

	/**
	 * Directly modify the field value
	 *
	 * @param mixed $value
	 * @return void
	 **/
	function set_value($value) {
		$this->value = $value;
	}

	/**
	 * Set default field value
	 *
	 * @param mixed $value
	 * @return void
	 **/
	function set_default_value($default_value) {
		$this->default_value = $default_value;
		return $this;
	}

	/**
	 * Get default field value
	 *
	 * @return mixed
	 **/
	function get_default_value() {
		return $this->default_value;
	}

	/**
	 * Return the field value
	 *
	 * @return mixed
	 **/
	function get_value() {
		return $this->value;
	}

	/**
	 * Set field name.
	 * Use only if you are completely aware of what you are doing.
	 *
	 * @param string $name Field name, either sanitized or not
	 **/
	function set_name($name) {
		$name = preg_replace('~\s+~', '_', strtolower($name));

		if ( $this->name_prefix && strpos($name, $this->name_prefix) !== 0 ) {
			$name = $this->name_prefix . $name;
		}

		$this->name = $name;
	}

	/**
	 * Return the field name
	 *
	 * @return string
	 **/
	function get_name() {
		return $this->name;
	}

	/**
	 * Set field base name as defined in the container.
	 **/
	function set_base_name($name) {
		$this->base_name = $name;
	}

	/**
	 * Return the field base name.
	 *
	 * @return string
	 **/
	function get_base_name() {
		return $this->base_name;
	}

	/**
	 * Set field name prefix. Calling this method will update the current field name and the conditional logic fields.
	 *
	 * @param string $prefix
	 * @return object $this
	 **/
	function set_prefix($prefix) {
		$this->name = preg_replace('~^' . preg_quote($this->name_prefix, '~') . '~', '', $this->name);
		$this->name_prefix = $prefix;
		$this->name = $this->name_prefix . $this->name;

		return $this;
	}

	/**
	 * Set field label.
	 *
	 * @param string $label If null, the label will be generated from the field name
	 * @return void
	 **/
	function set_label($label) {
		// Try to guess field label from it's name
		if (is_null($label)) {
			// remove the leading underscore(if it's there)
			$label = preg_replace('~^_~', '', $this->name);

			// remove the leading "crb_"(if it's there)
			$label = preg_replace('~^crb_~', '', $label);

			// split the name into words and make them capitalized
			$label = ucwords(str_replace('_', ' ', $label));
		}

		$this->label = $label;
	}

	function get_label() {
		return $this->label;
	}

	/**
	 * Set additional text to be displayed during field render,
	 * containing information and guidance for the user
	 *
	 * @return object $this
	 **/
	function set_help_text($help_text) {
		$this->help_text = $help_text;
		return $this;
	}

	/**
	 * Alias for set_help_text()
	 *
	 * @see set_help_text()
	 * @return object $this
	 **/
	function help_text($help_text) {
		return $this->set_help_text($help_text);
	}

	/**
	 * Return the field help text
	 *
	 * @return object $this
	 **/
	function get_help_text() {
		return $this->help_text;
	}

	/**
	 * Whether or not this value should be auto loaded. Applicable to theme options only.
	 *
	 * @param bool $autoload
	 * @return object $this
	 **/
	function set_autoload($autoload) {
		$this->autoload = $autoload;
		return $this;
	}

	/**
	 * Return whether or not this value should be auto loaded.
	 *
	 * @return bool
	 **/
	function get_autoload() {
		return $this->autoload;
	}

	/**
	 * Whether or not this field will be initialized when the field is in the viewport (visible).
	 * 
	 * @param bool $autoload
	 * @return object $this
	 **/
	function set_lazyload($lazyload) {
		$this->lazyload = $lazyload;
		return $this;
	}

	/**
	 * Return whether or not this field should be lazyloaded.
	 * 
	 * @return bool
	 **/
	function get_lazyload() {
		return $this->lazyload;
	}

	/**
	 * Set the field width.
	 * 
	 * @param int $width
	 * @return object $this
	 **/
	function set_width($width) {
		$this->width = (int) $width;
		return $this;
	}

	/**
	 * Get the field width.
	 * 
	 * @return int $width
	 **/
	function get_width() {
		return $this->width;
	}

	/**
	 *  Add custom CSS class to the field html container.
	 * 
	 * @param string|array $classes
	 * @return object $this
	 **/
	function add_class($classes) {
		if (!is_array($classes)) {
			$classes = array_values( array_filter( explode(' ', $classes) ) );
		}

		$this->classes = array_map('sanitize_html_class', $classes);
		return $this;
	}

	/**
	 * Get the field custom CSS classes.
	 * 
	 * @return array
	 **/
	function get_classes() {
		return $this->classes;
	}

	/**
	 * Whether this field is mandatory for the user
	 *
	 * @param bool $required
	 * @return object $this
	 **/
	function set_required($required) {
		$this->required = $required;
		return $this;
	}

	/**
	 * HTML id attribute getter.
	 * @return string
	 */
	function get_id() {
		return $this->id;
	}

	/**
	 * HTML id attribute setter
	 * @param string $id
	 */
	function set_id($id) {
		$this->id = $id;
	}

	/**
	 * Return whether this field is mandatory for the user
	 *
	 * @return bool
	 **/
	function is_required() {
		return $this->required;
	}

	/**
	 * Returns the type of the field based on the class
	 * The class is stripped by the "Carbon_Field" prefix. Then underscores are replaced with a dash.
	 * Finally the result is lowercased.
	 *
	 * @return string
	 */
	public function get_type() {
		$class = get_class($this);

		return $this->clean_type($class);
	}

	/**
	 * Cleans up an object class for usage as HTML class
	 *
	 * @return string
	 */
	protected function clean_type($type) {
		$remove = array(
			'_',
			'\\',
			'CarbonFields',
			'Field',
		);
		$clean_class = str_replace($remove, '', $type);

		return $clean_class;
	}

	/**
	 * Return an array of html classes to be used for the field container
	 *
	 * @return array
	 */
	public function get_html_class() {
		$html_classes = array();

		$object_class = get_class($this);
		$html_classes[] = $this->get_type();

		$parent_class = $object_class;
		while ($parent_class = get_parent_class($parent_class)) {
			$clean_class = $this->clean_type($parent_class);

			if ($clean_class) {
				$html_classes[] = $clean_class;
			}
		}

		return $html_classes;
	}

	/**
	 * Allows the value of a field to be processed after loading.
	 * Can be implemented by the extending class if necessary.
	 * 
	 * @return array
	 */
	public function process_value() {

	}

	/**
	 * Returns an array that holds the field data, suitable for JSON representation.
	 * This data will be available in the Underscore template and the Backbone Model.
	 * 
	 * @param bool $load  Should the value be loaded from the database or use the value from the current instance.
	 * @return array
	 */
	public function to_json($load) {
		if ($load) {
			$this->load();
		}

		$this->process_value();

		$field_data = array(
			'id' => $this->get_id(),
			'type' => $this->get_type(),
			'label' => $this->get_label(),
			'name' => $this->get_name(),
			'base_name' => $this->get_base_name(),
			'value' => $this->get_value(),
			'default_value' => $this->get_default_value(),
			'help_text' => $this->get_help_text(),
			'context' => $this->get_context(),
			'required' => $this->is_required(),
			'lazyload' => $this->get_lazyload(),
			'width' => $this->get_width(),
			'classes' => $this->get_classes(),
			'conditional_logic' => $this->get_conditional_logic(),
		);

		return $field_data;
	}

	/**
	 * Set the field visibility conditional logic.
	 *
	 * @param array
	 */
	public function set_conditional_logic($rules) {
		$this->conditional_logic = $this->parse_conditional_rules($rules);

		return $this;
	}

	/**
	 * Get the conditional logic rules
	 *
	 * @return array
	 */
	protected function get_conditional_logic() {
		return $this->conditional_logic;
	}

	/**
	 * Validate and parse the conditional logic rules.
	 *
	 * @param array $rules
	 * @return array
	 */
	protected function parse_conditional_rules($rules) {
		if ( ! is_array( $rules ) ) {
			throw new Incorrect_Syntax_Exception('Conditional logic rules argument should be an array.');
		}

		$parsed_rules = array(
			'relation' => 'AND',
			'rules' => array(),
		);

		$allowed_operators = array('=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN');

		foreach ( $rules as $key => $rule ) {
			// Check if we have a relation key
			if ( $key === 'relation' ) {
				if ($rule === 'OR') {
					$parsed_rules['relation'] = $rule;
				}
				continue;
			}

			// Check if the rule is valid
			if ( ! is_array($rule) || empty( $rule['field'] ) ) {
				throw new Incorrect_Syntax_Exception('Invalid conditional logic rule format. The rule should be an array with the "field" key set.');
			}

			// Check the compare oparator
			if ( empty( $rule['compare'] ) ) {
				$rule['compare'] = '=';
			}
			if ( ! in_array( $rule['compare'], $allowed_operators ) ) {
				throw new Incorrect_Syntax_Exception('Invalid conditional logic compare oparator: <code>' . $rule['compare'] . '</code><br>' . 
					'Allowed oparators are: <code>' . implode(', ', $allowed_operators) . '</code>');
			}
			if ( $rule['compare'] === 'IN' || $rule['compare'] === 'NOT IN' ) {
				if ( ! is_array( $rule['value'] ) ) {
					throw new Incorrect_Syntax_Exception('Invalid conditional logic value format. An array is expected, when using the "' . $rule['compare'] . '" operator.');
				}
			}

			// Check the value
			if ( ! isset( $rule['value'] ) ) {
				$rule['value'] = '';
			}

			$parsed_rules['rules'][] = $rule;
		}

		return $parsed_rules;
	}

	/**
	 * Set the field options
	 * Callbacks are supported
	 *
	 * @param array|callback $options
	 * @return void
	 */
	protected function _set_options($options) {
		$this->options = (array) $options;
	}

	/**
	 * Add options to the field
	 * Callbacks are supported
	 *
	 * @param array|callback $options
	 * @return void
	 */
	protected function _add_options($options) {
		$this->options[] = $options;
	}

	/**
	 * Check if there are callbacks and populate the options
	 *
	 * @return void
	 */
	protected function load_options() {
		if (empty($this->options)) {
			return false;
		}

		$options = array();
		foreach ($this->options as $key => $value) {
			if (is_callable($value)) {
				$options = $options + (array) call_user_func($value);
			} else if (is_array($value)) {
				$options = $options + $value;
			} else {
				$options[$key] = $value;
			}
		}

		$this->options = $options;
	}

	/**
	 * Changes the options array structure. This is needed to keep the array items order when it is JSON encoded.
	 *
	 * @param array $options
	 * @return array
	 */
	public function parse_options($options) {
		$parsed = array();

		foreach ($options as $key => $value) {
			$parsed[] = array(
				'name' => $value,
				'value' => $key,
			);
		}

		return $parsed;
	}

	function admin_hook_scripts() {
		wp_enqueue_media();
		wp_enqueue_script('carbon-fields', CARBON_PLUGIN_URL . '/js/fields.js', array('carbon-app', 'carbon-containers'));
		wp_localize_script('carbon-fields', 'crbl10n',
			array(
				'title' => __('Files', 'crb'),
				'geocode_zero_results' => __('The address could not be found. ', 'crb'),
				'geocode_not_successful' => __('Geocode was not successful for the following reason: ', 'crb'),
				'max_num_items_reached' => __('Maximum number of items reached (%s items)', 'crb'),
				'max_num_rows_reached' => __('Maximum number of rows reached (%s rows)', 'crb'),
				'cannot_create_more_rows' => __('Cannot create more than %s rows', 'crb'),
				'enter_name_of_new_sidebar' => __('Please enter the name of the new sidebar:', 'crb'),
				'remove_sidebar_confirmation' => __('Are you sure you wish to remove this sidebar?', 'crb'),
				'add_sidebar' => __('Add Sidebar', 'crb'),
				'complex_no_rows' => __('There are no %s yet. Click <a href="#">here</a> to add one.', 'crb'),
				'complex_add_button' => __('Add %s', 'crb'),
				'complex_min_num_rows_not_reached' => __('Minimum number of rows not reached (%d %s)', 'crb'),

				'message_form_validation_failed' => __('Please fill out all fields correctly. ', 'crb'),
				'message_required_field' => __("This field is required. ", 'crb'),
				'message_choose_option' => __("Please choose an option. ", 'crb'),
			)
		);
	}

	function admin_hook_styles() {
		wp_enqueue_style('thickbox');
	}
} // END Field