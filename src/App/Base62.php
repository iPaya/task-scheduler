<?php


namespace App;


class Base62
{
    /**
     * @param string $input
     * @return string
     */
    static public function encodeString($input)
    {
        $input = crc32($input);
        $result = sprintf("%u", $input);
        $rs = '';
        while ($result > 0) {
            $s = $result % 62;
            if ($s > 35) {
                $s = chr($s + 61);
            } elseif ($s > 9 && $s <= 35) {
                $s = chr($s + 55);
            }
            $rs .= $s;
            $result = floor($result / 62);
        }

        return $rs;
    }
}
