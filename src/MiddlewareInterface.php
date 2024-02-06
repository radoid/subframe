<?php
namespace Subframe;

/**
 * Middleware interface
 */
interface MiddlewareInterface {

	/**
	 * Preprocesses a request, calls the next chained middleware, and postprocesses its response
	 */
	public function process(Request $request, RequestHandlerInterface $handler): Response;

}