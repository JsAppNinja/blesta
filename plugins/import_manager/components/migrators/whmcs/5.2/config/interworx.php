<?php
Configure::set("interworx.map", array(
	'module' => "interworx",
	'module_row_key' => "hostname",
	'module_row_meta' => array(
		(object)array('key' => "server_name", 'value' => (object)array('module' => "hostname"), 'serialized' => 0, 'encrypted' => 0),		
		(object)array('key' => "host_name", 'value' => (object)array('module' => "hostname"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "key", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "use_ssl", 'value' => (object)array('module' => "secure"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "port", 'value' => "2443", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "account_limit", 'value' => (object)array('module' => "maxaccounts"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "debug", 'value' => "none", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "name_servers", 'value' => null, 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "notes", 'value' => null, 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "package", 'value' => (object)array('package' => "configoption1"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "type", 'value' => "standard", 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'domain' => (object)array('key' => "interworx_domain", 'serialized' => 0, 'encrypted' => 0),
		'username' => (object)array('key' => "interworx_username", 'serialized' => 0, 'encrypted' => 0),
		'password' => (object)array('key' => "interworx_password", 'serialized' => 0, 'encrypted' => 1)
	)
));
?>