<?php
Configure::set("plesk9.map", array(
	'module' => "plesk",
	'module_row_key' => "hostname",
	'module_row_meta' => array(
		(object)array('key' => "server_name", 'value' => (object)array('module' => "hostname"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "ip_address", 'value' => (object)array('module' => "ipaddress"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "port", 'value' => "8443", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "username", 'value' => (object)array('module' => "username"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "password", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "account_limit", 'value' => (object)array('module' => "maxaccounts"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "panel_version", 'value' => "9", 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "plan", 'value' => (object)array('package' => "configoption1"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "type", 'value' => "standard", 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'domain' => (object)array('key' => "plesk_domain", 'serialized' => 0, 'encrypted' => 0),
		'username' => (object)array('key' => "plesk_username", 'serialized' => 0, 'encrypted' => 0),
		'password' => (object)array('key' => "plesk_password", 'serialized' => 0, 'encrypted' => 1)
	)
));
?>