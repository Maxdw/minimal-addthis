<?php
/**
 * Plugin Name: Minimal AddThis
 * Description: The minimum code required to allow you to run AddThis widgets on your pages. When enabled and configured it will add the AddThis script on every page. Can be configured under 'Settings'.
 * Version: 1.0.0
 * Requires at least: 4.2
 * Author: Dutchwise
 * Author URI: http://www.dutchwise.nl/
 * Text Domain: minaddt
 * Domain Path: /locale/
 * Network: true
 * License: MIT license (http://www.opensource.org/licenses/mit-license.php)
 */

include 'html.php';

class MinimalAddThis {
	
	/**
	 * Sanitizes AddThis settings before saving.
	 *
	 * [enabled] => 0
	 * [tracking_id] => RA-XXXXX
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitizeAddThisSettings(array $input) {
		foreach($input as $key => &$value) {
			$value = stripslashes(strip_tags($value));
		}
		
		$field = 'enabled';
		
		if(array_key_exists($field, $input)) {
			$input[$field] = (int)!!$input[$field];
		}
		
		return apply_filters('minaddt_sanitize_addthis_options', $input);
	}
	
	/**
	 * Renders the AddThis admin settings page.
	 *
	 * @return void
	 */
	public function renderAdminSettingsPage() {
		$html = new HtmlHelper(false);
		
		echo $html->open('div', array('class' => 'wrap'));
		
		// start form
		echo $html->open('form', array(
			'action' => 'options.php',
			'method' => 'POST',
			'accept-charset' => get_bloginfo('charset'),
			'novalidate'
		));
		
		// form title
		echo $html->element('h2', __('AddThis', 'minaddt'));
		
		echo $html->single('br');
		
		// prepare form for settings (nonce, referer fields)
		settings_fields('addthis');
		
		// renders all settings sections of the specified page
		do_settings_sections('addthis');
		
		// renders the submit button
		submit_button();
		
		echo $html->close();
	}
	
	/**
	 * Renders the AddThis admin settings section.
	 *
	 * @param array $args 'id', 'title', 'callback'
	 * @return void
	 */
	public function renderAdminAddThisSettingsSection($args) {
		// do nothing
	}
	
	/**
	 * Renders the AddThis admin settings fields.
	 *
	 * @param array $args Unknown
	 * @return void
	 */
	public function renderAdminAddThisSettingField($args) {
		$options = get_option('addthis_settings');
		$html = new HtmlHelper();
		$atts = array();
		
		// if option does not exist, add to database
		if($options == '') {
			add_option('addthis_settings', array());
		}
		
		// make sure the required label_for and field arguments are present to render correctly
		if(!isset($args['label_for'], $args['field'])) {
			throw new InvalidArgumentException('add_settings_field incorrectly configured');
		}
		
		// define attributes each field should have
		$atts['id'] = $args['label_for'];
		$atts['name'] = "addthis_settings[{$args['field']}]";
		
		// render html based on which field needs to be rendered
		switch($args['field']) {
			case 'enabled':
				$atts['type'] = 'checkbox';
				$atts['value'] = '1';
				
				$html->single('input', array(
					'id' => $atts['id'] . '_hidden',
					'type' => 'hidden',
					'value' => 0
				) + $atts);
				
				if(isset($options[$args['field']]) && $options[$args['field']]) {
					$atts['checked'] = 'checked';
				}
				
				$html->single('input', $atts);				
				break;
			case 'id':
				$atts['type'] = 'text';
				$atts['placeholder'] = 'RA-XXXXXX...';
				
				if(isset($options[$args['field']])) {
					$atts['value'] = $options[$args['field']];
				}
				
				$html->single('input', $atts);				
				break;
		}
		
		$html->close();
		
		echo $html;
	}
	
	/**
	 * Runs when the WordPress admin area is initialised.
	 *
	 * @return void
	 */
	public function onAdminInit() {
		register_setting('addthis', 'addthis_settings', array($this, 'sanitizeAddThisSettings'));
		
		add_settings_section(
			'addthis_section',				// ID used to identify this section and with which to register options
			__( 'Settings', 'minaddt' ),		// Title to be displayed on the administration page
			array($this, 'renderAdminAddThisSettingsSection'),
			'addthis'						// Page on which to add this section of options
		);
		
		// field names and labels
		$fields = array(
			'enabled' => __('Enable AddThis', 'minaddt'),
			'id' => __('AddThis ID', 'minaddt'),
		);
		
		// register and render the fields using add_settings_field and the $fields array
		foreach($fields as $field => $label) {
			add_settings_field(
				"addthis_settings[{$field}]",	// ID used to identify the field throughout the theme
				$label,							// The label to the left of the option interface element
				array($this, 'renderAdminAddThisSettingField'),
				'addthis',						// The page on which this option will be displayed
				'addthis_section',				// The name of the section to which this field belongs
				array(							// The array of arguments to pass to the callback.
					'field' => $field,
					'label_for' => $field
				)
			);
		}
	}
	
	/**
	 * Runs when the WordPress admin menus are initialised.
	 *
	 * @return void
	 */
	public function onAdminMenu() {
		// adds the email menu item to WordPress's main Settings menu
		add_options_page(
			__('AddThis Settings', 'minaddt'),
			__('AddThis', 'minaddt'),
			'manage_options', 
			'addthis',
			array($this, 'renderAdminSettingsPage')
		);
	}
	
	/**
	 * Renders the AddThis script.
	 *
	 * @return void
	 */
	public function renderAddThisScript() {
		$options = get_option('addthis_settings');
		
		// check if AddThis is enabled
		if(empty($options['enabled']) || empty($options['id'])) {
			return;
		}
		
		// create identifier for the script
		$id = 'addthis-script';
		
		// check if script was already added
		if(wp_script_is($id, 'done')) {
			return;
		}		
		
		// access global scripts (to mark the script as done later on)
		global $wp_scripts;
		
		$html = new HtmlHelper();
		$html->element('script', array(
			'type' => 'text/javascript',
			'async' => 'async',
			'src' => '//s7.addthis.com/js/300/addthis_widget.js#pubid=' . $options['id']
		));
		
		// render script
		echo $html->render();
		
		// mark the script as added
		$wp_scripts->done[] = $id;		
	}
	
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action('admin_menu', array($this, 'onAdminMenu'));
		add_action('admin_init', array($this, 'onAdminInit'));
		add_action('wp_head', array($this, 'renderAddThisScript'));
	}
	
}

new MinimalAddThis;