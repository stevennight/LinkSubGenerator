<?php

namespace app\services\relay\impl;

use app\services\filter\FilterProtocolService;
use GuzzleHttp\Client;
use Yii;

class OcentRelayService extends AbstractRelayService
{
    protected $host;

    protected $username;

    protected $password;

    protected $authToken;

    protected $productList;

    protected $nodeList;

    protected $sslVerify = false;

    public function run()
    {
        $this->getNodeList();
        $this->getAuthToken();
        $this->getProductList();
        $this->getProxyLists();
        return $this->links;
    }

    private function getAuthToken()
    {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/v1/user/login', [
            'json' => [
                'email' => $this->username,
                'password' => $this->password,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('登录失败：解析响应失败');
        }

        if ($response['status_code'] !== 0) {
            throw new \RuntimeException('登录失败：' . $response['status_msg']);
        }

        $this->authToken = $response['data']['token'] ?? '';
    }

    /**
     * 获取产品列表（可能有多个产品）
     *
     * @return void
     */
    private function getProductList() {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/v1/turbox/product', [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'verify' => $this->sslVerify,
        ]);
        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取产品列表：解析响应失败');
        }
        if ($response['status_code'] !== 0) {
            throw new \RuntimeException('获取产品列表：' . $response['status_msg']);
        }

        $this->productList = $response['data'];
    }

    /**
     * 处理产品列表中每个产品的转发列表
     *
     * @return void
     */
    private function getProxyLists() {
        foreach ($this->productList as $productInfo) {
            $this->getProxyList($productInfo);
        }
    }

    /**
     * 处理指定产品的转发列表，并生成links数据
     *
     * @param int $productInfo
     * @return void
     */
    private function getProxyList(array $productInfo) {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/v1/turbox/product/' . $productInfo['id'], [
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'verify' => $this->sslVerify,
        ]);
        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取转发列表：解析响应失败');
        }
        if ($response['status_code'] !== 0) {
            throw new \RuntimeException('获取转发列表：' . $response['status_msg']);
        }

        // 获取流量等信息
        $uploadFlow = $productInfo['upload_used'] ?? 0;
        $downloadFlow = $productInfo['download_used'] ?? 0;
        $usedFlow = $uploadFlow + $downloadFlow;
        $usedFlow = $usedFlow / 1024 / 1024 / 1024; // 单位G
        $totalFlow = $productInfo['flow'] ?? 0;
        $totalFlow = $totalFlow / 1024 / 1024 / 1024; // 单位G
        $remainFlow = $totalFlow - $usedFlow;
        $remainFlow = max($remainFlow, 0);

        # 新增一条中转账号信息
        $links = [];
        $label = sprintf(
            '[服务信息]%s【过期时间：%s，剩余流量：%s】',
            $this->name,
            isset($productInfo['due_time']) ?
                date('Y-m-d H:i:s', $productInfo['due_time']) : '获取失败',
            number_format($remainFlow, 2) . 'G'
        );
        $links[$label] = 'ss://bm9uZTow@' . uniqid() . ':8888#' . rawurlencode($label);

        // 组装最终链接
        $proxies = $response['data']['connections'];
        $nodes = array_column($response['data']['nodes'], null, 'id');
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);
        foreach ($proxies as $proxy) {
            $nodeId = $proxy['node_id'];
            if (!isset($nodes[$nodeId])) {
                continue;
            }
            $host = $nodes[$nodeId]['endpoints'] ?? '';
            $hostLabel = $nodes[$nodeId]['name'] ?? '';
            $port = $proxy['local_port'];

            $nodeKey = $proxy['address'] . ':' . $proxy['port'];
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
                    $hostLabel . '*1',
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
}