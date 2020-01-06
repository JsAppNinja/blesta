<?php
Configure::set("directadmin.map", array(
	'module' => "direct_admin",
	'module_row_key' => "serverip",
	'module_row_meta' => array(
		(object)array('key' => "server_name", 'value' => (object)array('module' => "serverip"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "host_name", 'value' => (object)array('module' => "serverip"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "user_name", 'value' => (object)array('module' => "user"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "password", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "use_ssl", 'value' => (object)array('module' => "usessl"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "account_limit", 'value' => (object)array('module' => "max"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "name_servers", 'value' =>null, 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "notes", 'value' => null, 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "type", 'value' => "user", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "package", 'value' => (object)array('package' => "instantact"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "ip", 'value' => (object)array('module' => "serverip"), 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'user1' => (object)array('key' => "direct_admin_domain", 'serialized' => 0, 'encrypted' => 0),
		'user2' => (object)array('key' => "direct_admin_username", 'serialized' => 0, 'encrypted' => 0),
		'pass' => (object)array('key' => "direct_admin_password", 'serialized' => 0, 'encrypted' => 1),
		'opt1' => (object)array('key' => "direct_admin_email", 'serialized' => 0, 'encrypted' => 0),
		'opt2' => null
	),
	'package_tags' => array(
		'[domain]' => "{service.direct_admin_domain}",
		'[username]' => "{service.direct_admin_username}",
		'[pass]' => "{service.direct_admin_password}",
		'[serverip]' => "{package.ip}",
		'[term]' => "{pricing.term}"
	)
));
?>