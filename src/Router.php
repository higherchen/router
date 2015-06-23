<?php
namespace higherchen\router;

class Router {

	/**
	 * @var array The route patterns and their handling functions
	 */
	private static $routes = array();

	/**
	 * @var array The before middleware route patterns and their handling functions
	 */
	private static $befores = array();

	/**
	 * @var object|callable The function to be executed when no route has been matched
	 */
	private static $notFound;

	/**
	 * @var string Current baseroute, used for (sub)route mounting
	 */
	private static $baseroute = '';

	/**
	 * @var string The Request Method that needs to be handled
	 */
	private static $method = '';

	// static call Router::get|post|patch|delete|put|options
	public static function __callstatic($method, $params) {
		$accept_method = array('get', 'post', 'patch', 'delete', 'put', 'options');
		if (in_array($method, $accept_method)) {
			$pattern = $params[0];
			$fn = $params[1];
			self::match(strtoupper($method), $pattern, $fn);
		}
	}

	/**
	 * Store a before middleware route and a handling function to be executed when accessed using one of the specified methods
	 *
	 * @param string $methods Allowed methods, | delimited
	 * @param string $pattern A route pattern such as /about/system
	 * @param object|callable $fn The handling function to be executed
	 */
	public static function before($methods, $pattern, $fn) {

		$pattern = self::$baseroute . '/' . trim($pattern, '/');
		$pattern = self::$baseroute ? rtrim($pattern, '/') : $pattern;
		if (is_string($fn) && strstr($fn, '@')) {
			$fn = explode('@', $fn);
		}

		foreach (explode('|', $methods) as $method) {
			self::$befores[$method][] = array(
				'pattern' => $pattern,
				'fn' => $fn,
			);
		}

	}

	/**
	 * Store a route and a handling function to be executed when accessed using one of the specified methods
	 *
	 * @param string $methods Allowed methods, | delimited
	 * @param string $pattern A route pattern such as /about/system
	 * @param object|callable $fn The handling function to be executed
	 */
	public static function match($methods, $pattern, $fn) {

		$pattern = self::$baseroute . '/' . trim($pattern, '/');
		$pattern = self::$baseroute ? rtrim($pattern, '/') : $pattern;
		if (is_string($fn) && strstr($fn, '@')) {
			$fn = explode('@', $fn);
		}

		foreach (explode('|', $methods) as $method) {
			self::$routes[$method][] = array(
				'pattern' => $pattern,
				'fn' => $fn,
			);
		}
	}

	/**
	 * Mounts a collection of callables onto a base route
	 *
	 * @param string $baseroute The route subpattern to mount the callables on
	 * @param callable $fn The callabled to be called
	 */
	public static function mount($baseroute, $fn) {

		// Track current baseroute
		$curBaseroute = self::$baseroute;

		// Build new baseroute string
		self::$baseroute .= $baseroute;

		// Call the callable
		if (is_string($fn) && strstr($fn, '@')) {
			$fn = explode('@', $fn);
		}
		call_user_func($fn);

		// Restore original baseroute
		self::$baseroute = $curBaseroute;

	}

	/**
	 * Get all request headers
	 * @return array The request headers
	 */
	private static function getRequestHeaders() {

		// getallheaders available, use that
		if (function_exists('getallheaders')) {
			return getallheaders();
		}

		// getallheaders not available: manually extract 'm
		$headers = array();
		foreach ($_SERVER as $name => $value) {
			if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
				$headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;

	}

	/**
	 * Get the request method used, taking overrides into account
	 * @return string The Request method to handle
	 */
	private static function getRequestMethod() {

		// Take the method as found in $_SERVER
		$method = $_SERVER['REQUEST_METHOD'];

		// If it's a HEAD request override it to being GET and prevent any output, as per HTTP Specification
		// @url http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
		if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
			ob_start();
			$method = 'GET';
		}

		// If it's a POST request, check for a method override header
		else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$headers = self::getRequestHeaders();
			if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], array('PUT', 'DELETE', 'PATCH'))) {
				$method = $headers['X-HTTP-Method-Override'];
			}
		}

		return $method;

	}

	/**
	 * Execute the router: Loop all defined before middlewares and routes, and execute the handling function if a mactch was found
	 *
	 * @param object|callable $callback Function to be executed after a matching route was handled (= after router middleware)
	 */
	public static function run($callback = null) {

		// Define which method we need to handle
		self::$method = self::getRequestMethod();

		// Handle all before middlewares
		if (isset(self::$befores[self::$method])) {
			self::handle(self::$befores[self::$method]);
		}

		// Handle all routes
		$numHandled = 0;
		if (isset(self::$routes[self::$method])) {
			$numHandled = self::handle(self::$routes[self::$method], true);
		}

		// If no route was handled, trigger the 404 (if any)
		if ($numHandled == 0) {
			if (self::$notFound && is_callable(self::$notFound)) {
				call_user_func(self::$notFound);
			} else {
				header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
			}

		}
		// If a route was handled, perform the finish callback (if any)
		else {
			if ($callback) {
				$callback();
			}

		}

		// If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
		if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
			ob_end_clean();
		}

	}

	/**
	 * Set the 404 handling function
	 * @param object|callable $fn The function to be executed
	 */
	public static function set404($fn) {
		if (is_string($fn) && strstr($fn, '@')) {
			$fn = explode('@', $fn);
		}
		self::$notFound = $fn;
	}

	/**
	 * Handle a a set of routes: if a match is found, execute the relating handling function
	 * @param array $routes Collection of route patterns and their handling functions
	 * @param boolean $quitAfterRun Does the handle function need to quit after one route was matched?
	 * @return int The number of routes handled
	 */
	private static function handle($routes, $quitAfterRun = false) {

		// Counter to keep track of the number of routes we've handled
		$numHandled = 0;

		// The current page URL
		$uri = self::getCurrentUri();

		// Loop all routes
		foreach ($routes as $route) {

			// we have a match!
			if (preg_match_all('#^' . $route['pattern'] . '$#', $uri, $matches, PREG_OFFSET_CAPTURE)) {

				// Rework matches to only contain the matches, not the orig string
				$matches = array_slice($matches, 1);

				// Extract the matched URL parameters (and only the parameters)
				$params = array_map(function ($match, $index) use ($matches) {

					// We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
					if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
						return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
					}

					// We have no following parameters: return the whole lot
					else {
						return (isset($match[0][0]) ? trim($match[0][0], '/') : null);
					}

				}, $matches, array_keys($matches));

				// call the handling function with the URL parameters
				call_user_func_array($route['fn'], $params);

				// yay!
				$numHandled++;

				// If we need to quit, then quit
				if ($quitAfterRun) {
					break;
				}

			}

		}

		// Return the number of routes handled
		return $numHandled;

	}

	/**
	 * Define the current relative URI
	 * @return string
	 */
	private static function getCurrentUri() {

		// Get the current Request URI and remove rewrite basepath from it (= allows one to run the router in a subfolder)
		$basepath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
		$uri = substr($_SERVER['REQUEST_URI'], strlen($basepath));

		// Don't take query params into account on the URL
		if (strstr($uri, '?')) {
			$uri = substr($uri, 0, strpos($uri, '?'));
		}

		// Remove trailing slash + enforce a slash at the start
		$uri = '/' . trim($uri, '/');

		return $uri;

	}

}
