<?php

namespace app\commands;

use app\services\relay\RelayService;
use Yii;
use yii\console\Controller;

class TestZeroController extends Controller
{
    public function actionIndex()
    {
        echo "Starting Zero Relay Service Test...\n";

        $relayService = new RelayService();
        
        // 模拟请求参数
        $data = [
            'type' => 'ss', // 假设我们要获取 SS 协议的节点
            'forceRefresh' => 1, // 强制刷新，不走缓存
        ];

        // 为了只测试 Zero 面板，我们可以临时过滤 relayList
        // 或者我们可以直接实例化 ZeroRelayService，但 RelayService 封装了通用逻辑
        
        // 这里我先备份原始的 relayList，然后只保留 Zero 的配置
        $originalRelayList = Yii::$app->params['relayList'];
        
        $zeroConfig = null;
        foreach ($originalRelayList as $relay) {
            if (($relay['type'] ?? '') === 'zero') {
                $zeroConfig = $relay;
                break;
            }
        }
        
        if (!$zeroConfig) {
            echo "Error: No Zero configuration found in relayList.php\n";
            return;
        }
        
        echo "Found Zero configuration: " . $zeroConfig['name'] . "\n";
        
        // 覆盖 params
        Yii::$app->params['relayList'] = [$zeroConfig];
        
        try {
            $result = $relayService->run($data);
            
            echo "Test Completed.\n";
            echo "Result count: " . count($result) . "\n";
            
            foreach ($result as $name => $link) {
                echo "--------------------------------------------------\n";
                echo "Name: $name\n";
                echo "Link: $link\n";
            }
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }
    }
}
