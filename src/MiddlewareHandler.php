<?php
namespace Subframe;

use Closure;
use Exception;

class MiddlewareHandler implements RequestHandlerInterface {

	/**
	 * The middleware stack
	 */
	private array $middlewares;


	/**
	 * The constructor
	 */
	public function __construct(array $middlewares) {
		$this->middlewares = $middlewares;
	}

	/**
	 * Adds another middleware to the stack
	 * @param MiddlewareInterface|Closure $middleware
	 */
	public function add($middleware): void {
		array_push($this->middlewares, $middleware);
	}

	/**
	 * Processes the given request
	 * @throws Exception
	 */
	public function handle(RequestInterface $request): ResponseInterface {
		if (!$this->middlewares)
			throw new \Exception('Middleware stack is empty.', 500);
		$current = array_pop($this->middlewares);
		if ($current instanceof MiddlewareInterface)
			$response = $current->process($request, $this);
		else
			$response = $current($request, function ($request) { return $this->handle($request); });

		return $response;
	}

}
