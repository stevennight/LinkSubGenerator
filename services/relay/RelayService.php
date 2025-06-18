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

        $forceRefresh = (int) ($data['forceRefresh'] ?? 0);

        // 拿到中转服务列表
        $relayList = Yii::$app->params['relayList'];
        foreach ($relayList as $relay) {
            $cacheKey = $this->getCacheKey($data, $relay);

            if (!$forceRefresh && $relayRes = Yii::$app->cache->get($cacheKey . ':short')) {
                $relayRes = json_decode($relayRes, true);
                $res = array_merge($res, $relayRes);
                continue;
            }

            try {
                $relayType = $relay['type'];
                $className = ucfirst($relayType) . 'RelayService';
                $className = 'app\\services\\relay\\impl\\' . $className;
                if (!class_exists($className)) {
                    throw new \RuntimeException('中转服务类型不存在');
                }
                /** @var IRelayService $relayService */
                $relayService = new $className($relay, $data);
                $relayRes = $relayService->run();
                $res = array_merge($res, $relayRes);

                // 暂定缓存10天（按照中转服务、节点类型、节点协议分组缓存）。这个缓存是避免面板接口问题（包括被block）导致直接无法获取到数据，导致影响使用。
                Yii::$app->cache->set($cacheKey, json_encode($relayRes), 10 * 24 * 60 * 60);

                // 增加一个短期缓存。短期进来直接拿缓存。避免响应时间过长导致更新失败，有缓存了第二次请求可以直接读缓存，从而缩短响应时间。临时措施。
                Yii::$app->cache->set($cacheKey . ':short', json_encode($relayRes), 5 * 60);
            } catch (\Throwable $throwable) {
//                var_dump($throwable->getMessage(), $throwable->getLine(), $throwable->getFile(), $throwable->getTraceAsString());die;
                $relayRes = Yii::$app->cache->get($cacheKey);
                if (!$relayRes) {
                    continue;
                }

                $relayRes = json_decode($relayRes, true);
                $relayRes = array_merge($this->buildErrorLink($relay, $throwable->getMessage()), $relayRes);
                $res = array_merge($res, $relayRes);
            }
        }

        return $res;
    }

    public function getCacheKey($data, $relay): string
    {
        return sprintf(
            '%s:%s:%s:%s',
            $relay['name'], $relay['host'], $data['type'], $data['protocol'] ?? '',
        );
    }

    public function buildErrorLink($params, $msg): array
    {
        # 新增一条中转账号信息
        $label = sprintf(
            '[%s]获取最新数据异常：%s',
            $params['name'] ?? '',
            $msg
        );
        return [
            $label => 'ss://bm9uZTow@' . uniqid() . ':8888#' . rawurlencode($label),
        ];
    }
}