<?php
namespace Subframe;

use Exception;

/**
 * Routing middleware, defining routes and fitting in the middleware stack
 */
class RouterMiddleware implements MiddlewareInterface {

	/**
	 * All defined routes
	 * @var array[]
	 */
	protected $routes = [];

	/**
	 * Processes the request - tries defined routes until a match is found; otherwise throws an Exception
	 * @throws Exception
	 */
	public function process(RequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		$router = new Router($request);
		foreach ($this->routes as [$method, $uri, $action, $classArgs]) {
			if ($method)
				$response = $router->captureRoute($method, $uri, $action, $classArgs);
			elseif ($uri !== null)
				$response = $router->captureViewRoute($uri, $action, $classArgs);
			else
				$response = $router->captureRouteInNamespace($action, $classArgs);
			if ($response)
				return $response;
		}
		throw new Exception('Route not found.', 404);
	}

	/**
	 * Adds a route to the list
	 */
	public function addRoute(string $method, string $uri, $action, array $classArgs = []) {
		$this->routes[] = [$method, $uri, $action, $classArgs];
	}

	/**
	 * Adds a view route to the list
	 */
	public function addView(string $uri, string $filename, array $data = []) {
		$this->routes[] = [null, $uri, $filename, $data];
	}

	/**
	 * Adds a namespace with routes defined across classes to the list
	 */
	public function addNamespace(string $namespace, array $classArgs = []) {
		$this->routes[] = [null, null, $namespace, $classArgs];
	}

}
