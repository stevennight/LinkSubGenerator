<?php

namespace app\services\relay\impl;

use app\services\relay\IRelayService;
use Yii;

abstract class AbstractRelayService implements IRelayService
{
    protected $name;

    protected $type;

    protected $data;

    protected $links = [];

    protected $nodeList = [];

    public function __construct(array $options, array $data)
    {
        foreach ($options as $optionName => $optionValue) {
            $this->{$optionName} = $optionValue;
        }

        $this->data = $data;
    }

    abstract public function run();

    protected function getNodeList()
    {
        $list = Yii::$app->params['nodeList'];
        $res = [];
        foreach ($list as $item) {
            $key = $item['sourceHost'] . ':' . $item['sourcePort'];
            // 同一个host+端口，可以有多个不通协议的服务。
            $res[$key][] = $item;
        }
        $this->nodeList = $res;
    }
}