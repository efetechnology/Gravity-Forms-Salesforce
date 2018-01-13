<?php
//

//
GFForms::include_feed_addon_framework();

class GFSalesForceAddOn extends GFFeedAddOn {

	protected $_version = GF_SALESFORCE_ADDON_VERSION;
	protected $_min_gravityforms_version = '1.9.16';
	protected $_slug = 'gravity-forms-salesforce-add-on';
	protected $_path = 'gravity-form-salesforce-addon/gravity-forms-salesforce-add-on.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Salesforce Add-On';
	protected $_short_title = 'Gravity Forms Salesforce';

	static $_instance = null;
	private $check;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFgravity-forms-salesforce-add-on
	 */
	static function get_instance() {
		if (self::$_instance == null) {
			self::$_instance = new GFSalesForceAddOn();
		}

		return self::$_instance;
	}

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__('Subscribe contact to service x only when payment is received.', 'gravity-forms-salesforce-add-on'),
			)
		);

	}

	// # FEED PROCESSING -----------------------------------------------------------------------------------------------
	protected function get_merge_vars_from_entry($feed, $entry, $form) {

		$this->log_debug('All Feed Meta: ' . print_r($feed["meta"], true));

		$merge_vars = array();
		foreach ($feed["meta"] as $var_tag => $field_id) {

			if (empty($field_id) || strpos($var_tag, 'feed_condition') !== false) {
				$this->log_debug('[get_merge_vars_from_entry]: Feed field not defined for field ID ' . $var_tag);
				continue;
			}

			$var_tag = str_replace('field_map__', '', $var_tag);

			if (!is_numeric($field_id)) {

				$value = GFCommon::replace_variables('{' . $field_id . '}', $form, $entry, false, false, false);

			} else {

				$field = RGFormsModel::get_field($form, $field_id);

				$value = RGFormsModel::get_lead_field_value($entry, $field);

			}

			// If the value is multi-part, like a name or address, there will be an array
			// returned. In that case, we check the array for the key of the field id.
			if (is_array($value)) {

				if (array_key_exists($field_id, $value)) {
					$merge_vars[$var_tag] = $value[$field_id];
				}

				// The key wasn't mapped. Keep going.
				continue;

			} else {

				// The value can be an array, serialized.
				$value = maybe_unserialize($value);

				$merge_vars[$var_tag] = $value;

			}

		}

		return $merge_vars;
	}

	/**
	 * Custom format the phone type field values before they are returned by $this->get_field_value().
	 *
	 * @param array $entry The Entry currently being processed.
	 * @param string $field_id The ID of the Field currently being processed.
	 * @param GF_Field_Phone $field The Field currently being processed.
	 *
	 * @return string
	 */
	function get_phone_field_value($entry, $field_id, $field) {

		// Get the field value from the Entry Object.
		$field_value = rgar($entry, $field_id);

		// If there is a value and the field phoneFormat setting is set to standard reformat the value.
		if (!empty($field_value) && $field->phoneFormat == 'standard' && preg_match('/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches)) {
			$field_value = sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]);
		}

		return $field_value;
	}




	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Creates a custom page for this add-on.
	 */
	

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	function plugin_settings_fields() {
		return array(
			array(
				'title' => esc_html__('Lead Settings', 'gravity-forms-salesforce-add-on'),
				'fields' => array(
					array(
						'name' => 'org_id',
						'tooltip' => esc_html__('TSalesforce Org. ID', 'gravity-forms-salesforce-add-on'),
						'label' => esc_html__('TSalesforce Org. ID ', 'gravity-forms-salesforce-add-on'),
						'type' => 'text',
						'class' => 'small',
					),
					
				),
			),
			array(
				'title' => esc_html__('Contact Settings', 'gravity-forms-salesforce-add-on'),
				'fields' => array(
					array(
						'label' => esc_html__('User name', 'gravity-forms-salesforce-add-on'),
						'type' => 'text',
						'name' => 'username',
						'tooltip' => esc_html__('User name', 'gravity-forms-salesforce-add-on'),
						'class' => 'small_hiden',
					),
					array(
						'label' => esc_html__('Password', 'gravity-forms-salesforce-add-on'),
						'type' => 'text',
						'input_type' => 'password',
						'name' => 'password',
						'tooltip' => esc_html__('Password', 'gravity-forms-salesforce-add-on'),
						'class' => 'small_hiden',
					),
					array(
						'label' => esc_html__('Consumer Key', 'gravity-forms-salesforce-add-on'),
						'type' => 'text',
						'name' => 'client_id',
						'tooltip' => esc_html__('Consumer Key', 'gravity-forms-salesforce-add-on'),
						'class' => 'small_hiden',
					),
					array(
						'label' => esc_html__('Consumer Secret', 'gravity-forms-salesforce-add-on'),
						'type' => 'text',
						'name' => 'client_secret',
						'tooltip' => esc_html__('Consumer Secret', 'gravity-forms-salesforce-add-on'),
						'class' => 'small_hiden',
					),

					array(
						'label' => esc_html__('Security Token', 'gravity-forms-salesforce-add-on'),
						'type' => 'text',
						'name' => 'security_token',
						'tooltip' => esc_html__('Security Token', 'gravity-forms-salesforce-add-on'),
						'class' => 'small_hiden',
					),
				)
			)
		);
	}

	protected function valid_api_message() {
		return '<span class="gf_keystatus_valid_text">' . sprintf(__("%s Success: Your Org ID. is properly configured.", 'gravity-forms-salesforce'), '<i class="fa fa-check gf_keystatus_valid"></i>') . '</span>';
	}

	protected function invalid_api_message() {

		$oid = $this->get_plugin_setting('org_id');
		$tofind = sprintf(__('To find your Salesforce.com Organization ID, in your Salesforce.com account, go to:%1$s %2$s[Your Name] &raquo; Setup &raquo; Company Profile%3$s (near the bottom of the left sidebar) %2$s&raquo; Company Information%3$s (it is buried in the table of information). It will look like %4$s.', 'gravity-forms-salesforce'), '<br />', "<span class='code'>", '</span>', '<code>00AB0000000Z9kR</code>');
		if (empty($oid)) {
			$message = __("Please enter your Salesforce Organization ID.", 'gravity-forms-salesforce');
		} else {
			$message = '<span class="gf_keystatus_invalid_text">' . sprintf(__("%s Invalid Org ID. - Please confirm your setting. Try re-saving the form.", 'gravity-forms-salesforce'), '<i class="fa fa-times gf_keystatus_invalid"></i>') . '</span>';
		}

		return '<h4>' . $message . '</h4>' . $tofind;
	}

	/**
	 * Configures the settings which should be rendered on the feed edit page in the Form Settings > Simple Feed Add-On area.
	 *
	 * @return array
	 */
	function feed_settings_fields() {
		return array(
			array(
				'title' => esc_html__('Salesforce Feed Settings', 'gravity-forms-salesforce-add-on'),
				'fields' => array(
					array(
						'label' => esc_html__('Feed name', 'gravity-forms-salesforce-add-on'),
						'type' => 'text',
						'name' => 'feedName',
						'tooltip' => esc_html__('Enter a feed name to uniquely identify this setup.', 'gravity-forms-salesforce-add-on'),
						'class' => 'small',
					),
					array(
						'type' => 'select',
						'label' => __('Pick an export type.', 'gravity-addon-salesforce'),
						'name' => 'type',
						'default_value' => 'Lead',
						'id' => 'quang',
						'choices' => array(
							array(
								'name' => 'lead',
								'value' => 'Lead',
								'label' => __('Lead', 'gravity-addon-salesforce'),
							),
							array(
								'name' => 'case',
								'value' => 'Contact',
								'label' => __('Contact', 'gravity-addon-salesforce'),
							),
						),
						'tooltip' => sprintf("<h6>" . __("Object to Create in Salesforce", "gravity-addon-salesforce") . "</h6>" . __("When the form is exported to Salesforce, do you want to have the entry become a Lead or a Contact?", 'gravity-addon-salesforce')),
					),

					array(
						'type' => 'field_map',
						'label' => __('Map your fields.', 'gravity-addon-salesforce'),
						'tooltip' => "<h6>" . __("Map Fields", "gravity-addon-salesforce") . "</h6>",
						'name' => null,
						'field_map' => $this->feed_settings_fields_field_map(),
					),
					array(
						'name' => 'metaData',
						'label' => esc_html__('Metadata', 'sometextdomain'),
						'type' => 'dynamic_field_map',
						'limit' => 20,
						'exclude_field_types' => 'creditcard',
						'tooltip' => '<h6>' . esc_html__('Metadata', 'sometextdomain') . '</h6>' . esc_html__('You may send custom meta information to [...]. A maximum of 20 custom keys may be sent. The key name must be 40 characters or less, and the mapped data will be truncated to 500 characters per requirements by [...]. ', 'sometextdomain'),
						'validation_callback' => array($this, 'validate_custom_meta'),
					),
					array(
						'label' => 'Opt-in Condition',
						'name' => 'feed_condition',
						'type' => 'feed_condition',

					),
				),
			),
		);
	}

	/**
	 * Set up the feed forms.
	 * @return array Array of feed fields
	 */
	protected function feed_settings_fields_field_map() {

		$fields = array(
			array(
				'name' => 'email',
				'required' => true,
				'label' => __("Email"),
				'error_message' => __("You must set an Email Address", 'gravity-addon-salesforce'),
			),
			array(
				'name' => 'salutation',
				'required' => false,
				'label' => __("Salutation", 'gravity-addon-salesforce'),
			),
			array(
				'name' => 'first_name',
				'required' => true,
				'label' => __("Name (First)", 'gravity-addon-salesforce'),
			),
			array(
				'name' => 'last_name',
				'required' => true,
				'label' => __("Name (Last)", 'gravity-addon-salesforce'),
			),
			array(
				'name' => 'title',
				'required' => false,
				'label' => __("Title", 'gravity-addon-salesforce'),
			),
			array(
				'name' => 'company',
				'required' => false,
				'label' => __("Company", 'gravity-addon-salesforce'),
			),
			array(
				'name' => 'phone',
				'required' => false,
				'label' => __("Phone", 'gravity-addon-salesforce'),
			),
			array(
				'name' => 'mobile',
				'required' => false,
				'label' => __("Mobile", 'gravity-addon-salesforce'),
			),
			array(
				'name' => 'subject',
				'required' => false,
				'label' => __("Subject", 'gravity-forms-salesforce'),
			),

		);

		return $fields;

	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	function feed_list_columns() {
		return array(
			'feedName' => esc_html__('Name', 'gravity-forms-salesforce-add-on'),
			'mytextbox' => esc_html__('My Textbox', 'gravity-forms-salesforce-add-on'),
		);
	}

	/**
	 * Format the value to be displayed in the mytextbox column.
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	function get_column_value_mytextbox($feed) {
		return '<b>' . rgars($feed, 'meta/mytextbox') . '</b>';
	}

	/**
	 * Prevent feeds being listed or created if an api key isn't valid.
	 *
	 * @return bool
	 */
	function can_create_feed() {

		// Get the plugin settings.
		$settings = $this->get_plugin_settings();

		// Access a specific setting e.g. an api key
		$key = rgar($settings, 'apiKey');

		return true;
	}
	public function process_feed($feed, $entry, $form) {
		$this->set_settings($feed['meta']);

		//
		try {

			foreach ($feed['meta'] as $key => $value) {
				// The field names have a trailing underscore for some reason.
				$trimmed_key = ltrim($key, '_');
				$feed['meta'][$trimmed_key] = $value;
				unset($feed['meta'][$key]);
			}

			$temp_merge_vars = $this->get_merge_vars_from_entry($feed, $entry, $form);
			$this->check = $temp_merge_vars;

			self::log_debug(sprintf("Temp Merge Vars: %s", print_r($temp_merge_vars, true)));

			$merge_vars = array();
			foreach ($temp_merge_vars as $key => $value) {

				// Get the field ID for the current value
				$field_id = $feed['meta'][$key];

				// We need to specially format some data going to Salesforce
				// If it's a field ID, get the field data
				if (is_numeric($field_id) && !empty($value)) {

					$field = RGFormsModel::get_field($form, $field_id);
					$field_type = RGFormsModel::get_input_type($field);

					// Right now, we only have special cases for dates.
					switch ($field_type) {
					case 'date':

						$value = $this->get_date_format($value, $key, compact('form', 'entry', 'field', 'feed'));

						break;
					}
				}

				if (is_array($value)) {

					// Filter the implode glue
					$glue = apply_filters('gf_salesforce_implode_glue', ';', $key);

					$value = implode($glue, $value);

					// Get rid of empty array values that would result in
					// List Item 1;;List Item 2 - that causes weird things to happen in
					// Salesforce
					$value = preg_replace('/' . preg_quote($glue) . '+/', $glue, $data[$label]);

					unset($glue);

				} else {
					$value = GFCommon::trim_all($value);
				}

				// If the value is empty, don't send it.
				if (empty($value) && $value !== '0') {
					unset($merge_vars[$key]);
				} else {
					// Add the value to the data being sent
					$merge_vars[$key] = GFCommon::replace_variables($value, $form, $entry, false, false, false);
				}
			}

			// Process Boolean opt-out fields
			if (isset($merge_vars['emailOptOut'])) {
				$merge_vars['emailOptOut'] = !empty($merge_vars['emailOptOut']);
			}
			if (isset($merge_vars['faxOptOut'])) {
				$merge_vars['faxOptOut'] = !empty($merge_vars['faxOptOut']);
			}
			if (isset($merge_vars['doNotCall'])) {
				$merge_vars['doNotCall'] = !empty($merge_vars['doNotCall']);
			}

			// Add Address Line 2 to the street address
			if (!empty($merge_vars['street2'])) {
				$merge_vars['street'] .= "\n" . $merge_vars['street2'];
			}

			// You can tap into the data and filter it.
			$merge_vars = apply_filters('gf_salesforce_push_data', $merge_vars, $form, $entry);

			$type = $this->get_setting('type');

			//'value' => 'Contact', Get input from field api
			if ($type == 'Contact') {

				$username = $this->get_plugin_setting('username');
				$password = $this->get_plugin_setting('password');
				$client_id = $this->get_plugin_setting('client_id');
				$client_secret = $this->get_plugin_setting('client_secret');
				$security_token = $this->get_plugin_setting('security_token');

				$contact_standard_fields = $this->get_field_map_fields($feed, null);

				$content = array();
				$content['FirstName'] = $temp_merge_vars['first_name'];
				$content['LastName'] = $temp_merge_vars['last_name'];
				$content['Phone'] = $temp_merge_vars['phone'];
				$content['Email'] = $temp_merge_vars['email'];
				$content['Title'] = $temp_merge_vars['title'];
				$this->update_contact_salesform($username, $password, $client_id, $client_secret, $security_token, $content);
			}

			

			$return = $this->send_request($merge_vars);

			// If it returns false, there was an error.
			if (empty($return)) {
				self::log_error(sprintf("There was an error adding {$entry['id']} to Salesforce. Here's what was sent: %s", print_r($merge_vars, true)));
				return false;
			} else {
				// Otherwise, it was a success.
				self::log_debug(sprintf("Entry {$entry['id']} was added to Salesforce. Here's the available data:\n%s", print_r($return, true)));
				return true;
			}

		} catch (Exception $e) {
			// Otherwise, it was a success.
			self::log_error(sprintf("Error: %s", $e->getMessage()));
		}

		return;
		//

	}

	/**
	 * Send data to Salesforce using wp_remote_post()
	 *
	 * @filter gf_salesforce_salesforce_debug_email Disable debug emails (even if you have debugging enabled) by returning false.
	 * @filter gf_salesforce_salesforce_debug_email_address Modify the email address Salesforce sends debug information to
	 * @param  array  $post  Data to send to Salesforce
	 * @param  boolean $test Is this just testing the OID configuration and not actually sendinghelpful data?
	 * @return array|false         If the Salesforce server returns a non-standard code, an empty array is returned. If there is an error, `false` is returned. Otherwise, the `wp_remote_request` results array is returned.
	 */
	public function send_request($post, $test = false) {
		// global $wp_version;

		// Get submission type: Lead or Contact
		$type = $this->get_setting('type', 'Lead');

		// Web-to-Lead uses `oid` and Web to Case uses `orgid`
		switch ($type) {
		case 'Contact':
			//Not implemented because of above implementation
			break;
		default:
		case 'Lead':
			$post['oid'] = $this->get_plugin_setting('org_id');
		}

		// We need an Org ID to post to Salesforce successfully.
		if (empty($post['oid']) && empty($post['orgid'])) {
			self::log_error(__("No Salesforce Org. ID was specified.", 'gravity-forms-salesforce'));
			return NULL;
		}

		// Debug is 0 by default.
		$post['debug'] = 0;

		// Is this a live request?
		if (!$test) {

			$post['debug_email'] = isset($post['debug_email']) ? $post['debug_email'] : $this->get_plugin_setting('debug_email');

			if (!empty($post['debug_email'])) {

				// Don't want to pass this to Salesforce.
				unset($post['debug_email']);

				// Enable debug.
				$post['debug'] = 1;

				// The default debugging email is the WordPress admin email address,
				// unless overridden by passed args.
				$post['debugEmail'] = isset($post['debugEmail']) ? $post['debugEmail'] : get_option('admin_email');

				// Salesforce will send debug emails to this email address.
				$post['debugEmail'] = apply_filters('gf_salesforce_salesforce_debug_email_address', $post['debugEmail']);

				// If the filter passes an invalid email address, then don't use it.
				$post['debugEmail'] = is_email($post['debugEmail']) ? $post['debugEmail'] : NULL;

			}
		}
		unset($post['debugEmail']);

		// Redirect back to current page.
		$post['retURL'] = add_query_arg(array());

		// Set SSL verify to false because of server issues.
		$args = array(
			'body' => $post,
			'headers' => array(
				'user-agent' => 'Gravity Forms Salesforce Add-on plugin - WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
			),
			'sslverify' => false,
			'timeout' => MINUTE_IN_SECONDS,
		);

		$args = apply_filters('gf_salesforce_request_args', $args, $test);

		// Use test/www subdomain based on whether this is a test or live
		$sub = apply_filters('gf_salesforce_request_subdomain', ($test ? 'test' : 'www'), $test);

		// Use (test|www) subdomain and WebTo(Lead|Case) based on setting
		$url = apply_filters('gf_salesforce_request_url', sprintf('https://%s.salesforce.com/servlet/servlet.WebTo%s?encoding=UTF-8', $sub, $type), $args);

		self::log_debug(sprintf("This is the data sent to %s (at %s:\n%s)", $this->_service_name, $url, print_r($args, true)));

		// POST the data to Salesforce
		$result = wp_remote_post($url, $args);

		return $this->handle_response($result);
	}

	/**
	 * Determine whether the response was valid or not.
	 * @param $result
	 *
	 * @return array|null NULL if there's an error. Array if
	 */
	private function handle_response($result) {

		// There was an error
		if (is_wp_error($result)) {
			self::log_error(sprintf("There was an error adding the entry to Salesforce: %s", $result->get_error_message()));
			return array();
		}

		// Find out what the response code is
		$code = wp_remote_retrieve_response_code($result);

		// Salesforce should ALWAYS return 200, even if there's an error.
		// Otherwise, their server may be down.
		if (intval($code) !== 200) {
			self::log_error(sprintf("The Salesforce server may be down, since it should always return '200'. The code it returned was: %s", $code));
			return array();
		}
		// If `is-processed` isn't set, then there's no error.
		elseif (!isset($result['headers']['is-processed'])) {
			self::log_debug("The `is-processed` header isn't set. This means there were no errors adding the entry.");
			return $result;
		}
		// If `is-processed` is "true", then there's no error.
		else if ($result['headers']['is-processed'] === "true") {
			self::log_debug("The `is-processed` header is set to 'true'. This means there were no errors adding the entry.");
			return $result;
		}
		// But if there's the word "Exception", there's an error.
		else if (strpos($result['headers']['is-processed'], 'Exception')) {
			self::log_error(sprintf(__('The `is-processed` header shows an Exception: %s. This means there was an error adding the entry.', 'gravity-forms-salesforce'), $result['headers']['is-processed']));
			return NULL;
		}

		// Don't know how you get here, but if you do, here's an array
		return array();
	}

	public static function update_contact_salesform($username, $password, $client_id, $client_secret, $security_token, $content) {

		$bien = wp_remote_post(sprintf('https://login.salesforce.com/services/oauth2/token?grant_type=password&client_id=%1$s &client_secret=%2$s &username=%3$s &password=%4$s%5$s', $client_id, $client_secret, $username, $password, $security_token));

		$body = wp_remote_retrieve_body($bien);
		$body = json_decode($body);

		$instance_url = $body->instance_url;
		$param = "/services/data/v41.0/sobjects/Contact/";
		$url = $instance_url . $param;
		$access_token = $body->access_token;

		$body = json_encode($content);
		$reponse = wp_remote_post(
			$url,
			array(
				'body' => $body,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				),
			)
		);
	}

}

