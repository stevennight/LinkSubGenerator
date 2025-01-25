<?php

namespace app\services\relay\impl;

use app\services\filter\FilterProtocolService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Yii;

/**
 * Luny60 heptasky 唯云和租车 转发
 */
class Luny60RelayService extends AbstractRelayService
{
    protected $host;

    protected $token;

    protected $authToken;

    protected $subscriptionInfo;

    protected $serverList;

    protected $forwardList = [];

    protected $sslVerify = false;

    public function run()
    {
        $this->getNodeList();
        $this->getAuthToken();
        $this->getSubscription();
        $this->getProxyLists();
        $this->generateRelayedList();
        return $this->links;
    }

    private function getAuthToken()
    {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/login', [
            'json' => [
                'token' => $this->token,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('登录失败：解析响应失败');
        }

        if (!isset($response['jwt'])) {
            throw new \RuntimeException('登录失败：获取token失败');
        }

        $this->authToken = 'Bearer ' . $response['jwt'];
    }

    private function getSubscription()
    {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/subscription', [
            'headers' => [
                'authorization' => $this->authToken,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取订阅：解析响应失败');
        }

        $this->subscriptionInfo = $response;
        $this->serverList = array_column($response['lines'], null, 'id');
    }

    /**
     * 处理产品列表中每个产品的转发列表
     *
     * @return void
     */
    private function getProxyLists()
    {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/ports', [
            'headers' => [
                'authorization' => $this->authToken,
            ],
            'verify' => $this->sslVerify
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取转发列表：解析响应失败');
        }

        $this->forwardList = $response;
    }

    private function generateRelayedList()
    {
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);

        $links = $this->buildFlowLinks();

        foreach ($this->forwardList as $item) {
            $dest = $item['target_address_list'][0] ?? '';
            $sourceNodes = $this->nodeList[$dest] ?? null;

            // 从转发名字中获取源节点
            $nodeInfo = json_decode($item['display_name'], true);
            if (empty($sourceNodes)) {
                $sourceNodes = $this->nodeList[$nodeInfo['source'] ?? ''] ?? null;
            }
            if (empty($sourceNodes)) {
                continue;
            }

            $item['outbound_endpoint_id'] = $item['outbound_endpoint_id'] ?? 0;
            $inboundServer = $this->serverList[$item['outbound_endpoint_id']] ?? [];

            // 同一个host+端口，可以有多个不通协议的服务。
            foreach ($sourceNodes as $sourceNode) {
                // 过滤协议
                if (!in_array($sourceNode['protocol'], $outputProtocol)) {
                    continue;
                }

                $deviceGroupIn = [
                    'name' => $inboundServer['display_name'],
                    'connect_host' => $inboundServer['ip_addr'],
                    'is_online' => $inboundServer['is_online'] ?? false,
                ];

                // 自定义服务器信息，支持多个。
                $customHostList = $this->hostList[$item['outbound_endpoint_id']] ?? [[]];
                foreach ($customHostList as $customHost) {
                    // 将配置里面的信息合并过来
                    $currentDeviceGroupIn = array_merge($deviceGroupIn, $customHost);
                    $currentPort = $customHost['port_map'][$item['port_v4']] ?? $item['port_v4'];

                    $link = $sourceNode['link'];
                    $label = sprintf(
                        '%s-%s-%s-%s',
                        $sourceNode['name'],
                        $this->name,
                        $currentDeviceGroupIn['name'] . ($currentDeviceGroupIn['is_online'] ? '[🟢在线]' : '[❌离线]'),
                        $sourceNode['protocol']
                    );

                    $host = $currentDeviceGroupIn['connect_host'] ?? '';
                    if (empty($host)) {
                        continue;
                    }

                    $link = preg_replace('/\{host}/', $host, $link);
                    $link = preg_replace('/\{port}/', $currentPort, $link);
                    $link = preg_replace('/\{label}/', rawurlencode($label), $link);
                    $links[$label] = $link;
                }
            }
        }

        $this->links = $links;
    }

    public function buildFlowLinks(): array
    {
        // 获取流量等信息
        $usedFlow = $this->subscriptionInfo['traffic_used'] ?? 0;
        $usedFlow = $usedFlow / 1024 / 1024 / 1024; // 单位G
        $totalFlow = $this->subscriptionInfo['traffic_total'] ?? 0; // 单位G
        $remainFlow = $totalFlow - $usedFlow;
        $remainFlow = max($remainFlow, 0);

        # 新增一条中转账号信息
        $label = sprintf(
            '[服务信息]%s【过期时间：%s，剩余流量：%s】',
            $this->name,
            isset($this->subscriptionInfo['valid_until']) ?
                date('Y-m-d H:i:s', strtotime($this->subscriptionInfo['valid_until'])) : '获取失败',
            number_format($remainFlow, 2) . 'G'
        );
        return [
            $label => 'ss://bm9uZTow@' . uniqid() . ':8888#' . rawurlencode($label),
        ];
    }
}