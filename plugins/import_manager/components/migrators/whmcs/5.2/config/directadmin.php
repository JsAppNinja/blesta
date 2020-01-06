<?php
Configure::set("directadmin.map", array(
	'module' => "direct_admin",
	'module_row_key' => "hostname",
	'module_row_meta' => array(
		(object)array('key' => "server_name", 'value' => (object)array('module' => "hostname"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "host_name", 'value' => (object)array('module' => "hostname"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "user_name", 'value' => (object)array('module' => "username"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "password", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "use_ssl", 'value' => (object)array('module' => "secure"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "account_limit", 'value' => (object)array('module' => "maxaccounts"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "name_servers", 'value' =>null, 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "notes", 'value' => null, 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "type", 'value' => "user", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package", 'value' => (object)array('package' => "configoption1"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "ip", 'value' => (object)array('package' => "configoption2"), 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'domain' => (object)array('key' => "direct_admin_domain", 'serialized' => 0, 'encrypted' => 0),
		'username' => (object)array('key' => "direct_admin_username", 'serialized' => 0, 'encrypted' => 0),
		'password' => (object)array('key' => "direct_admin_password", 'serialized' => 0, 'encrypted' => 1)
	)
));
?>