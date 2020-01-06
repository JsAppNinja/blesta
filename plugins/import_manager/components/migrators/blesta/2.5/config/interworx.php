<?php
Configure::set("interworx.map", array(
	'module' => "interworx",
	'module_row_key' => "hostn",
	'module_row_meta' => array(
		(object)array('key' => "server_name", 'value' => (object)array('module' => "hostn"), 'serialized' => 0, 'encrypted' => 0),		
		(object)array('key' => "host_name", 'value' => (object)array('module' => "hostn"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "key", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "use_ssl", 'value' => (object)array('module' => "usessl"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "port", 'value' => "2443", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "account_count", 'value' => (object)array('module' => "cur"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "account_limit", 'value' => (object)array('module' => "max"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "debug", 'value' => "none", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "name_servers", 'value' => null, 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "notes", 'value' => null, 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "package", 'value' => (object)array('package' => "instantact"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "type", 'value' => "standard", 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'user1' => (object)array('key' => "interworx_domain", 'serialized' => 0, 'encrypted' => 0),
		'user2' => (object)array('key' => "interworx_username", 'serialized' => 0, 'encrypted' => 0),
		'pass' => (object)array('key' => "interworx_password", 'serialized' => 0, 'encrypted' => 1),
		'opt1' => null,
		'opt2' => null
	),
	'package_tags' => array(
		'[domain]' => "{service.interworx_domain}",
		'[username]' => "{service.interworx_username}",
		'[pass]' => "{service.interworx_password}",
		'[server]' => "{module.host_name}",
		'[term]' => "{pricing.term}"
	)
));
?>