<?php
Configure::set("cpanel.map", array(
	'module' => "cpanel",
	'module_row_key' => "hostn",
	'module_row_meta' => array(
		(object)array('key' => "host_name", 'value' => (object)array('module' => "hostn"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "user_name", 'value' => (object)array('module' => "user"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "key", 'value' => (object)array('module' => "remotekey"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "use_ssl", 'value' => (object)array('module' => "usessl"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "account_count", 'value' => (object)array('module' => "cur"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "account_limit", 'value' => (object)array('module' => "max"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "name_servers", 'value' => null, 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "notes", 'value' => null, 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "server_name", 'value' => (object)array('module' => "hostn"), 'serialized' => 0, 'encrypted' => 0),
	),
	'package_meta' => array(
		(object)array('key' => "package", 'value' => (object)array('package' => "instantact"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "type", 'value' => "standard", 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'user1' => (object)array('key' => "cpanel_domain", 'serialized' => 0, 'encrypted' => 0),
		'user2' => (object)array('key' => "cpanel_username", 'serialized' => 0, 'encrypted' => 0),
		'pass' => (object)array('key' => "cpanel_password", 'serialized' => 0, 'encrypted' => 1),
		'opt1' => null,
		'opt2' => null
	),
	'package_tags' => array(
		'[domain]' => "{service.cpanel_domain}",
		'[username]' => "{service.cpanel_username}",
		'[pass]' => "{service.cpanel_password}",
		'[server]' => "{module.host_name}",
		'[term]' => "{pricing.term}"
	)
));
?>