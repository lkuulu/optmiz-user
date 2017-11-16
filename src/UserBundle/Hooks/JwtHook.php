<?php

namespace STHUser\Hooks;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\JWT;
use WyriHaximus\SliFly\FlysystemServiceProvider;

class JwtHook
{
    CONST ROOT = '/var/www/optmiz/image/files/';
    CONST KEY = 'my key is secret';

    protected $data;

    public function getData()
    {
        return $this->data;
    }

    public function __invoke(Request $request, Application $app)
    {

        if ($request->getMethod() == 'OPTIONS') {
            return true;
        }

//        {
//            "alg": "HS256",
//              "typ": "JWT"
//        }
//        {
//            "repository": "repository",
//              "name": "lkuulu",
//              "admin": true
//        }

        $requestHeaders = $request->headers;

        $bearer = $requestHeaders->get('Authorization');

        if (!isset($bearer)) {
            return new Response('401 Authentication error', 401);
        } else {
            $bearers = explode(' ', $bearer);
            if (isset($bearers[1])) {
                $data = JWT::decode($bearers[1], SELF::KEY, array('HS256'));
                $app['jwt.user'] = $data;
                $app['repository'] = SELF::ROOT . $data->repository;
            }
        }
    }
}
