<?php
namespace Subframe;

use Closure;
use Exception;
use Throwable;

/**
 * Represents the outermost layer of the application, allowing for middleware and routes definition
 */
class App {

	/**
	 * The router
	 */
	private Router $router;

 	private MiddlewareHandler $middlewareHandler;


	/**
	 * The constructor
	 * @param array $middlewares The middleware stack
	 */
	public function __construct(array $middlewares = []) {
		$this->router = new Router();

		$routerMiddleware = function (Request $request, Closure $next): ResponseInterface {
			$response = $this->router->handle($request);
			if ($response)
				return $response;
			throw new Exception('Route not found.', 404);
		};

		$this->middlewareHandler = new MiddlewareHandler(array_merge([$routerMiddleware], $middlewares));
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
	 * Defines a view that is to be presented in case of an uncaught exception
	 * Specific views can be designated for specific exception codes, by providing an array with codes as keys
	 * @param string|array $filename The view's filename
	 */
	public function catchView($filename, array $data = []): self {
		$middleware = function (Request $request, Closure $next) use ($filename, $data): ResponseInterface {
			try {
				$response = $next($request);
			} catch (Throwable $e) {
				$code = $e->getCode();
				$code = (is_numeric($code) && $code >= 400 && $code < 500 ? $code : 500);
				if ($code == 500)
					error_log('PHP exception "'.$e->getMessage().'"; stack trace: '.$e->getTraceAsString().' thrown in '.$e->getFile().', line '.$e->getLine().'; URI: '.$request->getUri());
				if (is_array($filename))
					$filename = $filename[$code] ?? $filename[0] ?? null;
				if ($request->acceptsJson())
					$response = Response::fromData(['error' => $e->getMessage()], $code);
				else
					$response = Response::fromView($filename, ['error' => $e->getMessage()] + $data, $code);
			}
			return $response;
		};
		$this->middlewareHandler->add($middleware);

		return $this;
	}

	/**
	 * Defines a closure that is to be called in case of an uncaught exception
	 */
	public function catch(Closure $closure): self {
		$middleware = function (Request $request, Closure $next) use ($closure): ResponseInterface {
			try {
				$response = $next($request);
			} catch (Throwable $e) {
				$response = $closure($e);
			}
			return $response;
		};
		$this->middlewareHandler->add($middleware);

		return $this;
	}

	/**
	 * Adds a middleware to the middleware stack
	 * @param MiddlewareInterface|Closure $middleware
	 */
	public function add($middleware): self {
		$this->middlewareHandler->add($middleware);
	
		return $this;
}

	/**
	 * Starts processing of the request taken from the $_SERVER['REQUEST_URI'] variable
	 */
	public function handleRequestUri(): void {
		$this->handle(Request::fromGlobalRequestUri());
	}

	/**
	 * Starts processing of the request taken from the $_SERVER['REQUEST_URI'] variable, but relative to the index.php script
	 */
	public function handleRelativeRequestUri(): void {
		$this->handle(Request::fromGlobalRelativeUri());
	}

	/**
	 * Starts processing of the request taken from the $_SERVER['PATH_INFO'] variable
	 */
	public function handlePathInfo(): void {
		$this->handle(Request::fromGlobalPathInfo());
	}

	/**
	 * Starts processing of the given request
	 */
	public function handle(RequestInterface $request): void {
		$response = $this->middlewareHandler->handle($request);
		$response->send();
	}

}