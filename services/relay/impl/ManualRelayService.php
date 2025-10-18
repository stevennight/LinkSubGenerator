<?php

namespace app\services\relay\impl;

use app\services\filter\FilterProtocolService;

/**
 * 手动配置的中转服务
 */
class ManualRelayService extends AbstractRelayService
{
    /**
     * @var array 手动配置的隧道列表
     */
    public $manualTunnels = [];

    /**
     * @var array 手动配置的用户信息
     */
    public $manualUserInfo = [];

    /**
     * @var string 自定义标签格式
     */
    public $customLabelFormat = '';

    /**
     * @var bool 是否启用流量信息
     */
    public $enableFlowInfo = true;

    /**
     * @var string 服务名称
     */
    public $serviceName = '';

    /**
     * 运行服务
     * @return array
     */
    public function run()
    {
        $this->getNodeList();
        $this->generateManualRelayedList();
        $this->buildManualFlowLinks();
        
        return $this->links;
    }

    /**
     * 生成手动中转列表
     */
    protected function generateManualRelayedList(): void
    {
        $outputProtocol = (new FilterProtocolService())->getOutputProtocol($this->data);
        
        foreach ($this->manualTunnels as $tunnel) {
            // 使用 source_name 匹配源节点
            $sourceNodes = $this->nodeList[$tunnel['source_name']] ?? null;
            
            if (empty($sourceNodes)) {
                continue;
            }

            foreach ($sourceNodes as $sourceNode) {
                // 协议过滤
                if (!in_array($sourceNode['protocol'], $outputProtocol)) {
                    continue;
                }

                // 生成标签
                $label = $this->generateManualLabel($tunnel, $sourceNode);
                
                // 生成链接
                $link = $this->generateManualLink($tunnel, $sourceNode, $label);
                
                if (!empty($link)) {
                    $this->links[$label] = $link;
                }
            }
        }
    }

    /**
     * 获取手动主机
     * @param array $tunnel
     * @return string
     */
    protected function getManualHost(array $tunnel): string
    {
        return $tunnel['host'] ?? '';
    }

    /**
     * 获取手动端口
     * @param array $tunnel
     * @return string
     */
    protected function getManualPort(array $tunnel): string
    {
        return $tunnel['port'] ?? '';
    }

    /**
     * 获取手动协议
     * @param array $tunnel
     * @return string
     */
    protected function getManualProtocol(array $tunnel): string
    {
        return $tunnel['protocol'] ?? '';
    }

    /**
     * 生成手动标签
     * @param array $tunnel
     * @param array $sourceNode
     * @return string
     */
    protected function generateManualLabel(array $tunnel, array $sourceNode): string
    {
        if (!empty($this->customLabelFormat)) {
            $label = $this->customLabelFormat;
            $label = str_replace('{source_name}', $tunnel['source_name'] ?? '', $label);
            $label = str_replace('{host}', $this->getManualHost($tunnel), $label);
            $label = str_replace('{port}', $this->getManualPort($tunnel), $label);
            $label = str_replace('{protocol}', $this->getManualProtocol($tunnel), $label);
            $label = str_replace('{tunnel_type}', $tunnel['tunnel_type'] ?? '', $label);
            $label = str_replace('{remark}', $tunnel['remark'] ?? '', $label);
            $label = str_replace('{relay_name}', $this->name ?? 'manual', $label);
            return $label;
        }
        
        // 默认标签格式
        return sprintf(
            '%s-%s-%s',
            $sourceNode['name'],
            $this->name ?? 'manual',
            $sourceNode['protocol']
        );
    }

    /**
     * 生成手动链接
     * @param array $tunnel
     * @param array $sourceNode
     * @param string $label
     * @return string
     */
    protected function generateManualLink(array $tunnel, array $sourceNode, string $label): string
    {
        $host = $this->getManualHost($tunnel);
        $port = $this->getManualPort($tunnel);
        
        if (empty($host) || empty($port)) {
            return '';
        }

        // 获取源节点的链接模板
        $link = $sourceNode['link'] ?? '';
        if (empty($link)) {
            return '';
        }

        // 替换链接中的占位符
        $link = preg_replace('/\{host\}/', $host, $link);
        $link = preg_replace('/\{port\}/', $port, $link);
        $link = preg_replace('/\{label\}/', rawurlencode($label), $link);

        return $link;
    }

    /**
     * 构建手动流量链接
     */
    protected function buildManualFlowLinks(): void
    {
        if (!$this->enableFlowInfo || empty($this->manualUserInfo)) {
            return;
        }

        $serviceName = $this->manualUserInfo['service_name'] ?? $this->serviceName;
        $expiredAt = $this->manualUserInfo['expired_at'] ?? '';
        
        if ($serviceName && $expiredAt) {
            $label = sprintf('[%s] 过期时间：%s', $serviceName, $expiredAt);
            $this->links[$label] = 'ss://bm9uZTow@' . uniqid() . ':8888#' . rawurlencode($label);
        }
    }

    /**
     * 设置手动隧道
     * @param array $manualTunnels
     */
    public function setManualTunnels(array $manualTunnels): void
    {
        $this->manualTunnels = $manualTunnels;
    }

    /**
     * 设置手动用户信息
     * @param array $manualUserInfo
     */
    public function setManualUserInfo(array $manualUserInfo): void
    {
        $this->manualUserInfo = $manualUserInfo;
    }

    /**
     * 设置自定义标签格式
     * @param string $customLabelFormat
     */
    public function setCustomLabelFormat(string $customLabelFormat): void
    {
        $this->customLabelFormat = $customLabelFormat;
    }

    /**
     * 设置是否启用流量信息
     * @param bool $enableFlowInfo
     */
    public function setEnableFlowInfo(bool $enableFlowInfo): void
    {
        $this->enableFlowInfo = $enableFlowInfo;
    }

    /**
     * 设置服务名称
     * @param string $serviceName
     */
    public function setServiceName(string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }
}