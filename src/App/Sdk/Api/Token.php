<?php


namespace App\Sdk\Api;


class Token extends Api
{
    /**
     * @param string $token
     * @return bool
     */
    public function validate(string $token)
    {
        $result = $this->httpGet('token/validate', ['token' => $token]);
        return $result;
    }
}
