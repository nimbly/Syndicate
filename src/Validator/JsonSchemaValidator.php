<?php

namespace Nimbly\Syndicate\Validator;

use Nimbly\Syndicate\Message;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ValidationError;
use Nimbly\Syndicate\Exception\MessageValidationException;
use UnexpectedValueException;

class JsonSchemaValidator implements ValidatorInterface
{
	protected Validator $validator;

	/**
	 * @param array<string,string> $schemas A key/value pair array of topic names to JSON schemas or full path to a schema file.
	 * @param bool $ignore_missing_schemas If a schema cannot be found for a Message topic, should validation be ignored? Defaults to `false`.
	 */
	public function __construct(
		protected array $schemas = [],
		protected bool $ignore_missing_schemas = false
	)
	{
		$this->validator = new Validator;

		$this->schemas = \array_map(
			function(mixed $schema): mixed {
				if( \is_string($schema) && \file_exists($schema) ){
					$contents = \file_get_contents($schema);

					if( $contents === false ){
						throw new UnexpectedValueException(
							\sprintf(
								"Failed to read schema file \"%s\".",
								$schema
							)
						);
					}

					return $contents;
				}

				return $schema;
			},
			$schemas
		);
	}

	/**
	 * @inheritDoc
	 * @throws MessageValidationException
	 */
	public function validate(Message $message): bool
	{
		if( !isset($this->schemas[$message->getTopic()]) ){

			if( $this->ignore_missing_schemas ){
				return true;
			}

			throw new MessageValidationException(
				\sprintf(
					"No schema defined for message topic \"%s\".",
					$message->getTopic()
				),
				$message
			);
		}

		$result = $this->validator->validate(
			$message->getParsedPayload() ?: \json_decode($message->getPayload()),
			$this->schemas[$message->getTopic()]
		);

		if( $result->hasError() ){
			/**
			 * @psalm-suppress PossiblyNullArgument
			 */
			throw new MessageValidationException(
				"Message failed JSON schema validation.",
				$message,
				$this->buildContext($result->error())
			);
		}

		return true;
	}

	/**
	 * Build the error context. This provides detailed information about
	 * exactly where and why the schema contract was broken.
	 *
	 * @param ValidationError $validationError
	 * @return array{message:string,path:string,data:mixed}
	 */
	private function buildContext(ValidationError $validationError): array
	{
		$error = $validationError;

		while( $error->subErrors() ){
			$error = $error->subErrors()[0];
		}

		$data = $error->data()->value();
		$path = $error->data()->fullPath();
		$message = $error->message();

		$search = $replace = [];

		foreach( $error->args() as $key => $value ){
			if( \is_array($value) ){
				$value = \implode(", ", $value);
			}

			$search[] = "{{$key}}";
			$replace[] = $value;
		}

		/**
		 * @var string $message
		 */
		$message = \str_replace($search, $replace, $message);

		return [
			"message" => $message,
			"path" => "$." . \implode(".", $path),
			"data" => $data,
		];
	}
}