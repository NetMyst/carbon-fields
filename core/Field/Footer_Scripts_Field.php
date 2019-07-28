<?php

namespace Carbon_Fields\Field;

/**
 * Footer scripts field class.
 * Intended only for use in theme options container.
 */
class Footer_Scripts_Field extends Scripts_Field {

	/**
	 * {@inheritDoc}
	 */
	protected $hook_name = 'wp_footer';
	
	/**
	 * For print after add_action( 'wp_footer', 'wp_print_footer_scripts', 20 ) and before the closing </body>
	 */
	protected $hook_priority = 1000;

	/**
	 * {@inheritDoc}
	 */
	protected function get_default_help_text() {
		return __( 'If you need to add scripts to your footer (like Google Analytics tracking code), you should enter them in this box.', 'carbon-fields' );
	}
}
