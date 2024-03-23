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

    protected $authToken;

    protected $deviceGroup;

    protected $forwardList;

    protected $relayNodeList;

    protected $sslVerify = false;

    public function run()
    {
        $this->getRelayNodeList();
        $this->getAuthToken();
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
        // todo::测试
//        $this->forwardList =
//            '[{"id":5161,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Viie SONET IPV6-\u5e7f\u6e2fIEPL","uid":877,"listen_port":23591,"device_group_in":621,"device_group_out":622,"traffic_used":151774,"config":"{\"dest\":[\"jp.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:22 CST"},{"id":5157,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Viie SONET IPV6-\u6caa\u79fb\u81ea\u5907\u9999\u6e2f2","uid":877,"listen_port":19719,"device_group_in":21,"device_group_out":914,"traffic_used":57909,"config":"{\"dest\":[\"jp.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":5156,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Viie SONET IPV6-\u6caa\u79fb\u81ea\u5907\u9999\u6e2f","uid":877,"listen_port":16128,"device_group_in":21,"device_group_out":913,"traffic_used":589642077,"config":"{\"dest\":[\"jp.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":5155,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Viie SONET IPV6-\u5e7f\u79fb\u81ea\u5907\u9999\u6e2f2","uid":877,"listen_port":16925,"device_group_in":236,"device_group_out":914,"traffic_used":126058858,"config":"{\"dest\":[\"jp.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":5154,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Viie SONET IPV6-\u5e7f\u79fb\u81ea\u5907\u9999\u6e2f","uid":877,"listen_port":16984,"device_group_in":236,"device_group_out":913,"traffic_used":529947,"config":"{\"dest\":[\"jp.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":5153,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Viie SONET IPV6-\u5546\u5bb6\u51fa\u53e3","uid":877,"listen_port":14001,"device_group_in":236,"device_group_out":915,"traffic_used":51916,"config":"{\"dest\":[\"jp.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":5142,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Viie Hinet NAT-\u5546\u5bb6\u51fa\u53e3","uid":877,"listen_port":18032,"device_group_in":236,"device_group_out":915,"traffic_used":81126,"config":"{\"dest\":[\"tw-3.travel.24-7to.icu:50142\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":5141,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Viie Hinet NAT-\u81ea\u5907\u9999\u6e2f","uid":877,"listen_port":14947,"device_group_in":21,"device_group_out":913,"config":"{\"dest\":[\"tw-3.travel.24-7to.icu:50142\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":5140,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Viie Hinet NAT-\u81ea\u5907\u9999\u6e2f2","uid":877,"listen_port":10507,"device_group_in":21,"device_group_out":914,"config":"{\"dest\":[\"tw-3.travel.24-7to.icu:50142\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":5139,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Viie Hinet NAT-\u5e7f\u6e2fIEPL","uid":877,"listen_port":23274,"device_group_in":621,"device_group_out":622,"config":"{\"dest\":[\"tw-3.travel.24-7to.icu:50142\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:22 CST"},{"id":5138,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Viie Hinet NAT-\u81ea\u5907\u9999\u6e2f2","uid":877,"listen_port":17636,"device_group_in":236,"device_group_out":914,"traffic_used":159732,"config":"{\"dest\":[\"tw-3.travel.24-7to.icu:50142\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":5137,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Viie Hinet NAT-\u81ea\u5907\u9999\u6e2f","uid":877,"listen_port":10474,"device_group_in":236,"device_group_out":913,"traffic_used":674901044,"config":"{\"dest\":[\"tw-3.travel.24-7to.icu:50142\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4703,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011V5.net","uid":877,"listen_port":14924,"device_group_in":236,"device_group_out":913,"config":"{\"dest\":[\"hk-5.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4702,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Sharon","uid":877,"listen_port":14923,"device_group_in":236,"device_group_out":913,"config":"{\"dest\":[\"hk-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4701,"name":"\u3010\ud83c\uddfa\ud83c\uddf8US\u3011Akile 4837V4\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14922,"device_group_in":236,"device_group_out":913,"config":"{\"dest\":[\"us-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4700,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14921,"device_group_in":236,"device_group_out":913,"traffic_used":42062,"config":"{\"dest\":[\"tw-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4699,"name":"\u3010\ud83c\uddf8\ud83c\uddecSG\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14920,"device_group_in":236,"device_group_out":913,"config":"{\"dest\":[\"sg-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4698,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14919,"device_group_in":236,"device_group_out":913,"config":"{\"dest\":[\"193.246.161.238:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4697,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14918,"device_group_in":236,"device_group_out":913,"traffic_used":2691432866,"config":"{\"dest\":[\"hk-3.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4696,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011V5.net","uid":877,"listen_port":14917,"device_group_in":236,"device_group_out":914,"traffic_used":346,"config":"{\"dest\":[\"hk-5.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4695,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Sharon","uid":877,"listen_port":14916,"device_group_in":236,"device_group_out":914,"traffic_used":804,"config":"{\"dest\":[\"hk-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4694,"name":"\u3010\ud83c\uddfa\ud83c\uddf8US\u3011Akile 4837V4\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14915,"device_group_in":236,"device_group_out":914,"config":"{\"dest\":[\"us-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4693,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14914,"device_group_in":236,"device_group_out":914,"traffic_used":203588,"config":"{\"dest\":[\"tw-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4692,"name":"\u3010\ud83c\uddf8\ud83c\uddecSG\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14913,"device_group_in":236,"device_group_out":914,"config":"{\"dest\":[\"sg-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4691,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14912,"device_group_in":236,"device_group_out":914,"config":"{\"dest\":[\"193.246.161.238:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4690,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14911,"device_group_in":236,"device_group_out":914,"traffic_used":852593172,"config":"{\"dest\":[\"hk-3.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4689,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14910,"device_group_in":236,"device_group_out":625,"traffic_used":241427,"config":"{\"dest\":[\"hk-3.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4688,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011V5.net","uid":877,"listen_port":14909,"device_group_in":236,"device_group_out":625,"traffic_used":71774,"config":"{\"dest\":[\"hk-5.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4687,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Sharon","uid":877,"listen_port":14908,"device_group_in":236,"device_group_out":625,"traffic_used":794485,"config":"{\"dest\":[\"hk-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4686,"name":"\u3010\ud83c\uddfa\ud83c\uddf8US\u3011Akile 4837V4\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14907,"device_group_in":236,"device_group_out":625,"config":"{\"dest\":[\"us-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4685,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14906,"device_group_in":236,"device_group_out":625,"config":"{\"dest\":[\"tw-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4684,"name":"\u3010\ud83c\uddf8\ud83c\uddecSG\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14905,"device_group_in":236,"device_group_out":625,"config":"{\"dest\":[\"sg-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4683,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14904,"device_group_in":236,"device_group_out":625,"config":"{\"dest\":[\"193.246.161.238:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4682,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011V5.net","uid":877,"listen_port":14902,"device_group_in":21,"device_group_out":913,"config":"{\"dest\":[\"hk-5.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4681,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Sharon","uid":877,"listen_port":14901,"device_group_in":21,"device_group_out":913,"config":"{\"dest\":[\"hk-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4680,"name":"\u3010\ud83c\uddfa\ud83c\uddf8US\u3011Akile 4837V4\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14900,"device_group_in":21,"device_group_out":913,"config":"{\"dest\":[\"us-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4679,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14899,"device_group_in":21,"device_group_out":913,"traffic_used":56074,"config":"{\"dest\":[\"tw-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4678,"name":"\u3010\ud83c\uddf8\ud83c\uddecSG\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14898,"device_group_in":21,"device_group_out":913,"config":"{\"dest\":[\"sg-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4677,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14897,"device_group_in":21,"device_group_out":913,"config":"{\"dest\":[\"193.246.161.238:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4676,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14896,"device_group_in":21,"device_group_out":913,"traffic_used":4020987802,"config":"{\"dest\":[\"hk-3.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4674,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011V5.net","uid":877,"listen_port":14895,"device_group_in":21,"device_group_out":914,"config":"{\"dest\":[\"hk-5.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4673,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Sharon","uid":877,"listen_port":14894,"device_group_in":21,"device_group_out":914,"config":"{\"dest\":[\"hk-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4672,"name":"\u3010\ud83c\uddfa\ud83c\uddf8US\u3011Akile 4837V4\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14893,"device_group_in":21,"device_group_out":914,"config":"{\"dest\":[\"us-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4671,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14891,"device_group_in":21,"device_group_out":914,"traffic_used":151590,"config":"{\"dest\":[\"tw-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4670,"name":"\u3010\ud83c\uddf8\ud83c\uddecSG\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14890,"device_group_in":21,"device_group_out":914,"config":"{\"dest\":[\"sg-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4669,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14889,"device_group_in":21,"device_group_out":914,"config":"{\"dest\":[\"193.246.161.238:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4668,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14888,"device_group_in":21,"device_group_out":914,"traffic_used":1243293501,"config":"{\"dest\":[\"hk-3.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:09 CST"},{"id":4667,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011V5.net","uid":877,"listen_port":26143,"device_group_in":621,"device_group_out":622,"traffic_used":204194,"config":"{\"dest\":[\"hk-5.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:22 CST"},{"id":4666,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Sharon","uid":877,"listen_port":20119,"device_group_in":621,"device_group_out":622,"traffic_used":10399,"config":"{\"dest\":[\"hk-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:22 CST"},{"id":4664,"name":"\u3010\ud83c\uddfa\ud83c\uddf8US\u3011Akile 4837V4\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14893,"device_group_in":208,"device_group_out":209,"traffic_used":170245,"config":"{\"dest\":[\"us-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:12 CST"},{"id":4663,"name":"\u3010\ud83c\uddf9\ud83c\uddfcTW\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14891,"device_group_in":621,"device_group_out":622,"traffic_used":5180,"config":"{\"dest\":[\"tw-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:22 CST"},{"id":4662,"name":"\u3010\ud83c\uddf8\ud83c\uddecSG\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14890,"device_group_in":621,"device_group_out":622,"traffic_used":205635,"config":"{\"dest\":[\"sg-2.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:22 CST"},{"id":4661,"name":"\u3010\ud83c\uddef\ud83c\uddf5JP\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14889,"device_group_in":208,"device_group_out":209,"traffic_used":49794774,"config":"{\"dest\":[\"193.246.161.238:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:12 CST"},{"id":4660,"name":"\u3010\ud83c\udded\ud83c\uddf0HK\u3011Akile\uff08\u89e3\u9501\uff09","uid":877,"listen_port":14888,"device_group_in":621,"device_group_out":622,"traffic_used":862275468,"config":"{\"dest\":[\"hk-3.travel.24-7to.icu:23456\"]}","status":"ForwardRuleStatus_Normal","display_updated_at":"2024-03-21 19:41:22 CST"}]';
//        $this->forwardList = json_decode($this->forwardList, true);
//        return;

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

    private function getRelayNodeList()
    {
        $list = Yii::$app->params['relayNodeList'];
        $res = [];
        foreach ($list as $item) {
            $key = $item['sourceHost'] . ':' . $item['sourcePort'];
            // 同一个host+端口，可以有多个不通协议的服务。
            $res[$key][] = $item;
        }
        $this->relayNodeList = $res;
    }

    private function generateRelayedList()
    {
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);

        $links = [];
        foreach ($this->forwardList as $item) {
            $config = json_decode($item['config'], true);
            $nodeKey = current($config['dest']);
            $sourceNodes = $this->relayNodeList[$nodeKey] ?? null;
            if (empty($sourceNodes)) {
                continue;
            }

            // 同一个host+端口，可以有多个不通协议的服务。
            foreach ($sourceNodes as $sourceNode) {
                // 过滤协议
                if (!in_array($sourceNode['protocol'], $outputProtocol)) {
                    continue;
                }

                $deviceGroupIn = $this->deviceGroup[$item['device_group_in']] ?? 0;
                $deviceGroupOut = $this->deviceGroup[$item['device_group_out']] ?? 0;
                if (!$deviceGroupIn || !$deviceGroupOut) {
                    continue;
                }

                $link = $sourceNode['link'];
                $label = sprintf(
                    '%s-%s-%s-%s-%s',
                    $sourceNode['name'],
                    $this->name,
                    $deviceGroupIn['name'],
                    $deviceGroupOut['name'],
                    $sourceNode['protocol']
                );
                $host = $deviceGroupIn['connect_host'];
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