<?php

namespace app\services\relay\impl;

use app\services\filter\FilterProtocolService;
use GuzzleHttp\Client;
use Yii;

class DoraemonRelayService extends AbstractRelayService
{
    protected $host;

    protected $username;

    protected $password;

    protected $userDetail;

    protected $subHostByUsername;

    protected $authToken;

    protected $forwardList;

    protected $nodeList;

    protected $sslVerify = false;

    /**
     * 有些场景面板没有对应的入口地址等信息，这里可以让在配置文件里面配。
     * @var array
     */
    protected $hostList = [];

    public function run()
    {
        $this->getNodeList();
        $this->getAuthToken();
        $this->getUserDetail();
        $this->getForwardList();
        $this->generateRelayedList();
        return $this->links;
    }

    private function getAuthToken()
    {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/v1/user/login', [
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

        $this->authToken = $response['data']['token'];
    }

    private function getUserDetail() {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/v1/user/package', [
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

        $this->userDetail = $response['data']['userInfo'];
    }

    private function getForwardList()
    {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/v1/forward/list', [
            'headers' => [
                'Authorization' => $this->authToken,
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

    private function generateRelayedList()
    {
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);

        $links = $this->buildFlowLinks();

        foreach ($this->forwardList as $item) {
            // 根据文档，name字段可以与原连接的host:port进行匹配
            $sourceNodes = $this->nodeList[$item['name']] ?? null;
            
            // 如果通过name字段没有匹配到，尝试使用remoteAddr字段
            // 由于可能其他服务会中转到目标地址（比如v4->v6），如果拿这个地址作比较，那么就不那么可控。所以目前去掉。
//            if (empty($sourceNodes) && !empty($item['remoteAddr'])) {
//                $sourceNodes = $this->nodeList[$item['remoteAddr']] ?? null;
//            }
            
            if (empty($sourceNodes)) {
                continue;
            }

            // 同一个host+端口，可以有多个不通协议的服务。
            foreach ($sourceNodes as $sourceNode) {
                // 过滤协议
                if (!in_array($sourceNode['protocol'], $outputProtocol)) {
                    continue;
                }

                // 根据文档，inIp为入口ID，inPort为入口端口
                $currentPort = $item['inPort'];
                if (empty($currentPort)) {
                    continue;
                }

                $link = $sourceNode['link'];
                $label = sprintf(
                    '%s-%s-%s-%s',
                    $sourceNode['name'],
                    $this->name,
                    $item['tunnelName'],
                    $sourceNode['protocol']
                );

                $host = $item['inIp'] ?? '';
                if (empty($host)) {
                    continue;
                }
                if ($this->subHostByUsername) {
                    // 针对特定转发使用用户名作为子域名。
                    $host = $this->username . '.' . ltrim($host, '.');
                }

                $link = preg_replace('/\{host}/', $host, $link);
                $link = preg_replace('/\{port}/', $currentPort, $link);
                $link = preg_replace('/\{label}/', rawurlencode($label), $link);
                $links[$label] = $link;
            }
        }

        $this->links = $links;
    }

    public function buildFlowLinks(): array
    {
        // 获取流量等信息
        $usedFlow = $this->userDetail['inFlow'] ?? 0;
        $usedFlow = $usedFlow / 1024 / 1024 / 1024; // 单位G
        $totalFlow = $this->userDetail['flow'] ?? 0;
        $totalFlow = $totalFlow / 1024 / 1024 / 1024; // 单位G
        $remainFlow = $totalFlow - $usedFlow;
        $remainFlow = max($remainFlow, 0);

        # 新增一条中转账号信息
        $label = sprintf(
            '[服务信息]%s【过期时间：%s，剩余流量：%s】',
            $this->name,
            isset($this->userDetail['expTime']) ?
                date('Y-m-d H:i:s', $this->userDetail['expTime']/1000) : '获取失败',
            number_format($remainFlow, 2) . 'G'
        );
        return [
            $label => 'ss://bm9uZTow@' . uniqid() . ':8888#' . rawurlencode($label),
        ];
    }
}