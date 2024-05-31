<?php

namespace EightWebDesign\CentralNicResellerApi;

use SimpleXMLElement;

class CentralNicResellerResponseParser
{
    static public function parse(SimpleXMLElement $xml)
    {
        return self::parse_response_value($xml->params->param->value->struct);
    }

    static private function parse_response_member(SimpleXMLElement $member)
    {
        $name = (string) $member->name;
        $value = null;
        foreach ($member->value->children() as $child) {
            $value = self::parse_response_value($child);
        }
        return [$name, $value];
    }

    static private function parse_response_value(SimpleXMLElement $child)
    {
        switch ($child->getName()) {
            case 'double':
                return (float) $child;
            case 'string':
                return (string) $child;
            case 'int':
                return (int) $child;
            case 'struct':
                $value = [];
                foreach ($child->member as $m) {
                    list($n, $v) = self::parse_response_member($m);
                    $value[$n] = $v;
                }
                return $value;
            case 'array':
                $value = [];
                foreach ($child->data->value as $v) {
                    foreach ($v->children() as $c) {
                        $value[] = self::parse_response_value($c);
                    }
                }
                return $value;
        }
    }
}
