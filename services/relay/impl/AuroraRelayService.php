<?php

namespace app\services\relay\impl;

use app\services\filter\FilterProtocolService;
use GuzzleHttp\Client;
use Yii;

/**
 * 极光面板
 *
 * 目前服务器、端口没有做分页支持，最大支持100个服务器+100个端口<br />
 * 而且，支持再多也没用，这个面板的管理大部分不支持批量操作，真要加这么多端口什么的都加死个人。
 */
class AuroraRelayService extends AbstractRelayService
{
    protected $host;

    protected $username;

    protected $password;

    protected $authToken;

    /**
     * @var array{
     *     array{
     *         address: string,
     *         id: int,
     *         name: string,
     *         ports: array
     *     }
     * }
     */
    protected $servers = [];

    protected $forwardList = [];

    protected $nodeList = [];

    protected $sslVerify = false;

    protected $hostList = [];

    public function run()
    {
        $this->getNodeList();
        $this->getAuthToken();
        $this->getServers();
        $this->getForwardList();
        $this->generateRelayedList();
        return $this->links;
    }

    private function getAuthToken()
    {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/token', [
            'form_params' => [
                'username' => $this->username,
                'password' => $this->password,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('登录失败：解析响应失败');
        }

        if (!isset($response['access_token'])) {
            throw new \RuntimeException('登录失败：' . $response['detail'] ?? '未知原因');
        }

        $this->authToken = 'Bearer ' . $response['access_token'];
    }

    private function getServers()
    {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/v2/servers', [
            'query' => [
                'page' => 0,
                'size' => 100,
            ],
            'headers' => [
                'Authorization' => $this->authToken,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取服务器：解析响应失败');
        }

        if (!isset($response['items'])) {
            throw new \RuntimeException('获取服务器：' . $response['detail'] ?? '未知原因');
        }

        $this->servers = $response['items'];
        $this->servers = array_column($this->servers, null, 'id');
    }

    private function getForwardList()
    {
        foreach ($this->servers as &$server) {
            $url = '/api/v2/servers/' . $server['id'] . '/ports';

            $client = new Client();
            $res = $client->request('GET', $this->host . $url, [
                'headers' => [
                    'Authorization' => $this->authToken,
                ],
                'query' => [
                    'page' => 0,
                    'size' => 100,
                ],
                'verify' => $this->sslVerify,
            ]);

            $response = json_decode($res->getBody()->getContents(), true);
            if (!$response) {
                throw new \RuntimeException('获取转发列表：解析响应失败');
            }

            if (!isset($response['items'])) {
                throw new \RuntimeException('获取转发列表：' . $response['detail'] ?? '未知原因');
            }

            $list = $response['items'];
            foreach ($list as &$item) {
                $item['server'] = &$server;
            }
            unset($item);

            $this->forwardList = array_merge($this->forwardList, $list);
        }
        unset($server);
    }

    private function generateRelayedList()
    {
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);

        $links = [];

        foreach ($this->forwardList as $item) {
            // 从备注中读取信息
            $notes = json_decode($item['notes'], true);
            if (empty($notes)) {
                $notes = [
                    'source' => $item['notes'],
                ];
            }

            $nodeKey = $notes['source'] ?? '';
            $sourceNodes = $this->nodeList[$nodeKey] ?? null;
            if (empty($sourceNodes)) {
                continue;
            }

            $serverName = $item['server']['name'] ?? '';
            $hostList = $this->hostList[$serverName] ?? [
                $item['server']
            ];

            // 同一个host+端口，可以有多个不通协议的服务。
            foreach ($sourceNodes as $sourceNode) {
                // 过滤协议
                if (!in_array($sourceNode['protocol'], $outputProtocol)) {
                    continue;
                }

                foreach ($hostList as $aHost) {
                    $link = $sourceNode['link'];
                    $label = sprintf(
                        '%s-%s-%s%s-%s',
                        $sourceNode['name'],
                        $this->name,
                        $aHost['name'],
                        isset($notes['remark']) ? '-' . $notes['remark'] : '',
                        $sourceNode['protocol']
                    );

                    $host = $aHost['address'];
                    $port = $item['num'];
                    $link = preg_replace('/\{host}/', $host, $link);
                    $link = preg_replace('/\{port}/', $port, $link);
                    $link = preg_replace('/\{label}/', rawurlencode($label), $link);
                    $links[$label] = $link;
                }
            }
        }
        $this->links = $links;
    }
}