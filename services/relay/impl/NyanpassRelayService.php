<?php

namespace app\services\relay\impl;

use app\services\filter\FilterProtocolService;
use GuzzleHttp\Client;
use Yii;

class NyanpassRelayService extends AbstractRelayService
{
    protected $host;

    protected $username;

    protected $password;

    protected $userDetail;

    protected $subHostByUsername;

    protected $authToken;

    protected $deviceGroup;

    protected $forwardList;

    protected $nodeList;

    protected $sslVerify = false;

    public function run()
    {
        $this->getNodeList();
        $this->getAuthToken();
        $this->getUserDetail();
        $this->getDeviceGroup();
        $this->getForwardList();
        $this->generateRelayedList();
        return $this->links;
    }

    private function getAuthToken()
    {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/v1/auth/login', [
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

        if ($response['code'] !== 0) {
            throw new \RuntimeException('登录失败：' . $response['msg']);
        }

        $this->authToken = $response['data'];
    }

    private function getUserDetail() {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/v1/user/info', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'verify' => $this->sslVerify,
        ]);
        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取用户详情：解析响应失败');
        }
        if ($response['code'] !== 0) {
            throw new \RuntimeException('获取用户详情：' . $response['msg']);
        }

        $this->userDetail = $response['data'];
    }

    private function getDeviceGroup()
    {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/v1/user/devicegroup', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取设备组：解析响应失败');
        }

        if ($response['code'] !== 0) {
            throw new \RuntimeException('获取设备组：' . $response['msg']);
        }

        $this->deviceGroup = $response['data'];
        $this->deviceGroup = array_column($this->deviceGroup, null, 'id');
    }

    private function getForwardList()
    {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/v1/user/forward', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'query' => [
                'page' => 1,
                'size' => 200,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取转发列表：解析响应失败');
        }

        if ($response['code'] !== 0) {
            throw new \RuntimeException('获取转发列表：' . $response['msg']);
        }

        $this->forwardList = $response['data'];
    }

    private function getNodeList()
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

    private function generateRelayedList()
    {
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);

        $links = [];
        foreach ($this->forwardList as $item) {
            $config = json_decode($item['config'], true);

            $sourceNodes = null;
            foreach ($config['dest'] as $nodeKeyDest) {
                $sourceNodes = $this->nodeList[$nodeKeyDest] ?? null;
                if ($sourceNodes) {
                    break;
                }
            }
            // 从转发名字中获取源节点
            if (empty($sourceNodes)) {
                $nodeKeyDest = explode(':', $item['name']);
                $nodeKeyDest = end($nodeKeyDest);
                $sourceNodes = $this->nodeList[$nodeKeyDest] ?? null;
            }
            if (empty($sourceNodes)) {
                continue;
            }

            // 获取流量等信息
            $usedFlow = $this->userDetail['traffic_used'] ?? 0;
            $usedFlow = $usedFlow / 1024 / 1024 / 1024; // 单位G
            $totalFlow = $this->userDetail['traffic_enable'] ?? 0;
            $totalFlow = $totalFlow / 1024 / 1024 / 1024; // 单位G
            $remainFlow = $totalFlow - $usedFlow;
            $remainFlow = max($remainFlow, 0);

            // 同一个host+端口，可以有多个不通协议的服务。
            foreach ($sourceNodes as $sourceNode) {
                // 过滤协议
                if (!in_array($sourceNode['protocol'], $outputProtocol)) {
                    continue;
                }

                $deviceGroupIn = $this->deviceGroup[$item['device_group_in']] ?? [];
                // 可能没有出口设备
                $deviceGroupOut = $this->deviceGroup[$item['device_group_out']] ?? [];
                if (!$deviceGroupIn) {
                    continue;
                }

                $link = $sourceNode['link'];
                $label = sprintf(
                    '%s-%s-%s-%s-%s【过期时间：%s，剩余流量：%s】',
                    $sourceNode['name'],
                    $this->name,
                    $deviceGroupIn['name'] . '*' . $deviceGroupIn['ratio'],
                    isset($deviceGroupOut['name']) ?
                        $deviceGroupOut['name'] . '*' . $deviceGroupOut['ratio'] : '入口直出',
                    $sourceNode['protocol'],
                    isset($this->userDetail['expire']) ?
                        date('Y-m-d H:i:s', $this->userDetail['expire']) : '获取失败',
                    number_format($remainFlow, 2) . 'G'
                );

                $host = $deviceGroupIn['connect_host'];
                if ($this->subHostByUsername) {
                    // 针对GG转发(ny.bijia.me)使用用户名作为子域名。
                    $host = $this->username . '.' . ltrim($host, '.');
                }

                $port = $item['listen_port'];
                $link = preg_replace('/\{host}/', $host, $link);
                $link = preg_replace('/\{port}/', $port, $link);
                $link = preg_replace('/\{label}/', rawurlencode($label), $link);
                $links[$label] = $link;
            }
        }

        $this->links = $links;
    }
}