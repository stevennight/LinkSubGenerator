<?php

namespace app\services\forward\impl;

use app\services\filter\FilterProtocolService;

class CommonForwardService
{
    protected $name;

    protected $host;

    protected $rules;

    protected $data;

    protected $nodeList;

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
        $this->getNodeList();
        $this->generateLinks();
        return $this->links;
    }

    private function getNodeList()
    {
        $this->nodeList = \Yii::$app->params['nodeList'];
    }

    private function generateLinks()
    {
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);

        $links = [];
        foreach ($this->nodeList as $node) {
            // 过滤协议
            if (!in_array($node['protocol'], $outputProtocol)) {
                continue;
            }

            $nodeKey = $node['sourceHost'] . ':' . $node['sourcePort'];
            $link = $node['link'];
            $label = sprintf(
                '%s-%s-%s',
                $node['name'],
                $this->name,
                $node['protocol']
            );

            $host = $this->host;
            if (empty($host)) {
                continue;
            }

            // 获取端口
            $port = $node['forwardPort'];
            if ($this->rules && $this->rules[$nodeKey]) {
                $port = $this->rules[$nodeKey]['port'];
            }
            if (!$port) {
                continue;
            }

            $link = preg_replace('/\{host}/', $host, $link);
            $link = preg_replace('/\{port}/', $port, $link);
            $link = preg_replace('/\{label}/', rawurlencode($label), $link);
            $links[$label] = $link;
        }

        $this->links = $links;
    }
}