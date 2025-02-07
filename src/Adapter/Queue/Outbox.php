<?php

namespace Nimbly\Syndicate\Adapter\Queue;

use PDO;
use PDOStatement;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Message;
use Throwable;

/**
 * This adapter uses a database as the means to publish messages to a
 * specified table. This is a common pattern in EDA called the
 * "outbox pattern."
 *
 * Required minimum table structure is:
 *
 * ```sql
 * CREATE TABLE {:your table name:} (
 * 		id {:any data type you want:} primary key,
 * 		topic text not null,
 * 		payload text,
 * 		headers text, -- json serialized
 * 		attributes text, -- json serialized
 * 		created_at timestamp (with timezone), not null
 * );
 * ```
 *
 * @see https://microservices.io/patterns/data/transactional-outbox.html
 */
class Outbox implements PublisherInterface
{
	protected ?PDOStatement $statement = null;

	/**
	 * @param PDO $pdo
	 * @param string $table Name of table in database.
	 * @param callable|null $identity_generator If you need to generate a custom primary key, you can use this callback to do so.
	 */
	public function __construct(
		protected PDO $pdo,
		protected string $table = "outbox",
		protected $identity_generator = null
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$values = $this->buildValues($message);

		$statement = $this->getPublishStatement(
			$this->buildQuery($values)
		);

		try {

			$result = $statement->execute($values);
		}
		catch( Throwable $exception ){
			throw new PublishException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		if( $result === false ){
			throw new PublishException(
				"Failed to publish message."
			);
		}

		return $values["id"] ?? $this->pdo->lastInsertId();
	}

	/**
	 * Build the query to insert a record.
	 *
	 * @param array $values
	 * @return string
	 */
	protected function buildQuery(array $values): string
	{
		return \sprintf(
			"insert into %s (%s) values (%s)",
			$this->table,
			\implode(", ", \array_keys($values)),
			\implode(", ", \array_map(fn(string $key) => ":{$key}", \array_keys($values)))
		);
	}

	/**
	 * Build the value array to be used in the query.
	 *
	 * @param Message $message
	 * @return array<string,mixed>
	 */
	protected function buildValues(Message $message): array
	{
		$values = [
			"topic" => $message->getTopic(),
			"payload" => $message->getPayload(),
			"headers" => $message->getHeaders() ? \json_encode($message->getHeaders()) : null,
			"attributes" => $message->getAttributes() ? \json_encode($message->getAttributes(), JSON_FORCE_OBJECT) : null,
			"created_at" => \date("c"),
		];

		if( \is_callable($this->identity_generator) ){
			$values["id"] = \call_user_func($this->identity_generator, $message);
		}

		return $values;
	}

	/**
	 * Get the publish PDOStatement needed to insert new messages.
	 *
	 * @param string $query
	 * @return PDOStatement
	 */
	protected function getPublishStatement(string $query): PDOStatement
	{
		if( $this->statement === null ){
			try {

				$this->statement = $this->pdo->prepare($query);
			}
			catch( Throwable $exception ){
				throw new PublishException(
					message: "Failed to publish message.",
					previous: $exception
				);
			}

			if( $this->statement === false ){
				throw new PublishException(
					message: "Failed to publish message."
				);
			}
		}

		return $this->statement;
	}
}