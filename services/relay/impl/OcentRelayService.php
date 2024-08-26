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
            $this->getProxyList($productInfo['id']);
        }
    }

    /**
     * 处理指定产品的转发列表，并生成links数据
     *
     * @param int $productId
     * @return void
     */
    private function getProxyList(int $productId) {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/v1/turbox/product/' . $productId, [
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

        // 组装最终链接
        $proxies = $response['data']['connections'];
        $nodes = array_column($response['data']['nodes'], null, 'id');
        $links = [];
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