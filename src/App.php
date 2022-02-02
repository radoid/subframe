<?php
namespace Subframe;

use Closure;

/**
 * Represents the outermost layer of the application, allowing for middleware and routes definition
 */
class App {

	/**
	 * The middleware stack
	 * @var array
	 */
	private $middlewares;

	/**
	 * The router
	 * @var Router
	 */
	private $router;


	/**
	 * The constructor
	 */
	public function __construct(array $middlewares = []) {
		$this->router = new RouterMiddleware;
		$this->middlewares = $middlewares;
	}

	/**
	 * Adds a namespace, containing routes in its classes, to the routing list
	 */
	public function namespace(string $namespace, array $classArgs = []): self {
		$this->router->addNamespace($namespace, $classArgs);
		return $this;
	}

	/**
	 * Defines a GET route
	 */
	public function get(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('GET', $uri, $callable, $classArgs);
		return $this;
	}

	/**
	 * Defines a POST route
	 */
	public function post(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('POST', $uri, $callable, $classArgs);
		return $this;
	}

	/**
	 * Defines a PUT route
	 */
	public function put(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('PUT', $uri, $callable, $classArgs);
		return $this;
	}

	/**
	 * Defines a DELETE route
	 */
	public function delete(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('DELETE', $uri, $callable, $classArgs);
		return $this;
	}

	/**
	 * Defines an OPTIONS route
	 */
	public function options(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('OPTIONS', $uri, $callable, $classArgs);
		return $this;
	}

	/**
	 * Defines a view route, that only presents a view
	 */
	public function view(string $uri, string $filename, array $data = []): self {
		$this->router->addView($uri, $filename, $data);
		return $this;
	}

	/**
	 * Defines an exception view, that is to be presented in case of an uncaught exception
	 */
	public function exceptionView(string $filename, array $data = []): self {
		$middleware = function (Request $request, RequestHandlerInterface $handler) use ($filename, $data): ResponseInterface {
			try {
				$response = $handler->handle($request);
			} catch (\Throwable $e) {
				$code = $e->getCode();
				$code = ($code >= 400 && $code < 500 ? $code : 500);
				$data = ['status' => $code, 'error' => $e->getMessage()] + $data;
				if ($request->acceptsJson())
					$response = Response::fromData($data, $code);
				else
					$response = Response::fromView($filename, $data, $code);
			}
			return $response;
		};
		array_unshift($this->middlewares, $middleware);

		return $this;
	}

	/**
	 * Defines a closure that is to be called in case of an uncaught exception
	 */
	public function catch(Closure $closure): self {
		$middleware = function (Request $request, RequestHandlerInterface $handler) use ($closure): ResponseInterface {
			try {
				$response = $handler->handle($request);
			} catch (\Throwable $e) {
				$response = $closure($e);
			}
			return $response;
		};
		array_unshift($this->middlewares, $middleware);

		return $this;
	}

	/**
	 * Adds a middleware to the middleware stack
	 */
	public function add($middleware) {
		$this->middlewares[] = $middleware;
	}

	/**
	 * Starts processing of the request taken from the $_SERVER['REQUEST_URI'] variable
	 */
	public function handleRequestUri() {
		$this->handle(Request::fromRequestUri());
	}

	/**
	 * Starts processing of the request taken from the $_SERVER['REQUEST_URI'] variable, but relative to the index.php script
	 */
	public function handleRelativeRequestUri() {
		$this->handle(Request::fromRelativeRequestUri());
	}

	/**
	 * Starts processing of the request taken from the $_SERVER['PATH_INFO'] variable
	 */
	public function handlePathInfo() {
		$this->handle(Request::fromPathInfo());
	}

	/**
	 * Starts processing of the given request
	 */
	public function handle(RequestInterface $request) {
		$handler = new MiddlewareHandler(array_merge($this->middlewares, [$this->router]));
		$response = $handler->handle($request);
		$response->output();
	}

}