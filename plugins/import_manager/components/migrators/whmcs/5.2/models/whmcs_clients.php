<?php
/**
 *
 *
 */
class WhmcsClients {
	
	public function __construct(Record $remote) {
		$this->remote = $remote;
	}
	
	public function get() {
		return $this->remote->select(array("tblclients.*", 'tblcurrencies.code' => "currency_code"))->
			from("tblclients")->
			leftJoin("tblcurrencies", "tblcurrencies.id", "=", "tblclients.currency", false)->
			getStatement();
	}
	
	public function getGroups() {
		return $this->remote->select()->from("tblclientgroups")->getStatement();
	}
	
	public function getCustomFields() {
		return $this->remote->select()->from("tblcustomfields")->where("type", "=", "client")->
			order(array('sortorder' => "ASC"))->getStatement();
	}
	
	public function getCustomFieldValues($field_id) {
		return $this->remote->select(array("tblcustomfieldsvalues.*", "tblclients.groupid"))->from("tblcustomfieldsvalues")->
			innerJoin("tblclients", "tblclients.id", "=", "tblcustomfieldsvalues.relid", false)->
			where("tblcustomfieldsvalues.fieldid", "=", $field_id)->
			getStatement();
	}
	
	public function getNotes() {
		return $this->remote->select()->from("tblnotes")->getStatement();
	}
}
?>