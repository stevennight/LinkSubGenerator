<?php

namespace app\services\sub;

use app\services\relay\RelayService;

class SubService
{
    public function dataIndexProvider(array $data): string
    {
        $links = (new RelayService())->run($data);

        return $this->formatLinksToText($links);
    }

    public function formatLinksToText(array $links): string
    {
        ksort($links);
        return implode(PHP_EOL, $links);
    }
}