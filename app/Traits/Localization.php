<?php

namespace App\Traits;

use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

trait Localization
{
    /**
     * get localization from request
     * ```
     * en => english
     * id => indoneisa
     * ```
     * @return string lowercase
     */
    public static function getLocale()
    {
        $lang = strtolower(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (str_contains($_SERVER['HTTP_ACCEPT_LANGUAGE'],"en")?"en":"id") : 'id');
        return in_array($lang, ['en','id'])?$lang:"id";
    }

    public function transTokenException(JWTException $exception)
    {
        $translationMap = [
            "en" => [
                "token-invalid" => "Invalid token",
                "token-expire" => "Token has been expire",
                "token-blacklist" => "Token has been blacklisted"
            ],
            "id" => [
                "token-invalid" => "Token tidak valid.",
                "token-expire" => "Token telah kedaluwarsa",
                "token-blacklist" => "Token telah masuk daftar hitam",
            ]
        ];

        if ($exception instanceof TokenInvalidException) {
            return $translationMap[$this->getLocale()]['token-invalid'];
        } else if ($exception instanceof TokenExpiredException) {
            return $translationMap[$this->getLocale()]['token-expire'];
        } else if ($exception instanceof TokenBlacklistedException) {
            return $translationMap[$this->getLocale()]['token-blacklist'];
        }
    }
}
