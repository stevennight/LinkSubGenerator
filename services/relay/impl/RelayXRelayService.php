<?php

namespace app\services\relay\impl;

use app\services\filter\FilterProtocolService;
use GuzzleHttp\Client;
use Yii;

class RelayXRelayService extends AbstractRelayService
{
    protected $host;

    protected $email;

    protected $password;

    protected $userDetail;

    protected $subHostByUsername;

    protected $authToken;

    protected $refreshToken;

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
        $this->getUserInfo();
        $this->getTunnelList();
        $this->generateRelayedList();
        return $this->links;
    }

    private function getAuthToken()
    {
        $client = new Client();
        $res = $client->request('POST', $this->host . '/api/v1/auth/password/sign-in', [
            'json' => [
                'email' => $this->email,
                'password' => $this->password,
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('登录失败：解析响应失败');
        }

        if (!isset($response['access_token'])) {
            throw new \RuntimeException('登录失败：未获取到access_token');
        }

        $this->authToken = $response['access_token'];
        $this->refreshToken = $response['refresh_token'];
    }

    private function getUserInfo()
    {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/user/info', [
            'headers' => [
                'Cookie' => 'stack-access=' . urlencode('["' . $this->refreshToken . '","' . $this->authToken . '"]'),
            ],
            'verify' => $this->sslVerify,
        ]);
        
        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取用户信息：解析响应失败');
        }

        $this->userDetail = $response;
    }

    private function getTunnelList()
    {
        $client = new Client();
        $res = $client->request('GET', $this->host . '/api/tunnel?perPage=1000', [
            'headers' => [
                'Cookie' => 'stack-access=' . urlencode('["' . $this->refreshToken . '","' . $this->authToken . '"]'),
            ],
            'verify' => $this->sslVerify,
        ]);

        $response = json_decode($res->getBody()->getContents(), true);
        if (!$response) {
            throw new \RuntimeException('获取隧道列表：解析响应失败');
        }

        if (!isset($response['data'])) {
            throw new \RuntimeException('获取隧道列表：未获取到data字段');
        }

        $this->forwardList = $response['data'];
    }

    private function generateRelayedList()
    {
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);

        $links = $this->buildFlowLinks();

        foreach ($this->forwardList as $item) {
            // 使用name字段匹配原连接
            $sourceNodes = $this->nodeList[$item['name']] ?? null;
            
            if (empty($sourceNodes)) {
                continue;
            }

            // 同一个host+端口，可以有多个不同协议的服务
            foreach ($sourceNodes as $sourceNode) {
                // 过滤协议
                if (!in_array($sourceNode['protocol'], $outputProtocol)) {
                    continue;
                }

                // 获取监听端口
                $currentPort = $item['listen_port'];
                if (empty($currentPort)) {
                    continue;
                }

                $link = $sourceNode['link'];
                $label = sprintf(
                    '%s-%s-%s-%s',
                    $sourceNode['name'],
                    $this->name,
                    $item['tunnel_type'],
                    $sourceNode['protocol']
                );

                // 获取入口IP
                $host = '';
                if (!empty($item['in_node_group']['nodes']) && is_array($item['in_node_group']['nodes'])) {
                    $firstNode = $item['in_node_group']['nodes'][0];
                    $host = $firstNode['connect_ip'] ?? '';
                }
                
                if (empty($host)) {
                    continue;
                }
                
                if ($this->subHostByUsername) {
                    // 针对特定转发使用用户名作为子域名
                    $username = $this->userDetail['email'] ?? '';
                    $username = explode('@', $username)[0] ?? '';
                    $host = $username . '.' . ltrim($host, '.');
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
        $usedFlow = $this->userDetail['user_plan']['traffic_used'] ?? 0;
        $usedFlow = $usedFlow / 1024 / 1024 / 1024; // 单位G
        $totalFlow = $this->userDetail['user_plan']['traffic'] ?? 0;
        $totalFlow = $totalFlow / 1024 / 1024 / 1024; // 单位G
        $remainFlow = $totalFlow - $usedFlow;
        $remainFlow = max($remainFlow, 0);

        // 获取过期时间
        $expTime = $this->userDetail['user_plan']['expired_at'] ?? '';
        $expTimeStr = '获取失败';
        if (!empty($expTime)) {
            try {
                $expTimeStr = date('Y-m-d H:i:s', strtotime($expTime));
            } catch (\Exception $e) {
                $expTimeStr = '解析失败';
            }
        }

        # 新增一条中转账号信息
        $label = sprintf(
            '[服务信息]%s【过期时间：%s，剩余流量：%s】',
            $this->name,
            $expTimeStr,
            number_format($remainFlow, 2) . 'G'
        );
        return [
            $label => 'ss://bm9uZTow@' . uniqid() . ':8888#' . rawurlencode($label),
        ];
    }
}