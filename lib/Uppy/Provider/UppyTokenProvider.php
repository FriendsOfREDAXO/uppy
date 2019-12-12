<?php
/**
 * User: joachimdorr
 * Date: 11.12.19
 * Time: 12:08
 */

namespace Uppy\Provider;


use rex_csrf_token;

class UppyTokenProvider
{
    const TOKEN_ID = 'uppy';

    /**
     * @return string
     * @author Joachim Doerr
     */
    public static function getTokenParameterKey()
    {
        return rex_csrf_token::PARAM;
    }

    /**
     * @return string
     * @author Joachim Doerr
     */
    public static function getToken()
    {
        $token = rex_csrf_token::factory(self::TOKEN_ID);
        return $token->getValue();
    }

    /**
     * @param $token
     * @return bool
     * @author Joachim Doerr
     */
    public static function isTokenValid()
    {
        $token = rex_csrf_token::factory(self::TOKEN_ID);
        return $token->isValid();
    }
}