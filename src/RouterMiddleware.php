<?php
namespace Subframe;

use Exception;

class RouterMiddleware implements MiddlewareInterface {

	private $routes = [];

	/**
	 * @throws Exception
	 */
	public function process(RequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		$router = new Router($request);
		foreach ($this->routes as [$method, $uri, $action, $args]) {
			if ($method)
				$response = $router->captureRoute($method, $uri, $action, $args);
			elseif ($uri !== null)
				$response = $router->captureViewRoute($uri, $action, $args);
			else
				$response = $router->captureRouteInNamespace($uri, $args);
			if ($response)
				return $response;
		}
		throw new Exception('Route not found.', 404);
	}

	public function addRoute($method, $uri, $callable, $classArgs = []) {
		$this->routes[] = [$method, $uri, $callable, $classArgs];
	}

	public function addView(string $uri, string $filename, array $data = []) {
		$this->routes[] = [null, $uri, $filename, $data];
	}

	public function addNamespace($namespace, $classArgs = []) {
		$this->routes[] = [null, null, $namespace, $classArgs];
	}

}
