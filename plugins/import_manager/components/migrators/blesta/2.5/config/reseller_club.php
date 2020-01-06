<?php
Configure::set("reseller_club.map", array(
	'module' => "logicboxes",
	'module_row_key' => "resellerid",
	'module_row_meta' => array(
		(object)array('key' => "registrar", 'value' => "ResellerClub", 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "reseller_id", 'value' => (object)array('module' => "resellerid"), 'serialized' => 0, 'encrypted' => 0),
		(object)array('key' => "key", 'value' => (object)array('module' => "password"), 'serialized' => 0, 'encrypted' => 1),
		(object)array('key' => "sandbox", 'value' => (object)array('module' => "testmode"), 'serialized' => 0, 'encrypted' => 0)
	),
	'package_meta' => array(
		(object)array('key' => "ns", 'value' => array(), 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "tlds", 'value' => array(), 'serialized' => 1, 'encrypted' => 0),
		(object)array('key' => "type", 'value' => "domain", 'serialized' => 0, 'encrypted' => 0)
	),
	'service_fields' => array(
		'user1' => (object)array('key' => "domain-name", 'serialized' => 0, 'encrypted' => 0),
		'user2' => null,
		'pass' => null,
		'opt1' => null,
		'opt2' => null
	),
	'package_tags' => array(
		'[domain]' => "{service.domain-name}"
	)	
));
?>