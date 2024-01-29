<?php
namespace Subframe;

/**
 * Represents an HTTP request
 */
interface RequestInterface {

	/**
	 * Request's HTTP method
	 * @return string
	 */
	public function getMethod(): string;

	/**
	 * Request's URI
	 * @return string
	 */
	public function getUri(): string;

	/**
	 * Specific header field's value, or all fields as an associative array
	 * @param string $name
	 * @return string|null
	 */
	public function getHeader(?string $name = null);

	/**
	 * Returns a query (GET) parameter by name, or all parameters
	 * @param string|null $name
	 * @return string|string[]|null
	 */
	public function getQuery(?string $name = null);

	/**
	 * Returns a (POST) variable from the body by name, or all variables
	 * @param string|null $name
	 * @return string|string[]|null
	 */
	public function getPost(?string $name = null);

	/**
	 * Returns a cookie value, if present in the request
	 * @param string|null $name
	 * @return string|array|null
	 */
	public function getCookie(?string $name = null);

	/**
	 * Returns a parameter from the $_SERVER array, if present in the request
	 * @param string|null $name
	 * @return string|array|null
	 */
	public function getServer(?string $name = null);

	/**
	 * Returns an array representing the named uploaded file, or all files
	 * @return array|null
	 * @throws Exception
	 */
	public function getFiles(?string $name = null): ?array;

	/**
	 * Remote address the request was made from, taking X-Forwarded-For header field into accout
	 * @return string
	 */
	public function getRemoteAddr(): string;

	/**
	 * Tells whether the request was made with XMLHttpRequest (an AJAX request)
	 * @return boolean
	 */
	public function isXmlHttpRequest(): bool;

	/**
	 * Tells whether JSON format is requested (in Accept header field)
	 * @return boolean
	 */
	public function acceptsJson(): bool;

}
