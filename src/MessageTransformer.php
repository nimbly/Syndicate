<?php

namespace Syndicate;

abstract class MessageTransformer
{
    /**
     * The serializer to apply to message payload.
     *
     * @var callable|null
     */
    protected $serializer;

    /**
     * The deserializer to apply to message payload.
     *
     * @var callable|null
     */
    protected $deserializer;

    /**
     * Set the serializer.
     *
     * @param callable $callback
     * @return void
     */
    public function setSerializer(callable $callback): void
    {
        $this->serializer = $callback;
    }

    /**
     * Set the deserializer.
     *
     * @param callable $callback
     * @return void
     */
    public function setDeserializer(callable $callback): void
    {
        $this->deserializer = $callback;
    }

    /**
     * Serialize data if a serializer has been assigned.
     *
     * @param mixed $data
     * @return string
     */
    public function serialize($data): string
    {
        if( $this->serializer ){
            return \call_user_func($this->serializer, $data);
        }

        return \json_encode($data);
    }

    /**
     * Deserialize data, if a deserializer has been assigned.
     *
     * @param string $data
     * @return mixed
     */
    public function deserialize(string $data)
    {
        if( $this->deserializer ){
            return \call_user_func($this->deserializer, $data);
        }

        return \json_decode($data);
    }
}