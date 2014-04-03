<?php
/*
 */

namespace Bogo\Http\Message;

/**
 * Response HTTP Message.
 *
 * @since 2.0
 * @package Components
 * @author Konstantinos Filios <konfilios@gmail.com>
 */
class Response extends Base
{
	/**
	 * HTTP Response Code.
	 *
	 * @var integer
	 */
	private $httpStatusCode;

	/**
	 * HTTP Response Status string.
	 *
	 * @var string
	 */
	private $httpReasonPhrase;

	/**
	 * Version of HTTP protocol.
	 *
	 * @var string
	 */
	private $httpProtocolVersion;

	/**
	 * Error message callback.
	 *
	 * @var callable
	 */
	private $errorMessageCallback;

	/**
	 * Set new error message callback.
	 *
	 * @param type $defaultErrorMessageCallback
	 * @return static
	 */
	public function setErrorMessageCallback($defaultErrorMessageCallback)
	{
		$this->errorMessageCallback = $defaultErrorMessageCallback;
		return $this;
	}

	/**
	 * HTTP Response Code.
	 *
	 * @return integer
	 */
	public function getHttpStatusCode()
	{
		return $this->httpStatusCode;
	}

	/**
	 * HTTP Response Status string.
	 *
	 * @return string
	 */
	public function getHttpReasonPhrase()
	{
		return $this->httpReasonPhrase;
	}

	/**
	 * Version of HTTP protocol.
	 *
	 * @return string
	 */
	public function getHttpProtocolVersion()
	{
		return $this->httpProtocolVersion;
	}

	/**
	 * Parse header line.
	 *
	 * @param string $headerLine
	 */
	public function parseHeaderLine($headerLine)
	{
		$pos = strpos($headerLine, ':');

		if ($pos === false) {
			// It's probably the status line
			if ('HTTP' === strtoupper(substr($headerLine, 0, 4))) {
				$statusComponents = explode(' ', trim($headerLine));
				$this->httpProtocolVersion = trim(array_shift($statusComponents));
				$this->httpStatusCode = intval(trim(array_shift($statusComponents)));
				$this->httpReasonPhrase = implode(' ', $statusComponents);
				return true;
			} else {
				return false;
			}
		}

		// Extract header value
		$field = strtolower(trim(substr($headerLine, 0, $pos)));
		$value = trim(substr($headerLine, $pos + 1));
		$this->setHeader($field, $value);

		if (($field == 'set-cookie') && !empty($value)) {
			// Response cookie
			$rawCookieAttributes = explode(';', $value);

			$cookieName = null;
			$cookieValue = null;
			$cookieAttributes = array();
			foreach ($rawCookieAttributes as $rawCookieAttribute) {
				// Explode into parts
				$rawCookieAttributeParts = explode('=', trim($rawCookieAttribute));

				if (empty($rawCookieAttributeParts)) {
					// Nothing in there
					continue;
				}

				// Trim first
				$cookieAttributeName = trim($rawCookieAttributeParts[0]);
				if (isset($rawCookieAttributeParts[1])) {
					// Attribute-value pair, this is the value
					$cookieAttributeValue = trim($rawCookieAttributeParts[1]);
				} else {
					// Flag, its value is true
					$cookieAttributeValue = true;
				}

				if ($cookieName === null) {
					// This is the first cookie attribute, i.e. the cookieName=cookieValue pair
					$cookieName = $cookieAttributeName;
					$cookieValue = $cookieAttributeValue;
				} else {
					$cookieAttributes[$cookieAttributeName] = $cookieAttributeValue;
				}
			}

			// Save the cookie
			$this->setCookie($cookieName, $cookieValue, $cookieAttributes);
		}

		return true;
	}

	/**
	 * True if status code is 4xx or 5xx.
	 *
	 * @return boolean
	 */
	public function hasErrorStatusCode()
	{
		return is_numeric($this->httpStatusCode) && ($this->httpStatusCode >= 400);
	}

	/**
	 * True if status code is below 400.
	 *
	 * @return boolean
	 */
	public function hasSuccessfulStatusCode()
	{
		return intval($this->httpStatusCode) < 400;
	}

	private function defaultErrorMessageCallback(Response $responseMessage)
	{
		return $responseMessage->httpReasonPhrase ?: Message\StatusCode::getMessageForCode($responseMessage->httpStatusCode);
	}

	/**
	 * Throws exception on error status code.
	 *
	 * @return Message\Response
	 * @throws ResponseException
	 */
	public function validateStatus()
	{
		// Network operation succeeded but way may have an HTTP error
		if ($this->hasErrorStatusCode()) {
			if ($this->errorMessageCallback === null) {
				$this->errorMessageCallback = array($this, 'defaultErrorMessageCallback');
			}

			throw new ResponseException(call_user_func($this->errorMessageCallback, $this), $this->httpStatusCode);
		}

		return $this;
	}
}