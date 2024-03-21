<?php

namespace app\services\forward;

use app\services\filter\FilterProtocolService;
use app\services\forward\impl\CommonForwardService;
use Yii;

class ForwardService
{
    /**
     * @param array $data
     * @return array
     */
    public function run(array $data): array
    {
        $res = [];

        // 拿到自建转发列表
        $forwardList = Yii::$app->params['forwardList'];
        foreach ($forwardList as $forward) {
            $commonForwardService = new CommonForwardService($forward, $data);
            $res = array_merge($res, $commonForwardService->run());
        }

        return $res;
    }
}