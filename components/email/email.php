<?php
/**
 * A wrapper component for Swift Mailer, adds tag replacements capabilities.
 *
 * @package blesta
 * @subpackage blesta.components.email
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
Loader::load(VENDORDIR . "swiftmailer" . DS . "lib" . DS . "swift_required.php");

class Email {
	/**
	 * @var string The left tag enclosure
	 */
	public $tag_start = "[";
	/**
	 * @var string The right tag enclosure
	 */
	public $tag_end = "]";
	/**
	 * @var Logs The logs Model, used to record outgoing messages
	 */
	public $Logs;
	/**
	 * @var array All tags set for replacement
	 */
	private $tags = array();
	/**
	 * @var Swift_Message The message object for this instance
	 */
	private $message;
	/**
	 * @var Swift_Mailer The mailer used to send the message
	 */
	private $mailer;
	/**
	 * @var array An array of options to log when this message is attempted to be sent
	 */
	private $options = array();
	/**
	 * @var string The default character set encoding.
	 */
	private $charset;
	/**
	 * @var int int $max_line_length The maximum line length (historically 78 chars, no more than 1000 per RFC 2822)
	 */
	private $max_line_length;
	
	/**
	 * Constructs a new Email components, sets the default charset.
	 *
	 * @param string $charset The default character set encoding.
	 * @param int $max_line_length The maximum line length (historically 78 chars, no more than 1000 per RFC 2822)
	 */
	public function __construct($charset="UTF-8", $max_line_length=1000) {

		$this->max_line_length = $max_line_length;
		$this->charset = $charset;
		$this->newMessage();
		
		Loader::loadModels($this, array("Logs"));
	}
	
	/**
	 * Sets the transport object to be used for all subsequent requests
	 * 
	 * @param Swift_Transport The transport object used to send the message (SMTP, Sendmail, Mail, etc.)
	 */
	public function setTransport(Swift_Transport $transport) {
		
		$this->mailer = Swift_Mailer::newInstance($transport);
	}
	
	/**
	 * Set the flood resistenance for sending messages
	 *
	 * @param int $max_messages The maximum number of messages to send before disconnecting/reconnecting to the mail server
	 * @param int $pause_time The number of seconds to pause before reconnecting
	 */
	public function setFloodResistance($max_messages, $pause_time=0) {
		
		$this->mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin($max_messages, $pause_time));
	}
	
	/**
	 * Creates a new instance of the message
	 */
	private function newMessage() {
		$this->message = Swift_Message::newInstance();
		$this->message->setMaxLineLength($this->max_line_length);
		
		$this->message->setCharset($this->charset);
	}
	
	/**
	 * Sets the log options to be recorded when the message is attempted
	 *
	 * @param array $options An array of options to log when this message is attempted to be sent including:
	 * 	- company_id The ID of the company the message is being sent by
	 * 	- to_client_id The ID of the client the message is being sent to (optional)
	 * 	- from_staff_id The ID of the staff member the message is sent by (optional)
	 */
	public function setLogOptions(array $options) {
		$this->options = $options;
	}
	
	/**
	 * Sets the given array of tags for replacement.
	 *
	 * @param array $tags The tags to set for replacement.
	 * @see Email::replaceTags()
	 */
	public function setTags(array $tags) {
		$this->tags = $tags;
	}
	
	/**
	 * Sets the subject of the message, replacing the given tags with their
	 * key/value pairs.
	 *
	 * @param string $subject The subject of the message
	 * @param array $replacements The key/value pairs of tag replacements
	 * @see Email::setTags()
	 */
	public function setSubject($subject, array $replacements=array()) {
		
		$this->message->setSubject($this->replaceTags($subject, $replacements));
	}
	
	/**
	 * Sets the body of the message, replacing the given tags with their
	 * key/value pairs.
	 *
	 * @param string $body The body of the message
	 * @param boolean $is_html True if $body is HTML, false otherwise
	 * @param array $replacements The key/value pairs of tag replacements
	 * @see Email::setTags()
	 */
	public function setBody($body, $is_html=false, array $replacements=array()) {
		
		$this->message->setBody($this->replaceTags($body, $replacements), ($is_html ? 'text/html' : 'text/plain'));
	}

	/**
	 * Sets the alternate body of the message, replacing the given tags with their
	 * key/value pairs.
	 *
	 * @param string $body The body of the message
	 * @param array $replacements The key/value pairs of tag replacements
	 * @see Email::setTags()
	 */	
	public function setAltBody($body, array $replacements=array()) {

		$this->message->addPart($this->replaceTags($body, $replacements), 'text/plain');
	}
	
	/**
	 * Invokes parent::SetFrom()
	 *
	 * @param string $from The from address
	 * @param string $from_name The from name for this from address
	 */
	public function setFrom($from, $from_name=null) {

		$this->message->setFrom(($from_name ? array($from => $from_name) : array($from)));
	}
	
	/**
	 * Invokes parent::AddAddress()
	 *
	 * @param string $address The email address to add as a TO address
	 * @param string $name The TO name
	 */
	public function addAddress($address, $name=null) {

		$this->message->addTo($address, $name);
	}
	
	/**
	 * Invokes parent::AddCC()
	 *
	 * @param string $address The email address to add as a CC address
	 * @param string $name The CC name
	 */
	public function addCc($address, $name=null) {
		
		$this->message->addCc($address, $name);
	}
	
	/**
	 * Invokes parent::AddBCC()
	 *
	 * @param string $address The email address to add as a BCC address
	 * @param string $name The BCC name
	 */
	public function addBcc($address, $name='') {
		
		$this->message->addBcc($address, $name);
	}
	
	/**
	 * Invokes parent::setReplyTo()
	 *
	 * @param string $address The email address to add as a ReplyTo address
	 * @param string $name The ReplyTo name
	 */
	public function addReplyTo($address, $name=null) {
		
		$this->message->setReplyTo($address, $name);
	}
	
	/**
	 * Adds the attachment
	 *
	 * @param string $path The path to the file
	 * @param string $name The name of the file
	 * @param string $encoding The encoding of the file
	 * @param string $type The MIME type of the file
	 */
	public function addAttachment($path, $name, $encoding, $type) {
		
		$this->message->attach(Swift_Attachment::fromPath($path, $type)->setFilename($name));
	}
	
	/**
	 * Invokes parent::Send() and logs the result
	 */
	public function send() {
		$error = null;
		$sent = false;
		
		try {
			$sent = $this->mailer->send($this->message);
		}
		catch (Exception $e) {
			$error = $e->getMessage();
		}
		
		$vars = array('sent' => ($sent ? 1 : 0), 'error' => $error);
		
		$vars = array_merge($vars, $this->options);
		$this->log($vars);
		
		return $sent;
	}
	
	/**
	 * Log the last sent message to the Logs
	 */
	protected function log($vars) {
		
		$cc_address = implode(',', array_keys((array)$this->message->getCc()));
		if ($cc_address == null)
			$cc_address = null;
			
		$body_text = null;
		$body_html = null;
		foreach ($this->message->getChildren() as $child) {
			if ($child->getContentType() == "text/plain")
				$body_text = $child->getBody();
		}
		if ($body_text === null)
			$body_text = $this->message->getBody();
		else
			$body_html = $this->message->getBody();
		
		$vars = array_merge($vars,
			array(
				'to_address'=>implode(',', array_keys((array)$this->message->getTo())),
				'from_address'=>implode(',', array_keys((array)$this->message->getFrom())),
				'from_name'=>implode(',', array_values((array)$this->message->getFrom())),
				'cc_address'=>$cc_address,
				'subject'=>$this->message->getSubject(),
				'body_text'=>$body_text,
				'body_html'=>$body_html
			)
		);
		
		$this->Logs->addEmail($vars);
	}
	
	/**
	 * Resets all recipients, replytos, attachments, and custom headers, body
	 * and subject, and replacement tags (if any).
	 */
	public function resetAll() {
		
		$this->newMessage();
		$this->options = array();
		$this->tags = array();
	}
	
	/**
	 * Replaces tags in the given $str with the supplied key/value replacements,
	 * if a tag exists in Email::$tags, but is not found in $replacements, it
	 * will be replaced with null.
	 *
	 * @param string $str The string to run replacements on.
	 * @param array $replacements The key/value replacements.
	 * @return string The string with all replacements done.
	 */
	private function replaceTags($str, array $replacements) {
		$tag_count = count($this->tags);
		for ($i=0; $i<$tag_count; $i++)
			$str = str_replace($this->tag_start . $this->tags[$i] . $this->tag_end, (isset($replacements[$this->tags[$i]]) ? $replacements[$this->tags[$i]] : null), $str);
		
		return $str;
	}
}
?>