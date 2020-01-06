<?php
Configure::set("namecheap.map", array(
	'module' => "namecheap",
	'module_row_key' => "api_username",
	'module_row_meta' => array(
		(object)array('key' => "user", 'value' => (object)array('module' => "api_username"), 'serialized' => 0, 'encrypted' => 0),		
		(object)array('key' => "key", 'value' => (object)array('module' => "api_key"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "sandbox", 'value' => (object)array('module' => "testmode"), 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "type", 'value' => "domain", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "ns", 'value' => (object)array('module' => "ns1"), 'serialized' => 1, 'encrypted' => 0)
	),
	'service_fields' => array(
		'user1' => (object)array('key' => "DomainName", 'serialized' => 0, 'encrypted' => 0),
		'user2' => null,
		'pass' => null,
		'opt1' => null,
		'opt2' => null
	),
	'package_tags' => array(
		'[domain]' => "{service.DomainName}"
	)
));
?>