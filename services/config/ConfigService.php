<?php

namespace app\services\config;

use Yii;

class ConfigService
{
    public function dataNodeProvider(array $data): array
    {
        $content = $data['content'];
        $nodes = explode(PHP_EOL, $content);

        $configNodeList = Yii::$app->params['nodeList'];
        $keyByHostPort = [];
        foreach ($configNodeList as $node) {
            $keyByHostPort[$node['sourceHost'] . ':' . $node['sourcePort']] = $node['forwardPort'] ?? 0;
        }
        unset($node);

        $nodeSettings = [];
        foreach ($nodes as $node) {
            if (!preg_match(
                '/^(?<protocol>.*?)(?<part1>:\/\/.*?@)(?<host>.*?):(?<port>.*?)(?<part2>(\?.*?#)|#)(?<label>.*?)$/',
                $node,
                $matches
            )) {
                continue;
            }

            // 名称
            $name = $matches['label'];
            $name = urldecode($name);
            $name = current(explode('-', $name));
            // link
            $link = $matches['protocol'] . $matches['part1'] . '{host}' . ':' . '{port}' . $matches['part2'] . '{label}';

            $nodeSettings[] = [
                'name' => $name,
                'link' => $link,
                'protocol' => $matches['protocol'],
                'sourceHost' => $matches['host'],
                'sourcePort' => (int)$matches['port'],
                // 如果旧配置有则从旧配置获取，否则到时候再手动配置。
                'forwardPort' => $keyByHostPort[$matches['host'] . ':' . $matches['port']] ?? 0,
            ];
        }

        file_put_contents(Yii::getAlias('@app') . '/config/nodeList.php', '<?php return ' . var_export($nodeSettings, true) . ';');

        return $nodeSettings;
    }

    public function dataRelayListProvider(array $data)
    {
        file_put_contents(Yii::getAlias('@app') . '/config/relayList.php', $data['content']);

        return $data['content'];
    }
}