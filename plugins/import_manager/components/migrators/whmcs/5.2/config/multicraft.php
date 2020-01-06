<?php
Configure::set("multicraft.map", array(
	'module' => "multicraft",
	'module_row_key' => "server_name",
	'module_row_meta' => array(
		(object)array('key' => "server_name", 'value' => (object)array('module' => "name"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "username", 'value' => (object)array('module' => "username"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "key", 'value' => (object)array('module' => "accesshash"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "log_all", 'value' => "0", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "panel_url", 'value' => (object)array('module' => "hostname"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "panel_api_url", 'value' => "", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "daemons", 'value' => array(), 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "ips", 'value' => array(), 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "ips_in_use", 'value' => array(), 'serialized' => 1, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "autostart", 'value' => "1", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "create_ftp", 'value' => "0", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "default_level", 'value' => "10", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "jardir", 'value' => "", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "jarfile", 'value' => (object)array('package' => "configoption3"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "memory", 'value' => (object)array('package' => "configoption2"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "players", 'value' => (object)array('package' => "configoption1"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "server_name", 'value' => "Minecraft Server", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "server_visibility", 'value' => "1", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "user_ftp", 'value' => "0", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "user_jar", 'value' => "0", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "user_name", 'value' => "1", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "user_schedule", 'value' => "1", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "user_visibility", 'value' => "1", 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'domain' => (object)array('key' => "multicraft_server_id", 'serialized' => 0, 'encrypted' => 0),
		'username' => (object)array('key' => "multicraft_login_username", 'serialized' => 0, 'encrypted' => 0),
		'password' => (object)array('key' => "multicraft_login_password", 'serialized' => 0, 'encrypted' => 1)
	)
));
?>