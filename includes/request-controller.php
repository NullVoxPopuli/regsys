<?php

class RegistrationSystem_Request_Controller
{
	static public function admin_dancer_delete($event, $dancer)
	{
		if (isset($_POST['confirmed'])) {
			$database = RegistrationSystem::get_database_connection();
			
			$database->query('DELETE FROM %1$s_registrations WHERE event_id = ? AND dancer_id = ?',         array($event->id(), $dancer->id()));
			$database->query('DELETE FROM %1$s_housing       WHERE event_id = ? AND dancer_id = ? LIMIT 1', array($event->id(), $dancer->id()));
			$database->query('DELETE FROM %1$s_dancers       WHERE event_id = ? AND dancer_id = ? LIMIT 1', array($event->id(), $dancer->id()));
			
			wp_redirect(site_url('wp-admin/admin.php') . sprintf('?page=reg-sys&request=report_index_event&event_id=%d&deleted_dancer=%s', $event->id(), rawurlencode($dancer->name())));
			exit();
		}
		
		# Needed if the confirmation checkbox wasn't checked.
		if (isset($_GET['noheader'])) {
			require_once ABSPATH . 'wp-admin/admin-header.php';
		}
		
		echo RegistrationSystem::render_template('admin/dancer-delete.html', array(
			'event'  => $event,
			'dancer' => $dancer));
	}
	
	static public function admin_dancer_edit($event, $dancer)
	{
		$validation = new RegistrationSystem_Form_Validation;
		
		if (!empty($_POST)) {
			$validation->add_rules(array(
				'first_name'      => 'trim|required|max_length[100]|ucfirst',
				'last_name'       => 'trim|required|max_length[100]|ucfirst',
				'email'           => 'trim|valid_email|max_length[100]', # TODO: Update RegistrationSystem::validate_*
				'mobile_phone'    => 'trim|required|max_length[30]',
				'position'        => 'intval|in[1,2]',
				'payment_method'  => 'in[Mail,PayPal]',
				'date_registered' => 'required|strtotime',
				));
			
			if ($event->has_levels()) {
				$validation->add_rule('level_id', sprintf('intval|in[%s]',
					implode(',', array_keys($event->levels_keyed_by_id()))));
			}
			else {
				$_POST['level'] = 1;
			}
			
			if ($validation->validate()) {
				$database = RegistrationSystem::get_database_connection();
				
				$database->query('UPDATE %s_dancers SET first_name = ?, last_name = ?, email = ?, position = ?, level_id = ?, status = ?, date_registered = ?, payment_method = ?, mobile_phone = ? WHERE dancer_id = ?;', array(
					@$_POST['first_name'],
					@$_POST['last_name'],
					@$_POST['email'],
					@$_POST['position'],
					@$_POST['level_id'],
					@$_POST['status'],
					@$_POST['date_registered'],
					@$_POST['payment_method'],
					@$_POST['mobile_phone'],
					$dancer->id()));
				
				$dancer = $event->dancer_by_id($dancer->id());
			}
		}
		else {
			# Put values into POST so that form is pre-populated.
			$reflection = new ReflectionObject($dancer);
			
			if (version_compare(PHP_VERSION, '5.3', '>=')) {
				foreach ($reflection->getProperties() as $property) {
						$property->setAccessible(true);
						$_POST[$property->getName()] = $property->getValue($dancer);
				}
			}
			else {
				$temp = (array) $event;
				
				foreach ($reflection->getProperties() as $property) {
					$key = "\0RegistrationSystem_Model_Dancer\0" . $property->getName();
					$_POST[$property->getName()] = $temp[$key];
				}
				
				unset($temp);
			}
		}
		
		if (isset($_POST['date_registered']) and is_numeric($_POST['date_registered'])) {
			$_POST['date_registered'] = date('Y-m-d h:i A', $_POST['date_registered']);
		}
		
		echo RegistrationSystem::render_template('admin/dancer-edit.html', array(
			'event'      => $event,
			'dancer'     => $dancer,
			'validation' => $validation));
	}
	
	static public function admin_dancer_resend_confirmation_email($event, $dancer)
	{
		try {
			if (!$dancer->send_confirmation_email()) {
				throw new Exception('Email could not be sent to ' . $dancer->email());
			}
			
			wp_redirect(site_url('wp-admin/admin.php') . sprintf('?page=reg-sys&request=report_dancer&event_id=%d&dancer_id=%d&confirmation_email=true', $event->id(), $dancer->id()));
			exit();
		}
		catch (Exception $e) {
			if (isset($_GET['noheader'])) {
				require_once ABSPATH . 'wp-admin/admin-header.php';
			}
			
			echo $e->getMessage();
		}
	}
	
	static public function admin_dancer_registration_edit($event, $dancer)
	{
		RegistrationSystem::$validation = new RegistrationSystem_Form_Validation;
		
		if (!empty($_POST)) {
			RegistrationSystem::$validation->add_rule('items', 'RegistrationSystem::validate_items');
			
			if (RegistrationSystem::$validation->validate()) {
				$database = RegistrationSystem::get_database_connection();
				
				$additional_owed = 0;
				
				foreach (RegistrationSystem::$validated_items as $item) {
					$price = $dancer->is_vip() ? $item->price_for_vip() : $item->price_for_prereg();
					$additional_owed += $price;
					
					$event->add_registration(array(
						'dancer_id' => $dancer->id(),
						'item_id'   => $item->id(),
						'price'     => $price,
						'item_meta' => isset($_POST['item_meta'][$item->id()]) ? $_POST['item_meta'][$item->id()] : '',
						));
				}
				
				$dancer->update_payment_confirmation(false, $dancer->payment_owed() + $additional_owed);
				
				foreach ($_POST['item_meta'] as $key => $value) {
					if (isset(RegistrationSystem::$validated_items[$key])) {
						continue;
					}
					
					$database->query('UPDATE %s_registrations SET item_meta = ? WHERE item_id = ? AND dancer_id = ? AND event_id = ?;', array($value, $key, $dancer->id(), $event->id()));
				}
				
				unset($_POST['items'], $_POST['item_meta']);
			}
		}
		
		echo RegistrationSystem::render_template('admin/registration-edit.html', array(
			'event'      => $event,
			'items'      => $event->items(),
			'dancer'     => $dancer,
			'validation' => RegistrationSystem::$validation));
	}
	
	static public function admin_event_add()
	{
		# Separate method used to avoid loading non-existent event
		self::admin_event_edit(null);
	}
	
	static public function admin_event_delete($event)
	{
		if (isset($_POST['confirmed'])) {
			$database = RegistrationSystem::get_database_connection();
			
			$database->query('DELETE FROM %s_registrations WHERE event_id = ?;', array($event->id()));
			$database->query('DELETE FROM %s_housing       WHERE event_id = ?;', array($event->id()));
			$database->query('DELETE FROM %s_dancers       WHERE event_id = ?;', array($event->id()));
			
			if (isset($_GET['registrations_only']) and $_GET['registrations_only'] == 'true') {
				wp_redirect(site_url('wp-admin/admin.php') . '?page=reg-sys&request=report_index&deleted_event=' . rawurlencode($event->name) . '&registrations_only=true');
				exit();
			}
			else {
				$database->query('DELETE FROM %s_item_prices     WHERE event_id = ?;', array($event->id()));
				$database->query('DELETE FROM %s_items           WHERE event_id = ?;', array($event->id()));
				$database->query('DELETE FROM %s_event_discounts WHERE event_id = ?;', array($event->id()));
				$database->query('DELETE FROM %s_event_levels    WHERE event_id = ?;', array($event->id()));
				$database->query('DELETE FROM %s_events          WHERE event_id = ?;', array($event->id()));
				
				wp_redirect(site_url('wp-admin/admin.php') . '?page=reg-sys&request=report_index&deleted_event=' . rawurlencode($event->name));
				exit();
			}
		}
		
		# Needed if the confirmation checkbox wasn't checked.
		if (isset($_GET['noheader'])) {
			require_once ABSPATH . 'wp-admin/admin-header.php';
		}
		
		echo RegistrationSystem::render_template('admin/event-delete.html', array('event' => $event));
	}
	
	static public function admin_event_edit($event)
	{
		$validation = new RegistrationSystem_Form_Validation;
		
		if (!empty($_POST)) {
			$validation->add_rules(array(
				'name'                   => 'trim|required',
				'date_mail_prereg_end'   => 'required|strtotime',
				'date_paypal_prereg_end' => 'required|strtotime',
				'date_refund_end'        => 'if_set[date_refund_end]|strtotime',
				'has_vip'                => 'intval|in[0,1]',
				'has_volunteers'         => 'intval|in[0,1]',
				'has_housing'            => 'intval|in[0,1,2]',
				));
			
			if ($validation->validate()) {
				$database = RegistrationSystem::get_database_connection();
				
				$event = new RegistrationSystem_Model_Event($_POST);
				
				if ($_GET['request'] == 'admin_event_add') {
					$database->query('INSERT %s_events VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?);', array(
						@(string) $_POST['name'],
			 			@(int)    $_POST['date_mail_prereg_end'],
			 			@(int)    $_POST['date_paypal_prereg_end'],
			 			@(int)    $_POST['date_refund_end'],
			 			@(int)    $_POST['has_levels'],
			 			@(int)    $_POST['has_vip'],
			 			@(int)    $_POST['has_volunteers'],
			 			@(int)    $_POST['has_housing'],
			 			@(string) $_POST['housing_nights'],
						));
					
					wp_redirect(site_url('wp-admin/admin.php') . sprintf('?page=reg-sys&event_id=%d&request=admin_event_edit&added=true', $database->lastInsertID()));
					exit();
				}
				else {
					$database->query('UPDATE %s_events SET `name` = ?, date_mail_prereg_end = ?, date_paypal_prereg_end = ?, date_refund_end = ?, has_levels = ?, has_vip = ?, has_volunteers = ?, has_housing = ?, housing_nights = ? WHERE event_id = ?', array(
						$_POST['name'],
			 			$_POST['date_mail_prereg_end'],
			 			$_POST['date_paypal_prereg_end'],
			 			$_POST['date_refund_end'],
			 			@(int) $_POST['has_levels'],
			 			$_POST['has_vip'],
			 			$_POST['has_volunteers'],
			 			$_POST['has_housing'],
			 			@(string) $_POST['housing_nights'],
						$event->id(),
						));
					
					$levels = $event->levels();
					
					foreach ($_POST['edit_levels'] as $key => $value) {
						if ($value) {
							if (!isset($levels[$key])) {
								$database->query('INSERT %s_event_levels VALUES (?, ?, ?, ?);', array(
									$event->id(),
									$key,
									$value,
									isset($_POST['edit_tryouts'][$key]),
									));
							}
							elseif (isset($levels[$key])) {
								$database->query('UPDATE %s_event_levels SET label = ?, has_tryouts = ? WHERE event_id = ? AND level_id = ?', array(
									$value,
									isset($_POST['edit_tryouts'][$key]),
									$event->id(),
									$key,
									));
							}
						}
						elseif (!$value and isset($levels[$key])) {
							$database->query('DELETE FROM %s_event_levels WHERE event_id = ? AND level_id = ?', array(
								$event->id(),
								$key,
								));
						}
					}
					
					$event->unset_levels();
					unset($_POST['edit_tryouts'], $levels);
					
					$discounts = $event->discounts();
					
					foreach ($_POST['edit_discount_code'] as $key => $value) {
						if ($value) {
							if (!isset($discounts[$key])) {
								$database->query('INSERT %s_event_discounts VALUES (?, ?, ?, ?, ?);', array(
									$event->id(),
									$key,
									$value,
									(int) $_POST['edit_discount_amount'][$key],
									(int) $_POST['edit_discount_limit'][$key],
									));
							}
							elseif (isset($discounts[$key])) {
								$database->query('UPDATE %s_event_discounts SET discount_code = ?, discount_amount = ?, discount_limit = ? WHERE event_id = ? AND discount_id = ?', array(
									$value,
									(int) $_POST['edit_discount_amount'][$key],
									(int) $_POST['edit_discount_limit'][$key],
									$event->id(),
									$key,
									));
							}
						}
						elseif (!$value and isset($discounts[$key])) {
							$database->query('DELETE FROM %s_event_discounts WHERE event_id = ? AND discount_id = ?', array(
								$event->id(),
								$key,
								));
						}
					}
					
					$event->unset_discounts();
					unset($_POST['edit_discount_code'], $_POST['edit_discount_amount'], $_POST['edit_discount_limit'], $discounts);
				}
			}
		}
		elseif (isset($event)) {
			# Put values into POST so that form is pre-populated.
			$reflection = new ReflectionObject($event);
			
			if (version_compare(PHP_VERSION, '5.3', '>=')) {
				foreach ($reflection->getProperties() as $property) {
						$property->setAccessible(true);
						$_POST[$property->getName()] = $property->getValue($event);
				}
			}
			else {
				$temp = (array) $event;
				
				foreach ($reflection->getProperties() as $property) {
					$key = "\0RegistrationSystem_Model_Event\0" . $property->getName();
					$_POST[$property->getName()] = $temp[$key];
				}
				
				unset($temp);
			}
		}
		
		# Format dates for display
		foreach ($_POST as $key => $value) {
			if (in_array($key, array('date_mail_prereg_end', 'date_paypal_prereg_end', 'date_refund_end'))) {
				if (empty($value)) {
					unset($_POST[$key]);
				}
				elseif (is_numeric($value)) {
					$_POST[$key] = date('Y-m-d h:i A', $value);
				}
			}
		}
		unset($key, $value);
		
		# Needed if there are validations errors when adding an event.
		if (isset($_GET['noheader'])) {
			require_once ABSPATH . 'wp-admin/admin-header.php';
		}
		
		echo RegistrationSystem::render_template('admin/event-edit.html', array(
			'event'      => $event,
			'validation' => $validation,
			'vip_href'   => get_permalink(get_page_by_path('register')) . '?vip'));
	}
	
	static public function report_competitions($event)
	{
		echo RegistrationSystem::render_template('reports/competitions.html', array(
			'event' => $event,
			'items' => $event->items_where(array(':type' => 'competition'))));
	}
	
	static public function report_dancer($event, $dancer)
	{
		echo RegistrationSystem::render_template('reports/dancer.html', array(
			'event'  => $event,
			'dancer' => $dancer));
	}
	
	static public function report_dancers($event)
	{
		echo RegistrationSystem::render_template('reports/dancers.html', array(
			'event'   => $event,
			'dancers' => $event->dancers()));
	}
	
	static public function report_discounts($event)
	{
		$database = RegistrationSystem::get_database_connection();
		
		$dancers = $database->query('SELECT * FROM %s_dancers WHERE event_id = ? AND discount_id IS NOT NULL ORDER BY discount_id ASC, last_name ASC, first_name ASC', array($event->id()))->fetchAll(PDO::FETCH_CLASS, 'RegistrationSystem_Model_Dancer');
		
		echo RegistrationSystem::render_template('reports/discounts.html', array(
			'event'   => $event,
			'dancers' => $dancers));
	}
	
	static public function report_download_csv($event)
	{
		if (!isset($_GET['data']) or !in_array($_GET['data'], array('competitions', 'dancers', 'housing_needed', 'housing_providers', 'volunteers'))) {
			throw new Exception('Unable to handle to data for: ' . $_GET['data']);
		}
		
		$rows = array();
		
		if ($_GET['data'] == 'housing_needed') {
			$filename = 'Housing Needed';
			
			$rows[0] = array('Last Name', 'First Name', 'Email Address');
			$rows[0] = array_merge($rows[0], $event->housing_nights());
			$rows[0] = array_merge($rows[0], array(
				'Gender',
				'No Pets',
				'No Smoke',
				'Bedtime',
				'From',
				'Comment',
				'Date Registered'));
			
			$dancers = $event->dancers_where(array(':housing_type' => 1));
			
			foreach ($dancers as $dancer) {
				$row = array($dancer->last_name, $dancer->first_name, $dancer->email);
				
				foreach ($event->housing_nights() as $night) {
					$row[] = in_array($night, $dancer->housing_nights()) ? '•' : '';
				}
				
				$row[] = $dancer->housing_gender();
				$row[] = $dancer->housing_prefers_no_pets()  ? '•' : '';
				$row[] = $dancer->housing_prefers_no_smoke() ? '•' : '';
				$row[] = $dancer->housing_bedtime();
				$row[] = $dancer->housing_from_scene;
				$row[] = $dancer->housing_comment;
				$row[] = date('Y-m-d, h:i A', $dancer->date_registered());
				
				$rows[] = $row;
			}
		}
		elseif ($_GET['data'] == 'housing_providers') {
			$filename = 'Housing Providers';
			
			$rows[0] = array('Last Name', 'First Name', 'Email Address');
			$rows[0] = array_merge($rows[0], $event->housing_nights());
			$rows[0] = array_merge($rows[0], array(
				'Gender',
				'Spots',
				'Has Pets',
				'Smokes',
				'Bedtime',
				'Comment',
				'Date Registered'));
			
			$dancers = $event->dancers_where(array(':housing_type' => 2));
			
			foreach ($dancers as $dancer) {
				$row = array($dancer->last_name, $dancer->first_name, $dancer->email);
				
				foreach ($event->housing_nights() as $night) {
					$row[] = in_array($night, $dancer->housing_nights()) ? '•' : '';
				}
				
				$row[] = $dancer->housing_gender();
				$row[] = $dancer->housing_spots_available();
				$row[] = $dancer->housing_has_pets()  ? '•' : '';
				$row[] = $dancer->housing_has_smoke() ? '•' : '';
				$row[] = $dancer->housing_bedtime();
				$row[] = $dancer->housing_comment;
				$row[] = date('Y-m-d, h:i A', $dancer->date_registered());
				
				$rows[] = $row;
			}
		}
		else {
			if ($_GET['data'] == 'competitions') {
				$filename = 'Competitors';
				$database = RegistrationSystem::get_database_connection();
				$dancers  = $database->query('SELECT DISTINCT %1$s_dancers.`dancer_id` as dancer_id, last_name, first_name, email FROM %1$s_registrations LEFT JOIN %1$s_items USING(item_id) LEFT JOIN %1$s_dancers USING(dancer_id) WHERE %1$s_registrations.`event_id` = :event_id AND %1$s_items.`type` = "competition" ORDER BY %1$s_dancers.`last_name` ASC, %1$s_dancers.`first_name` ASC', array(':event_id' => $event->id()))->fetchAll(PDO::FETCH_CLASS, 'RegistrationSystem_Model_Dancer');
			}
			elseif ($_GET['data'] == 'dancers') {
				$filename = 'Dancers';
				$dancers  = $event->dancers();
			}
			elseif ($_GET['data'] == 'volunteers') {
				$filename = 'Volunteers';
				$dancers  = $event->dancers_where(array(':status' => 1));
			}
			
			$rows[0] = array('Last Name', 'First Name', 'Email Address');
			
			foreach ($dancers as $dancer) {
				$rows[] = array($dancer->last_name, $dancer->first_name, $dancer->email);
			}
		}
		
		$output = fopen('php://output', 'w');
		
		if (!$output) {
			throw new Exception('Unable to open output file.');
		}
		
		$filename .= sprintf(' for %s - %s.csv', $event->name, date('Y-m-d'));
		
		header('Content-Type: text/csv');
		header(sprintf('Content-Disposition: attachment; filename="%s"', $filename));
		header('Pragma: no-cache');
		header('Expires: 0');
		
		foreach ($rows as $row) {
			fputcsv($output, $row);
		}
		
		exit;
	}
	
	static public function report_index()
	{
		echo RegistrationSystem::render_template('reports/index.html', array('events' => RegistrationSystem_Model_Event::get_events()));
	}
	
	static public function report_index_event($event)
	{
		echo RegistrationSystem::render_template('reports/index-event.html', array('event' => $event));
	}
	
	static public function report_housing_needed($event)
	{
		$dancers = $event->dancers_where(array(':housing_type' => 1));
		
		echo RegistrationSystem::render_template('reports/housing.html', array(
			'event'         => $event,
			'dancers'       => $dancers,
			'housing_count' => count($dancers),
			'housing_type'  => 'Housing Needed',
			'housing_href'  => 'housing_needed'));
	}
	
	static public function report_housing_providers($event)
	{
		echo RegistrationSystem::render_template('reports/housing.html', array(
			'event'         => $event,
			'dancers'       => $event->dancers_where(array(':housing_type' => 2)),
			'housing_count' => $event->count_housing_spots_available(),
			'housing_type'  => 'Housing Providers',
			'housing_href'  => 'housing_providers'));
	}
	
	static public function report_money($event)
	{
		echo RegistrationSystem::render_template('reports/money.html', array(
			'event'   => $event,
			'dancers' => $event->dancers(),
			'items'   => $event->items()));
	}
	
	static public function report_numbers($event)
	{
		$database = RegistrationSystem::get_database_connection();
		
		# Dancers
		$lists['Dancers']['Total']   = $event->count_dancers();
		$lists['Dancers']['Leads']   = $event->count_dancers(array(':position' => 1));
		$lists['Dancers']['Follows'] = $event->count_dancers(array(':position' => 2));
		$lists['Dancers']['Ratio']   = @round($lists['Dancers']['Follows'] / $lists['Dancers']['Leads'], 2);
		
		if ($event->has_discounts()) {
			foreach ($event->discounts() as $d) {
				$lists['Discounts'][$d->discount_code] = $event->count_discounts_used($d->discount_code);
				
				if ($d->discount_limit) {
					$lists['Discounts'][$d->discount_code] .= ' of ' . $d->discount_limit;
				}
			}
		}
		
		# Levels
		if ($event->has_levels()) {
			foreach ($event->levels() as $level) {
				$lists['Levels (All Dancers)'][$level->label] = $event->count_dancers(array(':level_id' => $level->level_id));
				
				$lists['Levels (Dancers in Classes)'][$level->label] = sprintf('%d leads, %d follows',
					$database->query('SELECT COUNT(dancer_id) FROM %1$s_registrations LEFT JOIN %1$s_dancers USING(dancer_id) LEFT JOIN %1$s_items USING(item_id) WHERE %1$s_registrations.`event_id` = ? AND %1$s_dancers.`level_id` = ? AND %1$s_dancers.`position` = ? AND %1$s_items.`meta` = "count_for_classes"', array($event->id(), $level->level_id, 1))->fetchColumn(),
					$database->query('SELECT COUNT(dancer_id) FROM %1$s_registrations LEFT JOIN %1$s_dancers USING(dancer_id) LEFT JOIN %1$s_items USING(item_id) WHERE %1$s_registrations.`event_id` = ? AND %1$s_dancers.`level_id` = ? AND %1$s_dancers.`position` = ? AND %1$s_items.`meta` = "count_for_classes"', array($event->id(), $level->level_id, 2))->fetchColumn());
			}
			
			$lists['Levels (All Dancers)'] = array_filter($lists['Levels (All Dancers)']);
		}
		
		# Packages
		$lists['Packages'] = array();
		$packages = $event->items_where(array(':preregistration' => 1, ':type' => 'package'));
		foreach ($packages as $item) {
			$lists['Packages'][$item->name] = $database->query('SELECT COUNT(dancer_id) FROM %1$s_registrations LEFT JOIN %1$s_dancers USING(dancer_id) WHERE %1$s_registrations.`item_id` = :item_id AND %1$s_dancers.`status` != 2', array(':item_id' => $item->id()))->fetchColumn();
			
			if ($event->has_vip()) {
				$vip_count = $database->query('SELECT COUNT(dancer_id) FROM %1$s_registrations LEFT JOIN %1$s_dancers USING(dancer_id) WHERE %1$s_registrations.`item_id` = :item_id AND %1$s_dancers.`status` = 2', array(':item_id' => $item->id()))->fetchColumn();
				
				if ($vip_count) {
					$lists['Packages'][$item->name] .= sprintf(' (+%d %s)', $vip_count, _n('VIP', 'VIPs', $vip_count));
				}
			}
		}
		$lists['Packages'] = array_filter($lists['Packages']);
		
		# Shirts
		$shirts = $event->items_where(array(':preregistration' => 1, ':type' => 'shirt'));
		foreach ($shirts as $item) {
			$header_key = sprintf('%s (%d)', $item->name, $event->count_registrations_where(array(':item_id' => $item->id())));
			
			foreach (explode(',', $item->description) as $size) {
				$lists[$header_key][ucfirst($size)] = $event->count_registrations_where(array(':item_id' => $item->id(), ':item_meta' => $size));
			}
			
			$lists[$header_key] = array_filter($lists[$header_key]);
		}
		
		echo RegistrationSystem::render_template('reports/numbers.html', array(
			'event' => $event,
			'lists' => $lists));
	}
	
	static public function report_packet_printout($event)
	{
		echo RegistrationSystem::render_template('reports/packet-printout.html', array(
			'event'   => $event,
			'dancers' => $event->dancers()));
	}
	
	static public function report_reg_list($event)
	{
		if (isset($_GET['vip_only']) and $_GET['vip_only'] == 'true') {
			$dancers = $event->dancers_where(array(':status' => 2));
		}
		else {
			$dancers = $event->dancers_where(array(':status' => 2), false); # false = not equal to 2
		}
		
		if (!empty($_POST)) {
			foreach ($dancers as $dancer) {
				$dancer->update_payment_confirmation(
					(int) isset($_POST['payment_confirmed'][$dancer->id()]),
					(int) isset($_POST['payment_owed'][$dancer->id()]) ? $_POST['payment_owed'][$dancer->id()] : $dancer->payment_owed());
			}
		}
		
		echo RegistrationSystem::render_template('reports/reg-list.html', array(
			'event' => $event,
			'dancers' => $dancers));
	}
	
	static public function report_volunteers($event)
	{
		echo RegistrationSystem::render_template('reports/volunteers.html', array(
			'event'      => $event,
			'volunteers' => $event->dancers_where(array(':status' => 1))));
	}
}
