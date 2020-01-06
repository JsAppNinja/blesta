<?php
Configure::set("netearthone.map", array(
	'module' => "logicboxes",
	'module_row_key' => "username",
	'module_row_meta' => array(
		(object)array('key' => "registrar", 'value' => "NetEarthOne", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "reseller_id", 'value' => (object)array('module' => "resellerid"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "key", 'value' => (object)array('module' => "apikey"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "sandbox", 'value' => (object)array('module' => "testmode"), 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "ns", 'value' => array(), 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "tlds", 'value' => (object)array('package' => "tlds"), 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "type", 'value' => "domain", 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'domain' => (object)array('key' => "domain-name", 'serialized' => 0, 'encrypted' => 0)
	)	
));
?>