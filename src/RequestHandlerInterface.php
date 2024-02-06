<?php
namespace Subframe;

/**
 * Represents code that processes an HTTP request and returns an HTTP response
 */
interface RequestHandlerInterface {

	/**
	 * Implements the processing
	 */
	public function handle(Request $request): Response;

}
