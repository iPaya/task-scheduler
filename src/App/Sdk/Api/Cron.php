<?php


namespace App\Sdk\Api;


class Cron extends Api
{
    /**
     * @return array
     */
    public function lists()
    {
        return $this->httpGet('cron/list');
    }

    /**
     * @return integer
     */
    public function version()
    {
        $result = $this->httpGet('cron/version');
        return $result['version'];
    }
}
