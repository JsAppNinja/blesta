<?php
// All available TLDs (http://www.logicboxes.com/product_pricing#domain-resellers)
Configure::set("Logicboxes.tlds", array(
	".co.nz",
	".net.nz",
	".org.nz",
	".com.ru",
	".net.ru",
	".org.ru",
	".com.cn",
	".net.cn",
	".org.cn",
	".com.au",
	".net.au",
	".com.de",
	".co.in",
	".net.in",
	".org.in",
	".gen.in",
	".firm.in",
	".ind.in",
	".com.co",
	".net.co",
	".nom.co",
	".co.uk",
	".org.uk",
	".me.uk",
	".ae.org",
	".de.com",
	".eu.com",
	".gr.com",
	".kr.com",
	".qc.com",
	".us.com",
	".cn.com",
	".br.com",
	".gb.com",
	".gb.net",
	".hu.com",
	".jpn.com",
	".no.com",
	".ru.com",
	".sa.com",
	".se.com",
	".se.net",
	".uk.com",
	".uk.net",
	".uy.com",
	".za.com",
	".com",
	".net",
	".org",
	".info",
	".biz",
	".name",
	".asia",
	".tel",
	".mobi",
	".ws",
	".de",
	".es",
	".ru",
	".cn",
	".xxx",
	".pro",
	".ca",
	".eu",
	".us",
	".pw",
	".nl",
	".me",
	".sx",
	".tv",
	".cc",
	".in",
	".co",
	".mn",
	".bz",
	".coop",
));

// Transfer fields
Configure::set("Logicboxes.transfer_fields", array(
	'domain-name' => array(
		'label' => Language::_("Logicboxes.transfer.domain-name", true),
		'type' => "text"
	),
	'auth-code' => array(
		'label' => Language::_("Logicboxes.transfer.auth-code", true),
		'type' => "text"
	)
));

// Domain fields
Configure::set("Logicboxes.domain_fields", array(
	'domain-name' => array(
		'label' => Language::_("Logicboxes.domain.domain-name", true),
		'type' => "text"
	)
));

// Nameserver fields
Configure::set("Logicboxes.nameserver_fields", array(
	'ns1' => array(
		'label' => Language::_("Logicboxes.nameserver.ns1", true),
		'type' => "text"
	),
	'ns2' => array(
		'label' => Language::_("Logicboxes.nameserver.ns2", true),
		'type' => "text"
	),
	'ns3' => array(
		'label' => Language::_("Logicboxes.nameserver.ns3", true),
		'type' => "text"
	),
	'ns4' => array(
		'label' => Language::_("Logicboxes.nameserver.ns4", true),
		'type' => "text"
	),
	'ns5' => array(
		'label' => Language::_("Logicboxes.nameserver.ns5", true),
		'type' => "text"
	)
));

// Contact fields
Configure::set("Logicboxes.contact_fields", array(
	'customer-id' => array(
		'label' => Language::_("Logicboxes.contact.customer-id", true),
		'type' => "text"
	),
	'type' => array(
		'label' => Language::_("Logicboxes.contact.type", true),
		'type' => "text"
	),
	'name' => array(
		'label' => Language::_("Logicboxes.contact.name", true),
		'type' => "text"
	),
	'company' => array(
		'label' => Language::_("Logicboxes.contact.company", true),
		'type' => "text"
	),
	'email' => array(
		'label' => Language::_("Logicboxes.contact.email", true),
		'type' => "text"
	),
	'address-line-1' => array(
		'label' => Language::_("Logicboxes.contact.address-line-1", true),
		'type' => "text"
	),
	'address-line-2' => array(
		'label' => Language::_("Logicboxes.contact.address-line-2", true),
		'type' => "text"
	),
	'city' => array(
		'label' => Language::_("Logicboxes.contact.city", true),
		'type' => "text"
	),
	'state' => array(
		'label' => Language::_("Logicboxes.contact.state", true),
		'type' => "text"
	),
	'zipcode' => array(
		'label' => Language::_("Logicboxes.contact.zipcode", true),
		'type' => "text"
	),
	'country' => array(
		'label' => Language::_("Logicboxes.contact.country", true),
		'type' => "text"
	),
	'phone-cc' => array(
		'label' => Language::_("Logicboxes.contact.phone-cc", true),
		'type' => "text"
	),
	'phone' => array(
		'label' => Language::_("Logicboxes.contact.phone", true),
		'type' => "text"
	),
	'fax-cc' => array(
		'label' => Language::_("Logicboxes.contact.fax-cc", true),
		'type' => "text"
	),
	'fax' => array(
		'label' => Language::_("Logicboxes.contact.fax", true),
		'type' => "text"
	)
));

// Customer info
Configure::set("Logicboxes.customer_fields", array(
	'username' => array(
		'label' => Language::_("Logicboxes.customer.username", true),
		'type' => "text"
	),
	'passwd' => array(
		'label' => Language::_("Logicboxes.customer.passwd", true),
		'type' => "text"
	),
	'lang-pref' => array(
		'label' => Language::_("Logicboxes.customer.lang-pref", true),
		'type' => "text"
	)
));


// .ASIA
Configure::set("Logicboxes.contact_fields.asia", array(
	'attr_locality' => array(
		'type' => "hidden",
		'options' => null
	),
	'attr_legalentitytype' => array(
		'label' => Language::_("Logicboxes.contact.legalentitytype", true),
		'type' => "select",
		'options' => array(
			'corporation' => Language::_("Logicboxes.contact.legalentitytype.corporation", true),
			'cooperative' => Language::_("Logicboxes.contact.legalentitytype.cooperative", true),
			'partnership' => Language::_("Logicboxes.contact.legalentitytype.partnership", true),
			'government' => Language::_("Logicboxes.contact.legalentitytype.government", true),
			'politicalParty' => Language::_("Logicboxes.contact.legalentitytype.politicalParty", true),
			'society' => Language::_("Logicboxes.contact.legalentitytype.society", true),
			'institution' => Language::_("Logicboxes.contact.legalentitytype.institution", true),
			'naturalPerson' => Language::_("Logicboxes.contact.legalentitytype.naturalPerson", true)
		)
	),
	'attr_identform' => array(
		'label' => Language::_("Logicboxes.contact.identform", true),
		'type' => "select",
		'options' => array(
			'certificate' => Language::_("Logicboxes.contact.identform.certificate", true),
			'legislation' => Language::_("Logicboxes.contact.identform.legislation", true),
			'societyRegistry' => Language::_("Logicboxes.contact.identform.societyRegistry", true),
			'politicalPartyRegistry' => Language::_("Logicboxes.contact.identform.politicalPartyRegistry", true),
			'passport' => Language::_("Logicboxes.contact.identform.passport", true)
		)
	),
	'attr_identnumber' => array(
		'label' => Language::_("Logicboxes.contact.identnumber", true),
		'type' => "text"
	)
));

// .CA
Configure::set("Logicboxes.contact_fields.ca", array(
	'attr_CPR' => array(
		'label' => Language::_("Logicboxes.contact.CPR", true),
		'type' => "select",
		'options' => array(
			'CCO' => Language::_("Logicboxes.contact.CPR.cco", true),
			'CCT' => Language::_("Logicboxes.contact.CPR.cct", true),
			'RES' => Language::_("Logicboxes.contact.CPR.res", true),
			'GOV' => Language::_("Logicboxes.contact.CPR.gov", true),
			'EDU' => Language::_("Logicboxes.contact.CPR.edu", true),
			'ASS' => Language::_("Logicboxes.contact.CPR.ass", true),
			'HOP' => Language::_("Logicboxes.contact.CPR.hop", true),
			'PRT' => Language::_("Logicboxes.contact.CPR.prt", true),
			'TDM' => Language::_("Logicboxes.contact.CPR.tdm", true),
			'TRD' => Language::_("Logicboxes.contact.CPR.trd", true),
			'PLT' => Language::_("Logicboxes.contact.CPR.plt", true),
			'LAM' => Language::_("Logicboxes.contact.CPR.lam", true),
			'TRS' => Language::_("Logicboxes.contact.CPR.trs", true),
			'ABO' => Language::_("Logicboxes.contact.CPR.abo", true),
			'INB' => Language::_("Logicboxes.contact.CPR.inb", true),
			'LGR' => Language::_("Logicboxes.contact.CPR.lgr", true),
			'OMK' => Language::_("Logicboxes.contact.CPR.omk", true),
			'MAJ' => Language::_("Logicboxes.contact.CPR.maj", true)
		)
	),
	'attr_AgreementVersion' => array(
		'type' => "hidden",
		'options' => "2.0"
	),
	'attr_AgreementValue' => array(
		'type' => "hidden",
		'options' => "y"
	)
));

// .COOP
Configure::set("Logicboxes.contact_fields.coop", array(
	'attr_sponsor' => array(
		'label' => Language::_("Logicboxes.contact.sponsor", true),
		'type' => "text"
	)
));

// .ES
Configure::set("Logicboxes.contact_fields.es", array(
	'attr_es_form_juridica' => array(
		'type' => "hidden",
		'options' => "1"
		/*
		'label' => Language::_("Logicboxes.contact.es_form_juridica", true),
		'type' => "select",
		'options' => array(
			'1' => Language::_("Logicboxes.contact.es_form_juridica.1", true),
			'39' => Language::_("Logicboxes.contact.es_form_juridica.39", true),
			'47' => Language::_("Logicboxes.contact.es_form_juridica.47", true),
			'59' => Language::_("Logicboxes.contact.es_form_juridica.59", true),
			'68' => Language::_("Logicboxes.contact.es_form_juridica.68", true),
			'124' => Language::_("Logicboxes.contact.es_form_juridica.124", true),
			'150' => Language::_("Logicboxes.contact.es_form_juridica.150", true),
			'152' => Language::_("Logicboxes.contact.es_form_juridica.152", true),
			'164' => Language::_("Logicboxes.contact.es_form_juridica.164", true),
			'181' => Language::_("Logicboxes.contact.es_form_juridica.181", true),
			'197' => Language::_("Logicboxes.contact.es_form_juridica.197", true),
			'203' => Language::_("Logicboxes.contact.es_form_juridica.203", true),
			'229' => Language::_("Logicboxes.contact.es_form_juridica.229", true),
			'269' => Language::_("Logicboxes.contact.es_form_juridica.269", true),
			'286' => Language::_("Logicboxes.contact.es_form_juridica.286", true),
			'365' => Language::_("Logicboxes.contact.es_form_juridica.365", true),
			'434' => Language::_("Logicboxes.contact.es_form_juridica.434", true),
			'436' => Language::_("Logicboxes.contact.es_form_juridica.436", true),
			'439' => Language::_("Logicboxes.contact.es_form_juridica.439", true),
			'476' => Language::_("Logicboxes.contact.es_form_juridica.476", true),
			'510' => Language::_("Logicboxes.contact.es_form_juridica.510", true),
			'524' => Language::_("Logicboxes.contact.es_form_juridica.524", true),
			'525' => Language::_("Logicboxes.contact.es_form_juridica.525", true),
			'554' => Language::_("Logicboxes.contact.es_form_juridica.554", true),
			'560' => Language::_("Logicboxes.contact.es_form_juridica.560", true),
			'562' => Language::_("Logicboxes.contact.es_form_juridica.562", true),
			'566' => Language::_("Logicboxes.contact.es_form_juridica.566", true),
			'608' => Language::_("Logicboxes.contact.es_form_juridica.608", true),
			'612' => Language::_("Logicboxes.contact.es_form_juridica.612", true),
			'713' => Language::_("Logicboxes.contact.es_form_juridica.713", true),
			'717' => Language::_("Logicboxes.contact.es_form_juridica.717", true),
			'744' => Language::_("Logicboxes.contact.es_form_juridica.744", true),
			'745' => Language::_("Logicboxes.contact.es_form_juridica.745", true),
			'746' => Language::_("Logicboxes.contact.es_form_juridica.746", true),
			'747' => Language::_("Logicboxes.contact.es_form_juridica.747", true),
			'877' => Language::_("Logicboxes.contact.es_form_juridica.877", true),
			'878' => Language::_("Logicboxes.contact.es_form_juridica.878", true),
			'879' => Language::_("Logicboxes.contact.es_form_juridica.879", true)
		)
		*/
	),
	'attr_es_tipo_identificacion' => array(
		'label' => Language::_("Logicboxes.contact.es_tipo_identificacion", true),
		'type' => "select",
		'options' => array(
			'1' => Language::_("Logicboxes.contact.es_tipo_identificacion.1", true),
			'3' => Language::_("Logicboxes.contact.es_tipo_identificacion.3", true),
			'0' => Language::_("Logicboxes.contact.es_tipo_identificacion.0", true)
		)
	),
	'attr_es_identificacion' => array(
		'label' => Language::_("Logicboxes.contact.es_identificacion", true),
		'type' => "text"
	)
));

// .NL
Configure::set("Logicboxes.contact_fields.nl", array(
	'attr_legalForm' => array(
		'label' => Language::_("Logicboxes.contact.legalForm", true),
		'type' => "select",
		'options' => array(
			'PERSOON' => Language::_("Logicboxes.contact.legalForm.persoon", true),
			'ANDERS' => Language::_("Logicboxes.contact.legalForm.anders", true)
		)
	)
));

// .PRO
Configure::set("Logicboxes.contact_fields.pro", array(
	'attr_profession' => array(
		'label' => Language::_("Logicboxes.contact.profession", true),
		'type' => "text"
	)
));

// .RU
Configure::set("Logicboxes.contact_fields.ru", array(
	'attr_contract-type' => array(
		'label' => Language::_("Logicboxes.contact.contract-type", true),
		'type' => "select",
		'options' => array(
			'ORG' => Language::_("Logicboxes.contact.contract-type.org", true),
			'PRS' => Language::_("Logicboxes.contact.contract-type.prs", true)
		)
	),
	'attr_birth-date' => array(
		'label' => Language::_("Logicboxes.contact.birth-date", true),
		'type' => "text"
	),
	/*
	'attr_org-r' => array(
		'label' => Language::_("Logicboxes.contact.org-r", true),
		'type' => "text"
	),
	'attr_person-r' => array(
		'label' => Language::_("Logicboxes.contact.person-r", true),
		'type' => "text"
	),
	'attr_address-r' => array(
		'label' => Language::_("Logicboxes.contact.address-r", true),
		'type' => "text"
	),
	*/
	'attr_kpp' => array(
		'label' => Language::_("Logicboxes.contact.kpp", true),
		'type' => "text"
	),
	'attr_code' => array(
		'label' => Language::_("Logicboxes.contact.code", true),
		'type' => "text"
	),
	'attr_passport' => array(
		'label' => Language::_("Logicboxes.contact.passport", true),
		'type' => "text"
	),
));

// .US
Configure::set("Logicboxes.contact_fields.us", array(
	'attr_category' => array(
		'label' => Language::_("Logicboxes.contact.category", true),
		'type' => "select",
		'options' => array(
			'C11' => Language::_("Logicboxes.contact.category.c11", true),
			'C12' => Language::_("Logicboxes.contact.category.c12", true),
			'C21' => Language::_("Logicboxes.contact.category.c21", true),
			'C31' => Language::_("Logicboxes.contact.category.c31", true),
			'C32' => Language::_("Logicboxes.contact.category.c32", true)
		)
	),
	'attr_purpose' => array(
		'label' => Language::_("Logicboxes.contact.purpose", true),
		'type' => "select",
		'options' => array(
			'P1' => Language::_("Logicboxes.contact.purpose.p1", true),
			'P2' => Language::_("Logicboxes.contact.purpose.p2", true),
			'P3' => Language::_("Logicboxes.contact.purpose.p3", true),
			'P4' => Language::_("Logicboxes.contact.purpose.p4", true),
			'P5' => Language::_("Logicboxes.contact.purpose.p5", true)
		)
	)
));


// .AU
Configure::set("Logicboxes.domain_fields.au", array(
	'attr_id-type' => array(
		'label' => Language::_("Logicboxes.domain.id-type", true),
		'type' => "select",
		'options' => array(
			'ACN' => Language::_("Logicboxes.domain.id-type.acn", true),
			'ABN' => Language::_("Logicboxes.domain.id-type.abn", true),
			'VIC BN' => Language::_("Logicboxes.domain.id-type.vic_bn", true),
			'NSW BN' => Language::_("Logicboxes.domain.id-type.nsw_bn", true),
			'SA BN' => Language::_("Logicboxes.domain.id-type.sa_bn", true),
			'NT BN' => Language::_("Logicboxes.domain.id-type.nt_bn", true),
			'WA BN' => Language::_("Logicboxes.domain.id-type.wa_bn", true),
			'TAS BN' => Language::_("Logicboxes.domain.id-type.tas_bn", true),
			'ACT BN' => Language::_("Logicboxes.domain.id-type.act_bn", true),
			'QLD BN' => Language::_("Logicboxes.domain.id-type.qld_bn", true),
			'TM' => Language::_("Logicboxes.domain.id-type.tm", true),
			'ARBN' => Language::_("Logicboxes.domain.id-type.arbn", true),
			'Other' => Language::_("Logicboxes.domain.id-type.other", true)
		)
	),
	'attr_id' => array(
		'label' => Language::_("Logicboxes.domain.id", true),
		'type' => "text"
	),
	'attr_policyReason' => array(
		'label' => Language::_("Logicboxes.domain.policyReason", true),
		'type' => "radio",
		'value' => "1",
		'options' => array(
			'1' => Language::_("Logicboxes.domain.policyReason.1", true),
			'2' => Language::_("Logicboxes.domain.policyReason.2", true),
		)
	),
	'attr_isAUWarranty' => array(
		'label' => Language::_("Logicboxes.domain.isAUWarranty", true),
		'type' => "checkbox",
		'options' => array(
			'true' => Language::_("Logicboxes.domain.isAUWarranty.true", true)
		)
	),
	'attr_eligibilityType' => array(
		'type' => "hidden",
		'options' => "Trademark Owner"
	),
	'attr_eligibilityName' => array(
		'type' => "hidden"
	)
));
?>