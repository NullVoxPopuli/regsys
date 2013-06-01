<?php

$total  = $database->fetchColumn('SELECT SUM(price) FROM regsys_registrations WHERE event_id = ?', array($event->id()));
$total += $database->fetchColumn('SELECT SUM(paypal_fee) FROM regsys_dancers  WHERE event_id = ?', array($event->id()));

$groups = array('Mail' => array(), 'PayPal' => array());
foreach ($event->dancers() as $dancer) {
	$groups[$dancer->payment_method][] = $dancer;
}

echo self::render_template('report-money.html', array(
	'event'  => $event,
	'groups' => $groups,
	'items'  => $event->items(),
	'total'  => $total));
