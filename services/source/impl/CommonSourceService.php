<?php

namespace app\services\source\impl;

class CommonSourceService
{
    protected $name;

    protected $link;

    protected $type;

    protected $sourceHost;

    protected $sourcePort;

    protected $links;

    public function __construct(array $options, array $data)
    {
        foreach ($options as $optionName => $optionValue) {
            $this->{$optionName} = $optionValue;
        }

        $this->data = $data;
    }

    public function run()
    {
        $this->generateLinks();
        return $this->links;
    }

    private function generateLinks()
    {
        $links = [];
        $label = sprintf(
            '%s',
            $this->name,
        );
        $link = $this->link;
        $link = preg_replace('/\{host}/', $this->sourceHost, $link);
        $link = preg_replace('/\{port}/', $this->sourcePort, $link);
        $link = preg_replace('/\{label}/', $label, $link);
        $links[$label] = $link;
        $this->links = $links;
    }
}