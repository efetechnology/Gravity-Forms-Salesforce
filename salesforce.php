<?php
/*
Plugin Name: Gravity Forms SalesForce Add-On 
Description: Easily integrate Salesforce with your Gravity Forms.
Version: 1.0
Author: EFE Technology
Author URI: http://efe.com.vn/
Copyright 2018 EFE Technology.
*/

define('GF_SALESFORCE_ADDON_VERSION', '1.0');

add_action('gform_loaded', array('GF_Salesforce_Feed_AddOn_Bootstrap', 'load'), 5);

class GF_Salesforce_Feed_AddOn_Bootstrap {

	public static function load() {

		if (!method_exists('GFForms', 'include_feed_addon_framework')) {
			return;
		}
		require_once 'gravity-forms-salesforce-add-on.php';

		GFAddOn::register('GFSalesForceAddOn');
	}

}

function gf_simple_feed_addon() {
	return GFSalesForceAddOn::get_instance();
}