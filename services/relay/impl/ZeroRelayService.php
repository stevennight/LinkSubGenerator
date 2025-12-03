<?php

namespace app\services\relay\impl;

use app\services\filter\FilterProtocolService;
use GuzzleHttp\Client;
use Yii;

class ZeroRelayService extends AbstractRelayService
{
    protected $host;

    protected $username;

    protected $password;

    protected $authToken;

    protected $lines;

    protected $subscriptionInfo;

    protected $ports;

    protected $sslVerify = false;

    public function run()
    {
        $this->getNodeList();
        $this->getAuthToken();
        $this->getSubscription();
        $this->getPorts();
        $this->generateRelayedList();
        return $this->links;
    }

    private function getAuthToken()
    {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/login', [
            'json' => [
                'username' => $this->username,
                'password' => $this->password,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('登录失败：解析响应失败');
        }

        if (!isset($response['jwt'])) {
            throw new \RuntimeException('登录失败：未获取到JWT');
        }

        $this->authToken = $response['jwt'];
    }

    private function getSubscription()
    {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/subscription', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->authToken,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取订阅信息：解析响应失败');
        }

        if (!isset($response['lines'])) {
            throw new \RuntimeException('获取订阅信息：未获取到线路信息');
        }

        $this->subscriptionInfo = $response;
        $this->lines = array_column($response['lines'], null, 'id');
    }

    private function getPorts()
    {
        $client = new Client();
        // 默认获取一页，如果不够再调整，或者直接设置一个大点的 page_size
        $res = $client->request('GET', $this->host . '/api/ports', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->authToken,
            ],
            'query' => [
                'page' => 1,
                'page_size' => 200,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取端口列表：解析响应失败');
        }

        if (!isset($response['ports'])) {
            throw new \RuntimeException('获取端口列表：未获取到端口信息');
        }

        $this->ports = $response['ports'];
    }

    private function generateRelayedList()
    {
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);
        
        // 初始化链接列表，包含流量信息
        $this->links = $this->buildFlowLinks();

        foreach ($this->ports as $item) {
            $targetAddressList = $item['target_address_list'] ?? [];
            if (empty($targetAddressList)) {
                continue;
            }

            $sourceNodes = null;
            // 尝试匹配目标地址
            foreach ($targetAddressList as $targetAddress) {
                // $targetAddress 是 "host:port" 格式
                // nodeList key 是 "host:port"
                $sourceNodes = $this->nodeList[$targetAddress] ?? null;
                if ($sourceNodes) {
                    break;
                }
            }

            if (empty($sourceNodes)) {
                continue;
            }

            $line = $this->lines[$item['outbound_endpoint_id']] ?? null;
            if (!$line) {
                continue;
            }

            foreach ($sourceNodes as $sourceNode) {
                // 过滤协议
                if (!in_array($sourceNode['protocol'], $outputProtocol)) {
                    continue;
                }

                // 构建节点名称
                $label = sprintf(
                    '%s-%s-%s-%s',
                    $sourceNode['name'],
                    $this->name,
                    $line['display_name'] . '*' . ($line['traffic_scale'] ?? 1),
                    $sourceNode['protocol']
                );

                // 替换链接中的 host 和 port
                $link = $sourceNode['link'];
                
                // 获取入口 IP (line['ip_addr']) 和 端口 (item['port_v4'])
                $relayHost = $line['ip_addr'];
                $relayPort = $item['port_v4'];

                // 更新 link
                $newLink = $this->updateLink($link, $relayHost, $relayPort, $label);
                $this->links[$label] = $newLink;
            }
        }
    }

    private function updateLink($link, $host, $port, $name)
    {
        // 如果链接包含模板变量，优先使用模板替换
        if (strpos($link, '{host}') !== false) {
             $link = str_replace('{host}', $host, $link);
             $link = str_replace('{port}', $port, $link);
             $link = str_replace('{label}', rawurlencode($name), $link);
             return $link;
        }

        // 简单的 URL 解析和替换
        $components = parse_url($link);
        if (!$components) {
            return $link; // 无法解析，原样返回
        }

        $scheme = $components['scheme'] ?? '';
        
        // 针对常见协议的处理
        if (in_array($scheme, ['vmess', 'vless', 'trojan', 'ss', 'ssr'])) {
            // 注意：vmess 通常是 base64 编码的 json，不能直接 parse_url
            if ($scheme === 'vmess') {
                return $this->updateVmessLink($link, $host, $port, $name);
            }
            
            // ss, vless, trojan 通常符合 URL 规范
            // 构建新的 URL
            $newLink = $scheme . '://';
            
            // user info
            if (isset($components['user'])) {
                $newLink .= $components['user'];
                if (isset($components['pass'])) {
                    $newLink .= ':' . $components['pass'];
                }
                $newLink .= '@';
            }
            
            // host and port
            $newLink .= $host . ':' . $port;
            
            // path and query
            if (isset($components['path'])) {
                $newLink .= $components['path'];
            }
            
            if (isset($components['query'])) {
                $newLink .= '?' . $components['query'];
            }
            
            // fragment (usually name)
            $newLink .= '#' . rawurlencode($name);
            
            return $newLink;
        }
        
        return $link;
    }

    private function updateVmessLink($link, $host, $port, $name)
    {
        $payload = substr($link, 8); // remove vmess://
        $json = json_decode(base64_decode($payload), true);
        if (!$json) {
            return $link;
        }

        $json['add'] = $host;
        $json['port'] = $port;
        $json['ps'] = $name;

        return 'vmess://' . base64_encode(json_encode($json));
    }
    
    protected function buildFlowLinks()
    {
        $trafficTotalRaw = $this->subscriptionInfo['traffic_total'] ?? 0;
        $trafficUsedRaw = $this->subscriptionInfo['traffic_used'] ?? 0;
        
        // 简单的格式化逻辑
        $label = sprintf(
            '[服务信息]%s【过期时间：%s，总流量：%s，已用：%s】',
            $this->name,
            isset($this->subscriptionInfo['valid_until']) ?
                date('Y-m-d H:i:s', strtotime($this->subscriptionInfo['valid_until'])) : '获取失败',
            $trafficTotalRaw,
            $trafficUsedRaw
        );

        return [
            $label => 'ss://bm9uZTow@' . uniqid() . ':8888#' . rawurlencode($label),
        ];
    }
}
