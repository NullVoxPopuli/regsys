<?php
/*
Template Name: NSEvent Registration Form
*/

if (!class_exists('NSEvent'))
{
	@header('HTTP/1.1 500 Internal Server Error');
	exit(__('NSEvent plugin is not active.', 'nsevent'));
}
NSEvent::registration_head();
NSEvent::registration_form();
