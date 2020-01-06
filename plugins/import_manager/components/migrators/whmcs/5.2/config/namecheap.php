<?php
Configure::set("namecheap.map", array(
	'module' => "namecheap",
	'module_row_key' => "username",
	'module_row_meta' => array(
		(object)array('key' => "user", 'value' => (object)array('module' => "username"), 'serialized' => 0, 'encrypted' => 0),		
		(object)array('key' => "key", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "sandbox", 'value' => (object)array('module' => "testmode"), 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "type", 'value' => "domain", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "ns", 'value' => null, 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "tlds", 'value' => (object)array('package' => "tlds"), 'serialized' => 1, 'encrypted' => 0)
	),
	'service_fields' => array(
		'domain' => (object)array('key' => "DomainName", 'serialized' => 0, 'encrypted' => 0)
	)
));
?>