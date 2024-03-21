<?php

namespace app\services\filter;

class FilterProtocolService
{
    const ProtocolConvertMap = [
        1 => 'vless',
        2 => 'vmess',
        3 => 'trojan',
    ];

    public function getOutputProtocol($data)
    {
        $res = array_values(self::ProtocolConvertMap);

        $filterProtocol = $data['protocol'] ?? null;
        if ($filterProtocol) {
            $filterProtocolArr = explode(',', $filterProtocol);
            $res = [];
            foreach ($filterProtocolArr as $item) {
                if (isset(self::ProtocolConvertMap[$item])) {
                    $res[] = self::ProtocolConvertMap[$item];
                }
            }
        }

        return $res;
    }
}