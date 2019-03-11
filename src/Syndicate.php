<?php

namespace Syndicate;

class Syndicate
{
    /**
     * Application container.
     *
     * @var array<string, mixed>
     */
    protected $container = [];

    /**
     * Get an item from the application container.
     *
     * @param string $abstract
     * @return mixed
     */
    public function get(string $abstract)
    {
        if( array_key_exists($abstract, $this->container) ){
            return $this->container[$abstract];
        }

        return null;
    }

    /**
     * Set an item in the application container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    public function set(string $abstract, $concrete): void
    {
        $this->container[$abstract] = $concrete;
    }
}