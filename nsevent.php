<?php
/*
Plugin Name: NSEvent
Plugin URI: http://github.com/brucep/nsevent
Description: An event registration and reporting system for dance organizations.
Version: 1.0
Author: Bruce Phillips
License: X11
Note: Requires PHP 5.2.3 or later.
*/
/*
Copyright (C) 2010 Bruce Phillips

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Except as contained in this notice, the name of the author not be used in advertising or otherwise to promote the sale, use or other dealings in this Software without prior written authorization from the author.
*/

if (!class_exists('NSEvent')):
class NSEvent
{
	static public $event, $validation; # Used by validation methods
	static private $vip, $validated_package_id = 0, $validated_items = array(), $twig;
	static private $default_options = array(
		'current_event_id'      => '',
		'email_bcc'             => '',
		'email_from'            => '',
		'email_smtp_host'       => 'smtp.gmail.com',
		'email_smtp_port'       => '465',
		'email_smtp_username'   => '',
		'email_smtp_password'   => '',
		'email_smtp_encryption' => 'ssl',
		'email_transport'       => 'mail',
		'mailing_address'       => '',
		'payable_to'            => '',
		'paypal_business'       => '',
		'paypal_fee'            => 0,
		'paypal_sandbox'        => false,
		'postmark_within'       => 7,
		'registration_testing'  => false,
		);
	
	private function __clone() {}
	private function __construct() {}
	
	static public function admin_init()
	{
		register_setting('nsevent', 'nsevent', 'NSEvent::admin_validate_options');
	}
	
	static public function admin_menu()
	{
		$hookname = add_menu_page('Registration Reports', 'Registration Reports', 'edit_pages', 'nsevent', 'NSEvent::page_request');
		add_submenu_page('nsevent', 'Registration Options', 'Registration Options', 'manage_options', 'nsevent-options', 'NSEvent::page_options');
		
		add_action('admin_print_scripts-' . $hookname, 'NSEvent::admin_print_scripts');
		add_action('admin_print_styles-' . $hookname,  'NSEvent::admin_print_styles');
		add_action('admin_print_styles',               'NSEvent::admin_menu_hide_icon');
	}
	
	static public function admin_menu_hide_icon()
	{
		echo '<style type="text/css">li#toplevel_page_nsevent div.wp-menu-image { display: none; } body.folded li#toplevel_page_nsevent div.wp-menu-image { display: inherit; }</style>';
	}
	
	static public function admin_print_scripts()
	{
		wp_enqueue_script('nsevent-tablesorter',      plugins_url('js/jquery.tablesorter.min.js', __FILE__), array('jquery'));
		wp_enqueue_script('nsevent-tablesorter-init', plugins_url('js/tablesorter-init.js', __FILE__),       array('nsevent-tablesorter'));
	}
	
	static public function admin_print_styles()
	{
		wp_enqueue_style('nsevent-admin', plugins_url('css/admin.css', __FILE__));
		echo '<meta name="viewport" content="initial-scale=1.0;" />';
	}
	
	static public function admin_validate_options($input)
	{
		$options = self::get_options();
		
		NSEvent_Model::set_database(self::get_database_connection());
		
		if (isset($input['current_event_id']) and NSEvent_Model_Event::get_event_by_id($input['current_event_id'])) {
			$options['current_event_id'] = (int) $input['current_event_id'];
		}
		
		$options['registration_testing'] = isset($input['registration_testing']);
		
		if (isset($input['postmark_within'])) {
			$options['postmark_within'] = (int) $input['postmark_within'];
		}
		else {
			$options['postmark_within'] = 7;
		}
		
		if (isset($input['payable_to'])) {
			$options['payable_to'] = trim($input['payable_to']);
		}
		
		if (isset($input['mailing_address'])) {
			$options['mailing_address'] = trim($input['mailing_address']);
		}
		
		if (isset($input['paypal_business'])) {
			$options['paypal_business'] = trim($input['paypal_business']);
		}
		
		if (isset($input['paypal_fee'])) {
			$options['paypal_fee'] = (int) $input['paypal_fee'];
		}
		
		if (isset($input['email_from'])) {
			$options['email_from'] = trim($input['email_from']);
		}
		else {
			$options['email_from'] = get_option('admin_email');
		}
		
		if (isset($input['email_bcc'])) {
			$options['email_bcc'] = trim($input['email_bcc']);
		}
		
		if (isset($input['email_transport']) and in_array($input['email_transport'], array('smtp', 'mail'))) {
			$options['email_transport'] = $input['email_transport'];
		}
		
		if (!empty($input['email_smtp_port'])) {
			if (is_numeric($input['email_smtp_port'])) {
				$options['email_smtp_port'] = (int) $input['email_smtp_port'];
			}
		}
		else {
			$options['email_smtp_port'] = '';
		}
		
		if (isset($input['email_smtp_username'])) {
			$options['email_smtp_username'] = trim($input['email_smtp_username']);
		}
		
		if (isset($input['email_smtp_password'])) {
			$options['email_smtp_password'] = $input['email_smtp_password'];
		}
		
		if (isset($input['email_smtp_encryption']) and in_array($input['email_smtp_encryption'], array('ssl', 'tsl', 'none'))) {
			$options['email_smtp_encryption'] = $input['email_smtp_encryption'];
		}
		
		return $options;
	}
	
	static public function autoload($class)
	{
		if (substr($class, 0, 8) == 'NSEvent_') {
			$class = implode('-', explode('_', strtolower(substr($class, 8))));
			require dirname(__FILE__) . '/includes/' . $class . '.php';
		}
	}
	
	static public function get_database_connection()
	{
		global $wpdb;
		
		return new NSEvent_Database(array(
			'host'     => DB_HOST,
			'port'     => defined('DB_HOST_PORT') ? DB_HOST_PORT : false,
			'name'     => DB_NAME,
			'user'     => DB_USER,
			'password' => DB_PASSWORD,
			'prefix'   => $wpdb->prefix . 'nsevent',
			));
	}
	
	static public function get_options()
	{
		return array_merge(self::$default_options, get_option('nsevent', array()));
	}
	
	static public function page_options()
	{
		if (!current_user_can('administrator')) {
			throw new Exception(__('Cheatin&#8217; uh?'));
		}
		
		NSEvent_Model::set_database(self::get_database_connection());
		NSEvent_Model::set_options(self::get_options());
		
		$events = array();
		
		foreach (NSEvent_Model_Event::get_events() as $event) {
			$events[$event->id()] = $event->name();
		}
		
		echo self::render_template('admin/options.html', array('events' => $events));
	}
	
	static public function page_request()
	{
		try {
			@date_default_timezone_set(get_option('timezone_string'));
			
			if (!current_user_can('edit_pages')) {
				throw new Exception(__('Cheatin&#8217; uh?'));
			}
			
			NSEvent_Model::set_database(self::get_database_connection());
			NSEvent_Model::set_options(self::get_options());
			
			if (empty($_GET['request'])) {
				$_GET['request'] = 'report_index';
			}
			
			if (is_callable(array('NSEvent_Request_Controller', $_GET['request']))) {
				if (substr($_GET['request'], 0, 6) == 'admin_' and !current_user_can('administrator')) {
					throw new Exception(__('Cheatin&#8217; uh?'));
				}
				
				$params = array();
				
				if (!in_array($_GET['request'], array('report_index', 'admin_event_add'))) {
					if (!$params['event'] = self::$event = NSEvent_Model_Event::get_event_by_id($_GET['event_id'])) {
						throw new Exception(sprintf('Event ID not found: %d', $_GET['event_id']));
					}
					
					if (isset($_GET['dancer_id'])) {
						if (!$params['dancer'] = self::$event->dancer_by_id($_GET['dancer_id'])) {
							throw new Exception(sprintf('Dancer ID not found: %d', $_GET['dancer_id']));
						}
					}
					
					if (isset($_GET['item_id'])) {
						if (!$params['item'] = self::$event->item_by_id($_GET['item_id'])) {
							throw new Exception(sprintf('Item ID not found: %d', $_GET['dancer_id']));
						}
					}
				}
				
				call_user_func_array(array('NSEvent_Request_Controller', $_GET['request']), $params);
			}
			else {
				throw new Exception(sprintf('Unable to handle page request: %s', esc_html($_GET['request'])));
			}
		}
		catch (Exception $e) {
			printf('<div id="nsevent-exception">%s</div>', $e->getMessage());
		}
	}
	
	static public function plugin_activate()
	{
		global $wpdb;
		
		# Include `dbDelta` function
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		$tables = array(
			'events',
			'event_levels',
			'items',
			'item_prices',
			'dancers',
			'registrations',
			'housing');
		
		foreach ($tables as $table) {
			$table_name = sprintf('%snsevent_%s', $wpdb->prefix, $table);
			
			# Create new database tables
			if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
				switch ($table):
					case 'events':
						$query = sprintf("CREATE TABLE `%s` (
							`event_id`               int(10) unsigned NOT NULL auto_increment,
							`name`                   varchar(255) NOT NULL,
							`date_mail_prereg_end`   int(10) unsigned NOT NULL default '0',
							`date_paypal_prereg_end` int(10) unsigned NOT NULL default '0',
							`date_refund_end`        int(10) unsigned NOT NULL default '0',
							`has_discount`           tinyint(1) unsigned NOT NULL DEFAULT '0',
							`has_vip`                tinyint(1) unsigned NOT NULL default '0',
							`has_volunteers`         tinyint(1) unsigned NOT NULL default '0',
							`has_housing`            tinyint(1) unsigned NOT NULL default '0',
							`housing_nights`         set('Friday','Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday') NOT NULL,
							`limit_discount`         tinyint(3) unsigned NOT NULL default '0',
							`discount_org_name`      varchar(255) NOT NULL DEFAULT '',
							`levels`                 varchar(255) NOT NULL,
							`shirt_description`      text NOT NULL,
							PRIMARY KEY  (`id`)
							);", $table_name);
						break;
					
					case 'event_levels':
						$query = sprintf("CREATE TABLE `%s` (
							`event_id`    int(10) unsigned NOT NULL,
							`level_id`    tinyint(3) unsigned NOT NULL,
							`label`       varchar(255) NOT NULL,
							`has_tryouts` tinyint(1) unsigned NOT NULL DEFAULT '0',
							PRIMARY KEY (`event_id`,`level_id`)
							);", $table_name);
						break;
					
					case 'items':
						$query = sprintf("CREATE TABLE `%s` (
							`event_id`               int(10) unsigned NOT NULL,
							`item_id`                int(10) unsigned NOT NULL AUTO_INCREMENT,
							`name`                   varchar(200) NOT NULL,
							`type`                   varchar(11) NOT NULL,
							`preregistration`        tinyint(1) unsigned NOT NULL DEFAULT '1',
							`price_prereg`           tinyint(3) unsigned NOT NULL DEFAULT '0',
							`price_door`             tinyint(3) unsigned NOT NULL DEFAULT '0',
							`price_discount`         tinyint(3) unsigned NOT NULL DEFAULT '0',
							`price_vip`              tinyint(3) unsigned NOT NULL DEFAULT '0',
							`limit_total`            smallint(5) unsigned NOT NULL DEFAULT '0',
							`limit_per_position`     smallint(5) unsigned NOT NULL DEFAULT '0',
							`date_expires`           int(10) unsigned NOT NULL DEFAULT '0',
							`meta`                   varchar(20) NOT NULL DEFAULT '',
							`description`            varchar(255) NOT NULL DEFAULT '',
							PRIMARY KEY (`id`)
							);", $table_name);
						break;
					
					case 'item_prices':
						$query = sprintf("CREATE TABLE `%s` (
							`event_id`    int(10) unsigned NOT NULL,
							`item_id`     int(10) unsigned NOT NULL,
							`scale_count` smallint(5) unsigned NOT NULL,
							`scale_price` smallint(5) unsigned NOT NULL,
							PRIMARY KEY (`event_id`,`item_id`,`scale_count`)
						);", $table_name);
						break;
					
					case 'dancers':
						$query = sprintf("CREATE TABLE `%s` (
							`event_id`          int(10) unsigned NOT NULL,
							`dancer_id`         int(10) unsigned NOT NULL auto_increment,
							`first_name`        varchar(100) NOT NULL,
							`last_name`         varchar(100) NOT NULL,
							`email`             varchar(100) NOT NULL,
							`position`          tinyint(1) NOT NULL,
							`level`             tinyint(1) unsigned NOT NULL default '1',
							`status`            tinyint(1) unsigned NOT NULL default '0',
							`date_registered`   int(10) unsigned NOT NULL default '0',
							`payment_method`    varchar(6) NOT NULL,
							`payment_discount`  varchar(3) NOT NULL default '0',
							`payment_confirmed` tinyint(1) unsigned NOT NULL default '0',
							`payment_owed`      smallint(5) unsigned NOT NULL default '0',
							`mobile_phone`      varchar(30) NOT NULL DEFAULT '',
							`note`              varchar(255) NOT NULL,
							PRIMARY KEY  (`id`)
							);", $table_name);
						break;
					
					case 'registrations':
						$query = sprintf("CREATE TABLE `%s` (
							`event_id`  int(10) unsigned NOT NULL,
							`dancer_id` int(10) unsigned NOT NULL,
							`item_id`   int(10) unsigned NOT NULL,
							`price`     tinyint(3) unsigned NOT NULL,
							`item_meta` text NOT NULL,
							PRIMARY KEY  (`dancer_id`,`item_id`)
							);", $table_name);
						break;
					
					case 'housing':
						$query = sprintf("CREATE TABLE `%s` (
							`event_id`                int(10) UNSIGNED NOT NULL,
							`dancer_id`               int(10) UNSIGNED NOT NULL,
							`housing_type`            tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
							`housing_spots_available` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
							`housing_nights`          set('Friday','Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday') NOT NULL,
							`housing_gender`          tinyint(1) UNSIGNED NOT NULL DEFAULT '3',
							`housing_bedtime`         tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
							`housing_pets`            tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
							`housing_smoke`           tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
							`housing_from_scene`      varchar(255) NOT NULL DEFAULT '',
							`housing_comment`         text NOT NULL,
							PRIMARY KEY (`event_id`,`dancer_id`)
							);", $table_name);
						break;
				endswitch;
				
				dbDelta($query);
			}
			
			self::$default_options['confirmation_email_address'] = get_option('admin_email');
			add_option('nsevent', self::$default_options, '', 'no');
		}
			
	}
	
	static public function registration_form()
	{
		global $post;
		
		try {
			# Define a constant for themes to use
			define('REGISTRATION_FORM', true);
			
			# Stop the `WP Super Cache` plugin from caching registration pages
			define('DONOTCACHEPAGE', true);
			
			# Don't mess with my timezone WordPress!
			@date_default_timezone_set(get_option('timezone_string'));
			
			NSEvent_Model::set_database(self::get_database_connection());
			NSEvent_Model::set_options(self::get_options());
			
			$options = self::get_options();
			self::$validation = new NSEvent_Form_Validation;
			
			
			# Find current event
			$event = self::$event = NSEvent_Model_Event::get_event_by_id($options['current_event_id']);
			
			if (!$event) {
				throw new Exception(sprintf('Event ID not found: %d', $options['current_event_id']));
			}
			
			self::$vip = ($event->has_vip() and isset($_GET['vip']));
			
			# Display page content when registration is not available.
			if ((time() > $event->date_paypal_prereg_end() and time() > $event->get_date_mail_prereg_end() and !$vip) or ($options['registration_testing'] and !current_user_can('edit_pages'))) {
				get_template_part('page');
				return;
			}
			
			
			# Setup validation rules
			if (!empty($_POST)) {
				self::$validation->add_rules(array(
					'first_name'      => 'trim|required|max_length[100]|ucfirst',
					'last_name'       => 'trim|required|max_length[100]|ucfirst',
					'email'           => 'trim|valid_email|max_length[100]|NSEvent::validate_email_address',
					'confirm_email'   => 'trim|valid_email|max_length[100]',
					'mobile_phone'    => 'trim|required|max_length[30]',
					'position'        => 'intval|in[1,2]',
					'status'          => 'NSEvent::validate_status',
					'package'         => 'intval|NSEvent::validate_package',
					'items'           => 'NSEvent::validate_items',
					'payment_method'  => 'in[Mail,PayPal]',
					));
				
				# Level
				if ($event->has_levels()) {
					self::$validation->add_rule('level_id', sprintf('intval|in[%s]',
						implode(',', array_keys($event->levels_keyed_by_id()))));
				}
				else {
					$_POST['level_id'] = 1;
				}
				
				# Discount
				if ($event->has_discount()) {
					self::$validation->add_rule('payment_discount', 'intval|in[0,1]|NSEvent::validate_discount');
				}
				else {
					$_POST['payment_discount'] = 0;
				}
				
				# Housing
				if ($event->has_housing_enabled()) {
					self::$validation->add_rules(array(
						'housing_provider[housing_spots_available]' => 'if_set[housing_type_provider]|intval|greater_than[0]',
						'housing_provider[housing_smoke]'           => 'if_set[housing_type_provider]|intval|in[0,1]',
						'housing_provider[housing_pets]'            => 'if_set[housing_type_provider]|intval|in[0,1]',
						'housing_provider[housing_gender]'          => 'if_set[housing_type_provider]|intval|in[1,2,3]',
						'housing_provider[housing_bedtime]'         => 'if_set[housing_type_provider]|intval|in[0,1,2]',
						'housing_provider[housing_nights]'          => 'if_set[housing_type_provider]|NSEvent::validate_housing_nights',
						'housing_provider[housing_comment]'         => 'if_set[housing_type_provider]|trim|max_length[65536]',
						'housing_needed[housing_from_scene]'        => 'if_set[housing_type_needed]|trim|required|max_length[255]|ucwords',
						'housing_needed[housing_smoke]'             => 'if_set[housing_type_needed]|intval|in[0,1]',
						'housing_needed[housing_pets]'              => 'if_set[housing_type_needed]|intval|in[0,1]',
						'housing_needed[housing_gender]'            => 'if_set[housing_type_needed]|intval|in[1,2,3]',
						'housing_needed[housing_bedtime]'           => 'if_set[housing_type_needed]|intval|in[0,1,2]',
						'housing_needed[housing_nights]'            => 'if_set[housing_type_needed]|NSEvent::validate_housing_nights',
						'housing_needed[housing_comment]'           => 'if_set[housing_type_needed]|trim|max_length[65536]',
						));
				}
			}
			
			# Determine appropriate file for current step
			if (empty($_POST) or !self::$validation->validate()) {
				$file = 'form-reg-info';
			}
			else {
				# Used for confirmation page and email
				$package_cost      = 0;
				$competitions      = array();
				$competitions_cost = 0;
				$shirts            = array();
				$shirts_cost       = 0;
				$total_cost        = 0;
				
				if (self::$vip) {
					if (self::$validated_package_id !== 0) {
						$package_cost = self::$validated_items[self::$validated_package_id]->price_for_vip();
					}
					
					foreach (self::$validated_items as $item) {
						$total_cost += $item->price_for_vip();
					}
				}
				else {
					if (self::$validated_package_id !== 0) {
						$package_cost = self::$validated_items[self::$validated_package_id]->price_for_prereg($_POST['payment_discount']);
					}
					
					foreach (self::$validated_items as $item) {
						$total_cost += $item->price_for_prereg($_POST['payment_discount']);
					}
				}
				
				if ($total_cost == 0) {
					$_POST['payment_method'] = 'Mail';
				}
				
				
				# Prep info before creating new dancer object
				$dancer_data = $_POST;
				unset($dancer_data['items'], $dancer_data['item_meta'], $dancer_data['confirmed'], $dancer_data['confirm_email']);
				
				$dancer_data['payment_owed'] = $total_cost;
				$dancer_data['payment_confirmed'] = ($total_cost == 0) ? 1 : 0;
				
				if ($options['registration_testing']) {
					$dancer_data['note'] = 'TEST';
				}
				
				if ($event->has_housing_enabled()) {
					if (isset($dancer_data['housing_type_needed'])) {
						$dancer_data = array_merge($dancer_data, $dancer_data['housing_needed']);
						$dancer_data['housing_type'] = 1;
					}
					elseif (isset($dancer_data['housing_type_provider'])) {
						$dancer_data = array_merge($dancer_data, $dancer_data['housing_provider']);
						$dancer_data['housing_type'] = 2;
					}
					
					unset($dancer_data['housing_type_needed'],$dancer_data['housing_needed'], $dancer_data['housing_type_provider'], $dancer_data['housing_provider']);
				}
				
				$dancer = new NSEvent_Model_Dancer($dancer_data);
				
				
				if (!isset($_POST['confirmed'])) {
					$file = 'form-confirm';
				}
				else {
					# Add dancer
					$dancer->add($event->id());
					
					if (!$dancer) {
						throw new Exception('Unable to add dancer to database.');
					}
					
					# Add housing
					if ($event->has_housing_enabled() and ($dancer->needs_housing() or $dancer->is_housing_provider())) {
						$dancer->add_housing();
					}
					
					# Add registrations				
					foreach (self::$validated_items as $item) {
						if (self::$vip) {
							$item_price = $item->price_for_vip();
						}
						else {
							$item_price = $item->price_for_prereg($_POST['payment_discount']);
						}
						
						if ($item->type() == 'competition') {
							$competitions[$item->id()] = $item;
							$competitions_cost += $item_price;
						}
						elseif ($item->type() == 'shirt') {
							$shirts[$item->id()] = $item;
							$shirts_cost += $item_price;
						}
						
						$event->add_registration(array(
							'dancer_id' => $dancer->id(),
							'item_id'   => $item->id(),
							'price'     => $item_price,
							'item_meta' => (!isset($_POST['item_meta'][$item->id()]) ? '' : $_POST['item_meta'][$item->id()]),
							));
					}
					
					
					// TODO: For VIPs, force payment_method to "mail" if their total cost is 0?
					
					
					# Confirmation email
					// if (!$options['registration_testing']) {
						// try {
						// 	self::send_confirmation_email($confirmation_email);
						// }
						// catch (Exception $confirmation_email_failed) {
						// 	error_log('Error sending confirmation email: ' . $confirmation_email_failed->getMessage());
						// }
					// }
					
					
					$file = 'form-accepted';
				}
			}
			
			
			if (!get_post_meta($post->ID, 'registration_form', true)) { get_header(); }
			
			echo self::render_template(sprintf('registration/%s.html', $file), array(
				'event'                     => $event,
				'dancer'                    => isset($dancer) ? $dancer : null,
				'packages'                  => $event->items_where(array(':type' => 'package'),     true),
				'competitions'              => $event->items_where(array(':type' => 'competition'), true),
				'shirts'                    => $event->items_where(array(':type' => 'shirt'),       true),
				'time'                      => time(),
				'vip'                       => self::$vip,
				'permalink'                 => get_permalink(),
				'the_content'               => get_the_content(),
				'validation'                => self::$validation,
				'validated_items'           => self::$validated_items,
				'validated_package_id'      => self::$validated_package_id,
				'confirmation_email_failed' => isset($confirmation_email_failed) ? $confirmation_email_failed : null));
			
			if (!get_post_meta($post->ID, 'registration_form', true)) { get_footer(); }
		}
		catch (Exception $e) {
			if (!get_post_meta($post->ID, 'nsevent_registration_form', true)) { get_header(); }
			printf('<div id="nsevent-exception">%s</div>'."\n", $e->getMessage());
			if (!get_post_meta($post->ID, 'nsevent_registration_form', true)) { get_footer(); }
		}
	}
	
	static public function registration_head()
	{
		add_action('wp_head', 'NSEvent::registration_wp_head');
		wp_enqueue_style('nsevent-registration', plugins_url('css/registration.css', __FILE__));
		
		# Check if the current theme has a stylesheet for the registration
		$theme_stylesheet = sprintf('%s/%s/style-nsevent-registration.css', get_theme_root(), get_stylesheet());
		if (file_exists($theme_stylesheet)) {
			wp_enqueue_style('nsevent-registration-from-theme', get_stylesheet_directory_uri() . '/style-nsevent-registration.css');
		}
		
		wp_enqueue_script('nsevent-reg-info', plugins_url('js/reg-info.js', __FILE__), array('jquery'));
	}
	
	static public function registration_wp_head()
	{
		# Block search engines for this page if they are not blocked already
		if (get_option('blog_public')) {
			echo "<meta name='robots' content='noindex,nofollow' />\n";
		}
	}
	
	static public function render_template($file, array $context = array())
	{
		if (!isset(self::$twig)) {
			require dirname(__FILE__) . '/includes/Twig/lib/Twig/Autoloader.php';
			Twig_Autoloader::register();
			
			self::$twig = new Twig_Environment(
				new Twig_Loader_Filesystem(dirname(__FILE__) . '/templates'),
				array('debug' => WP_DEBUG));
			
			if (WP_DEBUG) {
				self::$twig->addExtension(new Twig_Extension_Debug());
			}
			
			self::$twig->addGlobal('form', new NSEvent_Form_Controls);
			self::$twig->addFunction('pluralize', new Twig_Function_Function('_n'));
			
			if (is_admin() and $_GET['page'] == 'nsevent-options') {
				self::$twig->addFunction('settings_fields', new Twig_Function_Function('settings_fields', array('is_safe' => array('html'))));
			}
		}
		
		$context['GET'] = $_GET;
		$context['POST'] = $_POST;
		$context['options'] = self::get_options();
		
		if (is_admin()) {
			$context['admin'] = current_user_can('administrator');
			
			if (isset(self::$event)) {
				$context['request_href'] = site_url('wp-admin/admin.php') . sprintf('?page=nsevent&event_id=%d&request=', self::$event->id());
			}
			else {
				$context['request_href'] = site_url('wp-admin/admin.php') . '?page=nsevent&request=';
			}
		}
		
		return self::$twig->loadTemplate($file)->render($context);
	}
	
	static public function validate_discount($payment_discount)
	{
		if ($payment_discount == 1 and !self::$event->has_discount_openings()) {
			$_POST['payment_discount'] = 0; // Change the value so that the checkbox won't appear checked.
			self::$validation->set_error('payment_discount', 'There are no more discount openings available. Review the prices before continuing with your registration.');
			return false;
		}
		else {
			return true;
		}
	}
	
	static public function validate_email_address($email)
	{
		$options = self::get_options();
		
		if (isset($_POST['confirm_email']) and $_POST['confirm_email'] == $email) {
			if ($options['registration_testing'] or empty($_POST['first_name']) or empty($_POST['last_name'])) {
				return true;
			}
			elseif (!self::$event->dancers_where(array(':first_name' => $_POST['first_name'], ':last_name' => $_POST['last_name'], ':email' => $email))) {
				return true;
			}
			else {
				self::$validation->set_error('email', sprintf('Someone has already registered with this information. If you have already registered and need to change your information, then please reply to your confirmation email. For any other concerns, email <a href="mailto:%1$s">%1$s</a>.', $options['confirmation_email_address']));
				return false;
			}
		}
		else {
			self::$validation->set_error('email', 'Your email addresses do not match.');
			return false;
		}
	}
	
	static public function validate_package($package_id)
	{
		if ($package_id === 0) {
			return true;
		}
		elseif (self::validate_items(array($package_id => $package_id))) {
			$item = self::$event->item_by_id($package_id);
			
			if (isset($_POST['package_tier'][$package_id]) and $_POST['package_tier'][$package_id] != $item->price_tier()) {
				self::$validation->set_error('package', 'The price has changed on this package. Review the price before continuing with your registration.');
				return false;
			}
			else {
				self::$validated_package_id = $package_id;
				return true;
			}
		}
		else {
			return false;
		}
	}
	
	static public function validate_items($items)
	{
		if (empty($items)) {
			return true; # skip
		}
		elseif (!is_array($items)) {
			return false;
		}
		
		if (empty($_POST['item_meta']) or !is_array($_POST['item_meta'])) {
			$_POST['item_meta'] = array();
		}
		
		$items_did_validate = true;
		
		foreach ($items as $key => $value) {
			$item = self::$event->item_by_id($key);
			
			if (!$item) {
				continue;
			}
			
			switch ($item->meta()) {
				# If position wasn't specified specifically for item, use dancer's position.
				case 'position':
					if (!isset($_POST['item_meta'][$item->id()]) or !in_array($_POST['item_meta'][$item->id()], array('lead', 'follow'))) {
						if (!self::$validation->get_error('position')) {
							$_POST['item_meta'][$item->id()] = ($_POST['position'] == 1) ? 'lead' : 'follow';
						}
					}
					break;
				
				case 'partner_name':
					if (empty($_POST['item_meta'][$item->id()])) {
						self::$validation->set_error('item_' . $item->id(), sprintf('Your partner\'s name must be specified for %s.', $item->name()));
						$items_did_validate = false;
						continue 2;
					}
					else {
						$_POST['item_meta'][$item->id()] = trim($_POST['item_meta'][$item->id()]);
						// TODO: Check if partner has already registered for this item.
					}
					break;
				
				case 'team_members':
					if (empty($_POST['item_meta'][$item->id()])) {
						self::$validation->set_error('item_' . $item->id(), sprintf('Team members must be specified for %s.', $item->name()));
						$items_did_validate = false;
						continue 2;
					}
					else {
						# Standarize formatting
						$_POST['item_meta'][$item->id()] = ucwords(preg_replace(array("/[\r\n]+/", "/\n+/", "/\r+/", '/,([^ ])/', '/, , /'), ', $1', trim($_POST['item_meta'][$item->id()])));
						
						if (strlen($_POST['item_meta'][$item->id()]) > 65536) {
							self::$validation->set_error('item_' . $item->id(), sprintf('Team members list for %s is too long.', $item->name()));
							$items_did_validate = false;
							continue 2;
						}
					}
					break;
				
				case 'size':
					if (!in_array($value, array_merge(array('None'), explode(',', $item->description())))) {
						self::$validation->set_error('item_' . $item->id(), sprintf('An invalid size was choosen for %s.', $item->name()));
						$items_did_validate = false;
						continue 2;
					}
					elseif ($value === 'None') {
						continue 2; # No size selected;
					}
					$_POST['item_meta'][$item->id()] = $value; # Populate `item_meta` for the confirmation and PayPal page
					break;
			}
			
			# Check openings again, in case they have filled since the form was first displayed to the user
			if (($item->meta() != 'position' and !$item->count_openings()) or ($item->meta() == 'position' and !$item->count_openings($_POST['item_meta'][$item->id()]))) {
				self::$validation->set_error('item_' . $item->id(), sprintf('There are no longer any openings for %s.', $item->name()));
				$items_did_validate = false;
				continue;
			}
			
			self::$validated_items[$item->id()] = $item;
		}
		
		return $items_did_validate;
	}
	
	static public function validate_status($status)
	{
		if (self::$vip === true) {
			return 2;
		}
		elseif (self::$event->has_volunteers() and isset($_POST['status']) and $_POST['status'] == '1') {
			return 1;
		}
		else {
			return 0;
		}
	}
	
	static public function validate_housing_nights($nights)
	{
		if (is_array($nights)) {
			$nights = array_sum($nights);
		}
		else {
			$nights = (int) $nights;
		}
		
		if ($nights > 0) {
			return $nights;
		}
		else {
			$key = isset($_POST['housing_type_needed']) ? 'housing_needed[housing_nights]' : 'housing_provider[housing_nights]';
			self::$validation->set_error($key, 'You must specify nights for housing.');
			return false;
		}
	}
}

add_action('admin_init', 'NSEvent::admin_init');
add_action('admin_menu', 'NSEvent::admin_menu');
register_activation_hook(__FILE__, 'NSEvent::plugin_activate');
spl_autoload_register('NSEvent::autoload');
endif;
