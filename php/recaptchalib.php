<?php
/**
 * This is a PHP library that handles calling reCAPTCHA.
 *    - Documentation and latest version
 *          https://developers.google.com/recaptcha/docs/php
 *    - Get a reCAPTCHA API Key
 *          https://www.google.com/recaptcha/admin/create
 *    - Discussion group
 *          http://groups.google.com/group/recaptcha
 * @copyright Copyright (c) 2014, Google Inc.
 * @link      http://www.google.com/recaptcha
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * A ReCaptchaResponse is returned from checkAnswer().
 */
class ReCaptchaResponse
{
	public $success;
	public $errorCodes;
}

class ReCaptchaException extends \Exception {};

class ReCaptcha
{

	private static $_signupUrl = 'https://www.google.com/recaptcha/admin';

	private static $_siteVerifyUrl =
		'https://www.google.com/recaptcha/api/siteverify?';

	private $_secret;

	private static $_version = 'php_1.0';

	private $_curl_opts;

	/**
	 * Constructor.
	 * @param string $secret shared secret between site and ReCAPTCHA server.
	 * @param array  $curl_opt
	 * @throws ReCaptchaException
	 */
	public function __construct( $secret, array $curl_opt = array() )
	{
		if ( is_null( $secret ) || empty( $secret ) )
		{
			throw new ReCaptchaException('To use reCAPTCHA you must get an API key from <a href="'
				. self::$_signupUrl . '">' . self::$_signupUrl . '</a>');
		}
		$this->_secret = $secret;
		if (!empty($curl_opts)){
			$this->_curl_opts = $curl_opts;
		}
	}

	/**
	 * Encodes the given data into a query string format.
	 * @param array $data array of string elements to be encoded.
	 * @return string - encoded request.
	 */
	private function _encodeQS( $data )
	{
		$req = '';
		foreach ( $data as $key => $value )
		{
			$req .= $key . '=' . urlencode( stripslashes( $value ) ) . '&';
		}

		// Cut the last '&'
		$req = substr( $req, 0, strlen( $req ) - 1 );

		return $req;
	}

	/**
	 * Submits an HTTP GET to a reCAPTCHA server.
	 * @param string $path url path to recaptcha server.
	 * @param array  $data array of parameters to be sent.
	 * @return array response
	 * @throws ReCaptchaException
	 */
	private function _submitHTTPGet( $path, $data )
	{
		$req      = $this->_encodeQS( $data );
		// modified from: http://stackoverflow.com/a/6595108
		if (function_exists('curl_version')) {
			$opts = array(
				CURLOPT_HEADER         => false,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_USERAGENT      => 'ReCaptcha '.self::$_version,
				CURLOPT_AUTOREFERER    => true,
				CURLOPT_CONNECTTIMEOUT => 60,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_ENCODING       => '',
			);
			// check if we got overrides, or extra options (eg. proxy configuration)
			if (is_array($this->_curl_opts) && !empty($this->_curl_opts)) {
				$opts = array_merge($opts, $this->_curl_opts);
			}
			$conn = curl_init($path . $req);
			curl_setopt_array($conn, $opts);
			$response = curl_exec($conn);
			// handle a connection error
			$errno = curl_errno($conn);
			if ($errno !== 0) {
				throw new ReCaptchaException('Fatal error while contacting reCAPTCHA. '.
					$errno . ': ' . curl_error($conn) . '.');
			}
			curl_close($conn);
		}
		else {
			$response = file_get_contents( $path . $req );
		}

		return $response;
	}

	/**
	 * Calls the reCAPTCHA siteverify API to verify whether the user passes
	 * CAPTCHA test.
	 * @param string $remoteIp IP address of end user.
	 * @param string $response response string from recaptcha verification.
	 * @return ReCaptchaResponse
	 */
	public function verifyResponse( $remoteIp, $response )
	{
		// Discard empty solution submissions
		if ( is_null( $response ) || strlen( $response ) == 0 )
		{
			$recaptchaResponse             = new ReCaptchaResponse();
			$recaptchaResponse->success    = false;
			$recaptchaResponse->errorCodes = 'missing-input';

			return $recaptchaResponse;
		}

		$getResponse       = $this->_submitHttpGet(
			self::$_siteVerifyUrl,
			array(
				'secret'   => $this->_secret,
				'remoteip' => $remoteIp,
				'v'        => self::$_version,
				'response' => $response
			)
		);
		$answers           = json_decode( $getResponse, true );
		$recaptchaResponse = new ReCaptchaResponse();

		if ( trim( $answers [ 'success' ] ) == true )
		{
			$recaptchaResponse->success = true;
			$recaptchaResponse->errorCodes = $answers[ 'error-codes' ];
		}
		else
		{
			$recaptchaResponse->success = false;
		}

		return $recaptchaResponse;
	}
}
