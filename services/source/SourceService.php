<?php

namespace app\services\source;

use app\services\filter\FilterProtocolService;
use app\services\source\impl\CommonSourceService;
use Yii;

class SourceService
{
    /**
     * @param array $data
     * @return array
     */
    public function run(array $data): array
    {
        $res = [];

        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($data);

        $nodeList = Yii::$app->params['nodeList'];
        foreach ($nodeList as $node) {
            // 过滤协议
            if (!in_array($node['protocol'], $outputProtocol)) {
                continue;
            }

            $commonSourceService = new CommonSourceService($node, $data);
            $res = array_merge($res, $commonSourceService->run());
        }

        return $res;
    }
}