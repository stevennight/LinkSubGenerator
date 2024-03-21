<?php

namespace app\services\sub;

use app\services\forward\ForwardService;
use app\services\relay\RelayService;
use app\services\source\SourceService;

class SubService
{
    public function dataIndexProvider(array $data): string
    {
        $type = $data['type'] ?? '';
        $typeArr = explode(',', $type);

        $links = [];
        foreach ($typeArr as $item) {
            if ($item === 'relay') {
                // 中转服务
                $links = array_merge(
                    $links,
                    (new RelayService())->run($data)
                );
            } else if ($item === 'forward') {
                // 自建转发
                $links = array_merge(
                    $links,
                    (new ForwardService())->run($data)
                );
            } else if ($item === 'source') {
                // 直连
                $links = array_merge(
                    $links,
                    (new SourceService())->run($data)
                );
            }
        }

        return $this->formatLinksToText($links, $data);
    }

    public function formatLinksToText(array $links, array $data): string
    {
        ksort($links);

        $res = implode(PHP_EOL, $links);
        $data['encode'] = $data['encode'] ?? 1;
        if ((int)$data['encode'] === 1) {
            $res = base64_encode($res);
        }

        return $res;
    }
}