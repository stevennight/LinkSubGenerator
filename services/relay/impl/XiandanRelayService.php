<?php

namespace app\services\relay\impl;

use app\services\filter\FilterProtocolService;
use GuzzleHttp\Client;
use Yii;

/**
 * 咸蛋转发
 */
class XiandanRelayService extends AbstractRelayService
{
    protected $host;

    protected $username;

    protected $password;

    protected $authToken;

    protected $userId;

    protected $userDetail;

    protected $serverList;

    protected $nodeList;

    protected $sslVerify = false;

    public function run()
    {
        $this->getNodeList();
        $this->getAuthToken();
        $this->getUserDetail();
        $this->getServerList();
        $this->getProxyLists();
        return $this->links;
    }

    private function getAuthToken()
    {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/login', [
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

        if ((int) $response['code'] !== 0) {
            throw new \RuntimeException('登录失败：' . $response['msg']);
        }

        $this->authToken = $response['data']['token'] ?? '';
        $this->userId = $response['data']['userId'] ?? 0;
    }

    private function getUserDetail() {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/user/getUserDetail', [
            'headers' => [
                'x-token' => $this->authToken,
            ],
            'query' => [
                'userId' => $this->userId,
            ],
            'verify' => $this->sslVerify,
        ]);
        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取用户详情：解析响应失败');
        }
        if ((int) $response['code'] !== 0) {
            throw new \RuntimeException('获取用户详情：' . $response['msg']);
        }

        $this->userDetail = $response['data'];
    }

    /**
     * 获取服务器列表
     *
     * @return void
     */
    private function getServerList() {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/server/getForwardServerList', [
            'headers' => [
                'x-token' => $this->authToken,
            ],
            'query' => [
                'userId' => $this->userId,
                'pageSize' => 10000,
                'pageNum' => 1,
                'pageTotal' => 0,
            ],
            'verify' => $this->sslVerify,
        ]);
        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取服务列表：解析响应失败');
        }
        if ((int) $response['code'] !== 0) {
            throw new \RuntimeException('获取服务列表：' . $response['msg']);
        }

        $this->serverList = $response['data'];
    }

    /**
     * 处理产品列表中每个产品的转发列表
     *
     * @return void
     */
    private function getProxyLists() {
        foreach ($this->serverList as $serverInfo) {
            $this->getProxyList($serverInfo);
        }
    }

    /**
     * 处理指定产品的转发列表，并生成links数据
     *
     * @param int $serverInfo
     * @return void
     */
    private function getProxyList(array $serverInfo) {
        $serverInfo['flowRate'] = $serverInfo['flowRate'] ?? 1;

        $client = new Client();
        $res = $client->request('POST', $this->host . '/forward/getPage', [
            'headers' => [
                'x-token' => $this->authToken,
            ],
            'json' => [
                'pageNum' => 1,
                'pageSize' => 48,
                'pageTotal' => 0,
                'queryStr' => null,
                'serverId' => $serverInfo['id'],
                'userId' => $this->userId,
            ],
            'verify' => $this->sslVerify,
        ]);
        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取转发列表：解析响应失败');
        }
        if ((int) $response['code'] !== 0) {
                throw new \RuntimeException('获取转发列表：' . $response['msg']);
        }
        $proxies = $response['data']['list'];

        // 获取流量等信息
        $usedFlow = $this->userDetail['dataUsage'] ?? 0;
        $usedFlow = $usedFlow / 1024 / 1024 / 1024; // 单位G
        $totalFlow = $this->userDetail['dataLimit'] ?? 0;
        $remainFlow = $totalFlow - $usedFlow;
        $remainFlow = max($remainFlow, 0);

        # 新增一条中转账号信息
        $label = sprintf(
            '[服务信息]%s【过期时间：%s，剩余流量：%s】',
            $this->name,
            isset($this->userDetail['expireTime']) ?
                date('Y-m-d H:i:s', strtotime($this->userDetail['expireTime'])) : '获取失败',
            number_format($remainFlow, 2) . 'G'
        );
        $links[$label] = 'ss://bm9uZTow@0.0.0.0:8888#' . rawurlencode($label);

        $links = [];
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);
        foreach ($proxies as $proxy) {
            $host = $proxy['serverHost'] ?? '';
            $hostLabel = $proxy['serverName'] ?? '';
            $hostLabel = $hostLabel . '*' . $serverInfo['flowRate'];
            $port = $proxy['localPort'];

            $nodeKey = $proxy['remoteHost'] . ':' . $proxy['remotePort'];
            $sourceNodes = $this->nodeList[$nodeKey] ?? null;

            // 同一个host+端口，可以有多个不通协议的服务。
            foreach ($sourceNodes as $sourceNode) {
                // 过滤协议
                if (!in_array($sourceNode['protocol'], $outputProtocol)) {
                    continue;
                }

                $link = $sourceNode['link'];
                $label = sprintf(
                    '%s-%s-%s-%s',
                    $sourceNode['name'],
                    $this->name,
                    $hostLabel,
                    $sourceNode['protocol']
                );

                $link = preg_replace('/\{host}/', $host, $link);
                $link = preg_replace('/\{port}/', $port, $link);
                $link = preg_replace('/\{label}/', rawurlencode($label), $link);
                $links[$label] = $link;
            }
        }

        $this->links = array_merge($this->links, $links);
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
}