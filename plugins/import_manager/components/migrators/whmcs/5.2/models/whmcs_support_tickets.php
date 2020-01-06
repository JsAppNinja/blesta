<?php
/**
 *
 *
 */
class WhmcsSupportTickets {
	
	public function __construct(Record $remote) {
		$this->remote = $remote;
	}
	
	public function get() {
		$fields = array("tbltickets.*", 'tbladmins.id' => "admin_id");
		return $this->remote->select($fields)->from("tbltickets")->
			leftJoin("tbladmins", "CONCAT_WS(?, tbladmins.firstname, tbladmins.lastname)", "=", "tbltickets.admin", false)->
			appendValues(array(' '))->
			group(array('tbltickets.id'))->
			getStatement();
	}
	
	public function getReplies() {
		$fields = array("tblticketreplies.*", 'tbladmins.id' => "admin_id");
		return $this->remote->select($fields)->from("tblticketreplies")->
			leftJoin("tbladmins", "CONCAT_WS(?, tbladmins.firstname, tbladmins.lastname)", "=", "tblticketreplies.admin", false)->
			appendValues(array(' '))->
			group(array('tblticketreplies.id'))->
			getStatement();
	}
	
	public function getNotes() {
		$fields = array("tblticketnotes.*", 'tbladmins.id' => "admin_id");
		return $this->remote->select($fields)->from("tblticketnotes")->
			leftJoin("tbladmins", "CONCAT_WS(?, tbladmins.firstname, tbladmins.lastname)", "=", "tblticketnotes.admin", false)->
			appendValues(array(' '))->
			group(array('tblticketnotes.id'))->
			getStatement();
	}
	
	public function getResponseCategories() {
		return $this->remote->select()->from("tblticketpredefinedcats")->order(array('id' => "ASC"))->getStatement();
	}
	
	public function getResponses() {
		return $this->remote->select()->from("tblticketpredefinedreplies")->getStatement();
	}
}
?>