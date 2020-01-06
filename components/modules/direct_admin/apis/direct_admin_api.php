<?php
/**
 * DirectAdmin API class
 *
 * This is licensed under the Open Software License, version 3.0, available at https://raw.github.com/FullAmbit/SiteSense/master/LICENSE.txt 
 * @author https://github.com/flotwig/directadmin/
 */
class DirectAdminApi {
	var $apiUrl='http://directadmin-install:2222';
	var $user='';
	var $pass='';
	var $calls=array(
		// Functions which modify users
		'createUser' => 
			array('POST','text','CMD_API_ACCOUNT_USER',array('action'=>'create','add'=>'Submit')),
		'createReseller' => 
			array('POST','text','CMD_API_ACCOUNT_RESELLER',array('action'=>'create','add'=>'Submit')),
		'modifyUserPackage' =>
			array('POST','text','CMD_API_MODIFY_USER',array('action'=>'package','add'=>'Submit')),
		'deleteUser' => 
			array('POST','text','CMD_API_SELECT_USERS',array('delete'=>'yes','confirmed'=>'Confirm')),
		'suspendUser' => 
			array('POST','','CMD_API_SELECT_USERS',array('suspend'=>'Suspend','location'=>'CMD_SELECT_USERS')),
		'unsuspendUser' => 
			array('POST','','CMD_API_SELECT_USERS',array('suspend'=>'Unsuspend','location'=>'CMD_SELECT_USERS')),
		// Functions which list users
		'listUsersByReseller' =>
			array('POST','list','CMD_API_SHOW_USERS'),
		'listResellers' =>
			array('POST','list','CMD_API_SHOW_RESELLERS'),
		'listAdmins' =>
			array('POST','list','CMD_API_SHOW_ADMINS'),
		'listUsers' =>
			array('POST','list','CMD_API_SHOW_ALL_USERS'),
		// Server Information functions
		'getServerStatistics' =>
			array('GET','array','CMD_API_ADMIN_STATS'),
		'getUserUsage' =>
			array('GET','array','CMD_API_SHOW_USER_USAGE'),
		'getUserDomains' =>
			array('GET','array','CMD_API_SHOW_USER_DOMAINS'),
		// User package info
		'getPackagesUser' =>
			array('GET', 'list', 'CMD_API_PACKAGES_USER'),
		'getPackagesReseller' =>
			array('GET', 'list', 'CMD_API_PACKAGES_RESELLER'),
		// Reseller IPS
		'getResellerIps' =>
			array('GET', 'list', 'CMD_API_SHOW_RESELLER_IPS'),
		'getUserConfig' =>
			array('GET', 'array', 'CMD_API_SHOW_USER_CONFIG')
	);
	private function contactApi($call,$method='POST',$postvars=array()){
		$url = trim($this->apiUrl,'/').'/'.$call;
		$ch = curl_init($url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
		curl_setopt($ch,CURLOPT_CUSTOMREQUEST,$method);
		if(!empty($postvars)){
			curl_setopt($ch,CURLOPT_POSTFIELDS,$postvars);
		}
		curl_setopt($ch,CURLOPT_USERAGENT,'flotwig\'s directadmin class - https://github.com/flotwig/directadmin');
		curl_setopt($ch,CURLOPT_USERPWD,$this->user.':'.$this->pass);
		curl_setopt($ch,CURLOPT_HTTPAUTH,CURLAUTH_BASIC);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
	public function __call($call,$arguments){
		if(empty($this->calls[$call])){
			return false;
		}else{
			$call=$this->calls[$call];
		}
		if(empty($arguments)){
			$response=$this->contactApi($call[2],$call[0]);
		}elseif(empty($call[3])){
			$response=$this->contactApi($call[2],$call[0],$arguments);
		}else{
			$response=$this->contactApi($call[2],$call[0],array_merge($call[3],$arguments));
		}
		$response=html_entity_decode($response);
		switch($call[1]){
			case 'array':
				parse_str($response,$response);
				return $response;
				break;
			case 'list':
			case 'text':
			default:
				parse_str($response,$response);
				return $response;
				break;
		}
	}
	
	/**
	 * Sets the API URL
	 *
	 * @param string $url The API URL
	 */
	public function setUrl($url) {
		$this->apiUrl = rtrim($url, "/") . ":2222";
	}
	
	/**
	 * Sets the API user
	 *
	 * @param string $username The username
	 */
	public function setUser($username) {
		$this->user = $username;
	}
	
	/**
	 * Sets the API pass
	 *
	 * @param string $password The password
	 */
	public function setPass($password) {
		$this->pass = $password;
	}
}
?>