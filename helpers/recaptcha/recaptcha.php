<?php
/**
 * Supplies methods for creating and verifying a reCAPTCHA captcha challenge.
 *
 * @package blesta
 * @subpackage blesta.components.recaptcha
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Recaptcha {
	/**
	 * @var string The error, if Recaptcha::verify() returned false
	 */
	public $error;
	/**
	 * @var string The URI to the reCAPTCHA API
	 */
	private $uri = "www.google.com/recaptcha/api/";
	/**
	 * @var string The secret key for this captcha
	 */
	private $private_key;
	/**
	 * @var string The public key for this captcha
	 */
	private $public_key;
	/**
	 * @var string The HTML to use for the custom captcha body
	 */
	private $custom_body;
	/**
	 * @var array The javascript options to set for this captcha
	 */
	private $custom_options = array();
	
	/**
	 * Establish a new instance of reCAPTCHA with the given private/public keys
	 *
	 * @param string $private_key The private key, as supplied by reCAPTCHA
	 * @param string $public_key The public key, as supplied by reCAPTCHA
	 */
	public function __construct($private_key, $public_key) {
		$this->private_key = $private_key;
		$this->public_key = $public_key;
	}
	
	/**
	 * Fetches all HTML, including javascript to display the captcha form
	 * elements
	 *
	 * @param string $theme The reCAPTCHA theme to use, or 'custom' for a custom theme.
	 * @param string $custom_widget The custom widget (i.e. HTML id attribute value) to use for the 'custom' theme.
	 * @return string The HTML to display
	 */
	public function getHtml($theme=null, $custom_widget="recaptcha_widget") {
		$options = array();
		if ($theme != null)
			$options['theme'] = $theme;
		if ($custom_widget != null)
			$options['custom_theme_widget'] = $custom_widget;
		$this->setCustomOptions($options);
		
		return $this->captchaOptions() . $this->captchaBody();
	}
	
	/**
	 * Sets the given HTML to be used for a custom theme when Recaptcha::getHtml
	 * is called.
	 *
	 * @param string $html The HTML to use for the custom theme
	 * @see Recaptcha::getHtml()
	 */
	public function setCustomBody($html) {
		$this->custom_body = $html;
	}
	
	/**
	 * Sets options to be set in the javascript 'RecaptchaOptions' variable, things
	 * like 'lang', 'theme', 'custom_translations', etc.
	 *
	 * @param array $options A single-dimensional string indexed array (key/value pairs)
	 */
	public function setCustomOptions(array $options) {
		$this->custom_options += $options;
	}
	
	/**
	 * Verifies that the captcha was answered successfully by making a request
	 * to the remote reCAPTCHA server.
	 *
	 * @param string $challenge The value of the 'recaptcha_challenge_field' form field.
	 * @param string $response The value of the 'recaptcha_response_field' form field.
	 * @return boolean True if the response is valid, false otherwise. If false, the error can be access via Recpatch::$error
	 */
	public function verify($challenge, $response) {
		$this->error = null;
		
		// If no challenge or response given, don't bother verifying
		if ($challenge == null || $response == null || $challenge == "" || $response == "") {
			$this->error = "incorrect-captcha-sol";
			return false;
		}
		
		$data = array(
			'privatekey' => $this->private_key,
			'remoteip' => $_SERVER['REMOTE_ADDR'],
			'challenge' => $challenge,
			'response' => $response
		);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->getScheme() . $this->uri . "verify");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$raw_response = curl_exec($ch);
		
		$response = explode("\n", $raw_response);
		if (isset($response[0]) && $response[0] == "true")
			return true;
		
		$this->error = isset($response[1]) ? $response[1] : null;
		return false;
	}
	
	/**
	 * Builds the captcha body
	 *
	 * @return string HTML/javascript used to build the captcha
	 */
	private function captchaBody() {
		return $this->customBody() . "<script type=\"text/javascript\" src=\"" . $this->getScheme() . $this->uri . "challenge?k=" . $this->public_key . "\"></script>
			<noscript>
				<iframe src=\"" . $this->getScheme() . $this->uri . "noscript?k=" . $this->public_key . "\" height=\"300\" width=\"500\" frameborder=\"0\"></iframe><br />
				<textarea name=\"recaptcha_challenge_field\" rows=\"3\" cols=\"40\"></textarea>
				<input type=\"hidden\" name=\"recaptcha_response_field\" value=\"manual_challenge\">
			</noscript>";
	}
	
	/**
	 * Builds the custom captcha body
	 *
	 * @return string HTML used for the custom captcha body
	 */
	private function customBody() {
		if (isset($this->custom_options['theme']) && $this->custom_options['theme'] == "custom") {
			if ($this->custom_body != null)
				return $this->custom_body;
	
			return "<div id=\"recaptcha_widget\" style=\"display:none\">
				<div id=\"recaptcha_image\"></div>
				<span class=\"recaptcha_only_if_image\">Enter the words above:</span>
				<span class=\"recaptcha_only_if_audio\">Enter the numbers you hear:</span>
				<input type=\"text\" id=\"recaptcha_response_field\" name=\"recaptcha_response_field\" />
				<div><a href=\"javascript:Recaptcha.reload()\">Get another CAPTCHA</a></div>
				<div class=\"recaptcha_only_if_image\"><a href=\"javascript:Recaptcha.switch_type('audio')\">Get an audio CAPTCHA</a></div>
				<div class=\"recaptcha_only_if_audio\"><a href=\"javascript:Recaptcha.switch_type('image')\">Get an image CAPTCHA</a></div>
				<div><a href=\"javascript:Recaptcha.showhelp()\">Help</a>
			</div>";
		}
		return null;
	}
	
	/**
	 * Builds the custom javascript options and sets them to the javascript 'RecaptchaOptions' variable.
	 *
	 * @return string The HTML/javscript for defining custom options for this captcha
	 */
	private function captchaOptions() {
		$options = "";
		if (is_array($this->custom_options)) {
			$i=0;
			foreach ($this->custom_options as $key => $value) {
				$options .= ($i > 0 ? ",\n" : "") . $key . ": '" . $value . "'";
				$i++;
			}
		}
		
		return "<script type=\"text/javascript\">var RecaptchaOptions = {" . $options . "};</script>";
	}
	
	/**
	 * Determine whether this server is currently running under a secure HTTP
	 * connection, and return the appropriate scheme.
	 *
	 * @return string The scheme currently in use (http:// or https://)
	 */
	private function getScheme() {
		return "http" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off" ? "s" : "") . "://";
	}
}
?>