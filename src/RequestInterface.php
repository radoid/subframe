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
	 * All header fields in the request
	 * @return string[]
	 */
	public function getHeaders(): array;

	/**
	 * Specific header field's value
	 * @param string $name
	 * @return string|null
	 */
	public function getHeader(string $name): ?string;

	/**
	 * All query (GET) parameters in the request
	 * @return string[]
	 */
	public function getQueryParams(): array;

	/**
	 * All (POST) variables from the request's body
	 * @return string[]
	 */
	public function getParsedBody(): array;

	/**
	 * Returns a query (GET) parameter by name, or all parameters
	 * @param string|null $name
	 * @return string|string[]|null
	 */
	public function get(?string $name = null);

	/**
	 * Returns a (POST) variable from the body by name, or all variables
	 * @param string|null $name
	 * @return string|string[]|null
	 */
	public function post(?string $name = null);

	/**
	 * Returns an uploaded file definition by name
	 * @param string|null $name
	 * @return string[]|null
	 */
	public function file(string $name): ?array;

	/**
	 * All uploaded files definitions in a normalized form
	 * @return array
	 */
	public function getUploadedFiles(): array;

	/**
	 * Remote address the request was made from, taking X-Forwarded-For header field into accout
	 * @return string
	 */
	public function getRemoteAddr(): string;

	/**
	 * Tells whether the request was made with XMLHttpRequest (an AJAX request)
	 * @return boolean
	 */
	public function isAjax(): bool;

	/**
	 * Tells whether JSON format is requested (in Accept header field)
	 * @return boolean
	 */
	public function acceptsJson(): bool;

}
