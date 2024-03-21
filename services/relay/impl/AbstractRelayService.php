<?php

namespace app\services\relay\impl;

use app\services\relay\IRelayService;

abstract class AbstractRelayService implements IRelayService
{
    protected $name;

    protected $type;

    protected $data;

    protected $links;

    public function __construct(array $options, array $data)
    {
        foreach ($options as $optionName => $optionValue) {
            $this->{$optionName} = $optionValue;
        }

        $this->data = $data;
    }

    abstract public function run();
}