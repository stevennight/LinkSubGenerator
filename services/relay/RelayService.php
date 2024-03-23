<?php

namespace app\services\relay;

use app\services\relay\impl\NyanpassRelayService;
use Yii;

class RelayService
{
    /**
     * @param array $data
     * @return array
     */
    public function run(array $data): array
    {
        $res = [];

        // 拿到中转服务列表
        $relayList = Yii::$app->params['relayList'];
        foreach ($relayList as $relay) {
            try {
                $relayType = $relay['type'];
                $className = ucfirst($relayType) . 'RelayService';
                $className = 'app\\services\\relay\\impl\\' . $className;
                /** @var IRelayService $relayService */
                $relayService = new $className($relay, $data);
                $res = array_merge($res, $relayService->run());
            } catch (\Throwable $throwable) {

            }
        }

        return $res;
    }
}