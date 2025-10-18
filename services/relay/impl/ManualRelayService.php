<?php

namespace app\services\relay\impl;

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
    public function run(): array
    {
        $this->nodeList = $this->generateManualRelayedList();
        $this->links = $this->buildManualFlowLinks();
        
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nodeList' => $this->nodeList,
            'links' => $this->links,
        ];
    }

    /**
     * 生成手动中转列表
     * @return array
     */
    protected function generateManualRelayedList(): array
    {
        $nodeList = [];
        
        foreach ($this->manualTunnels as $tunnel) {
            $nodeList[] = [
                'name' => $this->generateManualLabel($tunnel),
                'type' => $this->type,
                'host' => $this->getManualHost($tunnel),
                'port' => $this->getManualPort($tunnel),
                'protocol' => $this->getManualProtocol($tunnel),
            ];
        }
        
        return $nodeList;
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
     * @return string
     */
    protected function generateManualLabel(array $tunnel): string
    {
        if (!empty($this->customLabelFormat)) {
            $label = $this->customLabelFormat;
            $label = str_replace('{source_name}', $tunnel['source_name'] ?? '', $label);
            $label = str_replace('{host}', $tunnel['host'] ?? '', $label);
            $label = str_replace('{port}', $tunnel['port'] ?? '', $label);
            $label = str_replace('{protocol}', $tunnel['protocol'] ?? '', $label);
            $label = str_replace('{tunnel_type}', $tunnel['tunnel_type'] ?? '', $label);
            $label = str_replace('{remark}', $tunnel['remark'] ?? '', $label);
            return $label;
        }
        
        return $tunnel['source_name'] ?? ($tunnel['host'] . ':' . $tunnel['port']);
    }

    /**
     * 生成手动链接
     * @param array $tunnel
     * @return string
     */
    protected function generateManualLink(array $tunnel): string
    {
        $protocol = $tunnel['protocol'] ?? '';
        $host = $tunnel['host'] ?? '';
        $port = $tunnel['port'] ?? '';
        
        if ($protocol && $host && $port) {
            return $protocol . '://' . $host . ':' . $port;
        }
        
        return '';
    }

    /**
     * 构建手动流量链接
     * @return array
     */
    protected function buildManualFlowLinks(): array
    {
        $links = [];
        
        if ($this->enableFlowInfo && !empty($this->manualUserInfo)) {
            $serviceName = $this->manualUserInfo['service_name'] ?? $this->serviceName;
            $expiredAt = $this->manualUserInfo['expired_at'] ?? '';
            
            if ($serviceName && $expiredAt) {
                $label = $serviceName . ' (过期时间: ' . $expiredAt . ')';
                $links[$label] = '#';
            }
        }
        
        return $links;
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