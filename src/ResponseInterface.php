<?php
namespace Subframe;

/**
 * Represents an HTTP response
 */
interface ResponseInterface {

	/**
	 * Returns the response's status code
	 */
	public function getStatusCode(): int;

	/**
	 * Returns all header fields in the response
	 */
	public function getHeaders(): array;

	/**
	 * Returns the header field value, if present, or null otherwise
	 */
	public function getHeader($name): ?string;

	/**
	 * Returns the response's body
	 */
	public function getBody(): string;

	/**
	 * Adds a new header field to the response, returning a new instance
	 */
	public function withHeader(string $name, string $value): self;

	/**
	 * Removes a header field, if present, from the response, returning a new instance
	 */
	public function withoutHeader(string $name): self;

	/**
	 * Outputs the response, both header fields and the body
	 */
	public function send(): void;

	/**
	 * Creates a response from a view
	 */
	public static function fromView(string $filename, array $data = [], int $statusCode = 200): Response;

	/**
	 * Creates a JSON response from an array
	 */
	public static function fromData(array $data = [], int $statusCode = 200): Response;

}
