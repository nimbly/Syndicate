<?php

namespace Nimbly\Syndicate;

use Flow\JSONPath\JSONPath;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\RouterInterface;
use ReflectionClass;
use UnexpectedValueException;

class Router implements RouterInterface
{
	/**
	 * Array of handlers and matching Consume attribute.
	 *
	 * @var array<string,Consume>
	 */
	protected array $routes;

	/**
	 * @param array<object|class-string> $handlers Array of handlers (instances or class-strings) that contain Consume attributes.
	 * @param callable|null $default A default handler for messages that could not be routed.
	 */
	public function __construct(
		protected array $handlers,
		protected $default = null,
	)
	{
		$this->routes = \array_reduce(
			$handlers,
			function(array $routes, mixed $handler_class): array {
				$reflectionClass = new ReflectionClass($handler_class);
				$reflectionMethods = $reflectionClass->getMethods();

				foreach( $reflectionMethods as $reflectionMethod ){
					$reflectionAttributes = $reflectionMethod->getAttributes(Consume::class);

					if( empty($reflectionAttributes) ){
						continue;
					}

					foreach( $reflectionAttributes as $reflectionAttribute ) {
						$routes[\sprintf("\%s@%s", $reflectionClass->getName(), $reflectionMethod->getName())] = $reflectionAttribute->newInstance();
					}
				}

				return $routes;
			},
			[]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function resolve(Message $message): callable|string|null
	{
		foreach( $this->routes as $handler => $route ){
			if( $this->matchString($message->getTopic(), $route->getTopic()) &&
				$this->matchJson($message->getPayload(), $route->getPayload()) &&
				$this->matchKeyValuePairs($message->getHeaders(), $route->getHeaders()) &&
				$this->matchKeyValuePairs($message->getAttributes(), $route->getAttributes())) {
				return $handler;
			}
		}

		return $this->default;
	}

	/**
	 * Build a regex to match content.
	 *
	 * This method assumes the * (asterisk) is allowed as a wildcard, all other values
	 * get regex escaped.
	 *
	 * @param string $pattern
	 * @return string
	 */
	private function buildRegex(string $pattern): string
	{
		return \str_replace(
				[".", "*"], ["\.", ".*"],
				\str_replace(
					["\\", "/", "+", "?", "[", "^", "]", "$", "(", ")", "{", "}", "=", "!", "<", ">", "|", ":", "-", "#"],
					["\\\\", "\\/", "\\+", "\\?", "\\[", "\\^", "\\]", "\\$", "\\(", "\\)", "\\{", "\\}", "\\=", "\\!", "\\<", "\\>", "\\|", "\\:", "\\-", "\\#"],
					$pattern
				)
			);
	}

	/**
	 * Match a string against a pattern or a set of patterns.
	 *
	 * If more than one pattern is provided, the results are OR'ed.
	 *
	 * @param string $string
	 * @param string|array<string> $patterns
	 * @return boolean
	 */
	protected function matchString(string $string, string|array $patterns): bool
	{
		if( empty($patterns) ){
			return true;
		}

		if( !\is_array($patterns) ){
			$patterns = [$patterns];
		}

		$result = false;

		foreach( $patterns as $pattern ){
			$match = \preg_match(
				\sprintf("/^%s$/", $this->buildRegex($pattern)),
				$string
			);

			if( $match === false ){
				throw new UnexpectedValueException("Regex is invalid.");
			}

			$result = $result || (bool) $match;
		}

		return $result;
	}

	/**
	 * Match a JSON string against an array of JSON paths and
	 * patterns.
	 *
	 * @param string $json
	 * @param array<string,string|array<string>> $patterns
	 * @return boolean
	 */
	protected function matchJson(string $json, array $patterns): bool
	{
		if( empty($patterns) ){
			return true;
		}

		$data = \json_decode($json, true);

		if( \json_last_error() !== JSON_ERROR_NONE ){
			throw new UnexpectedValueException("Payload was not able to be JSON decoded.");
		}

		$json = new JSONPath($data);

		foreach( $patterns as $path => $pattern ){
			$data = $json->find($path)->getData();

			if( empty($data) ){
				return false;
			}

			if( count($data) > 1 || (!\is_string($data[0]) && !\is_int($data[0])) ){
				throw new UnexpectedValueException(
					\sprintf(
						"JSON path \"%s\" matched more than one value or the value is not a string. " .
						"Please refine your JSON path to return just a single string.",
						$path
					)
				);
			}

			$match = $this->matchString((string) $data[0], $pattern);

			if( !$match ){
				return false;
			}
		}

		return true;
	}

	/**
	 * Match all key value pair string values.
	 *
	 * @param array<string,string> $values
	 * @param array<string,string|array<string>> $patterns
	 * @return boolean
	 */
	private function matchKeyValuePairs(array $values, array $patterns): bool
	{
		if( empty($patterns) ){
			return true;
		}

		foreach( $patterns as $key => $value ){
			if( \array_key_exists($key, $values) === false ){
				return false;
			}

			$match = $this->matchString($values[$key], $value);

			if( !$match ){
				return false;
			}
		}

		return true;
	}
}