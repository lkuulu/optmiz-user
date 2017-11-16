<?php

namespace STHUser;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

use STHUser\Hooks\JwtHook;
use Firebase\JWT\JWT;


class UserController implements ControllerProviderInterface
{

    // const ROOT = '/var/www/optmiz/image/repo1/files';

    public function connect(Application $app)
    {
        // creates a new controller based on the default route
        $controllers = $app['controllers_factory'];
        $controllers->post('/login', 'STHUser\UserController::login');
        $controllers->get('/login', 'STHUser\UserController::login');
        $controllers->get('/login-redirect', 'STHUser\UserController::loginRedirect');

        $controllers->get('/refreshtoken', 'STHUser\UserController::refreshToken')->before(new JwtHook);

        $controllers->get('/profile/{profile}', 'STHUser\UserController::getProfile')->before(new JwtHook);
        $controllers->put('/profile/{profile}', 'STHUser\UserController::setProfile')->before(new JwtHook);

        $controllers->get('/logout', 'STHUser\UserController::logout');

        $controllers->post('/subscribe', 'STHUser\UserController::subscribe');
        $controllers->post('/subscribe-finish', 'STHUser\UserController::subscribeFinish');

        $controllers->options('/login', function () {
            return 'OK';
        });
        $controllers->options('/subscribe', function () {
            return 'OK';
        });
        $controllers->options('/subscribe-finish', function () {
            return 'OK';
        });
        $controllers->options('/profile/{profile}', function () {
            return 'OK';
        });

        return $controllers;
    }

    private function encodeToken(Application $app, $user, $ttl)
    {
        $data = [
            'repository' => $user['repository']['name'],
            'name' => $user['username'],
            'admin' => ($user['role'] == 'admin'),
            'ttl' => $ttl
        ];
        return JWT::encode($data, $app['jwt.secret'], 'HS256');
    }


    public function refreshToken(Application $app, Request $request)
    {
        $user = $app['db']->fetchAssoc('SELECT * FROM user u, repository r where u.username=? and r.id=u.repository_id', [$app['jwt.user']->name]);
        $ttl = time() + (60 * 60 * 24);

        //update BDD
        $app['db']->executeUpdate('UPDATE user SET last_token_ttl=? where username=?', [date("Y-m-d H:i:s", $ttl), $user['username']]);

        return new Response(json_encode(['result' => 'OK', 'token' => $this->encodeToken($app, $user, $ttl)]), 200);
    }

    public function login(Application $app, Request $request)
    {
        $postParams = $app['request_stack']->getCurrentRequest()->request->all();
        //var_dump(password_hash($postParams['password'], PASSWORD_BCRYPT, array( 'cost' => 10 )));
        $user = $app['db']->fetchAssoc('SELECT * FROM user u, repository r where u.username=? and r.id=u.repository_id', [$postParams['username']]);
        //var_dump($user['password'], $postParams['password'], password_verify($postParams['password'], $user['password']));
        if (!$user || !password_verify($postParams['password'], $user['password'])) {
            return new Response(json_encode(['result' => 'error', 'error' => 'AUTH-E001', 'message' => 'Username or password incorrect ']), 401);
        } else {
            $ttl = time() + (60 * 60 * 24);
            $user = $app['db']->fetchAssoc('SELECT * FROM user u where u.username=?', [$postParams['username']]);
            $user['repository'] = $app['db']->fetchAssoc('SELECT * FROM repository r where r.id=?', [$user['repository_id']]);
            $user['repository']['ratio'] = $app['db']->fetchAll('SELECT * FROM ratio r where r.repository_id=?', [$user['repository_id']]);
            unset($user['password']);
            return new Response(json_encode(['result' => 'OK', 'token' => $this->encodeToken($app, $user, $ttl), "profile" => $user]), 200);
        }
    }

    public function getProfile($profile, Application $app, Request $request)
    {
        if ($app['jwt.user']->name != $profile) {
            return new Response(json_encode(['result' => 'error', 'error' => 'PROF-E002', 'message' => 'Edit your own profile']), 403);
        }
        $user = $app['db']->fetchAssoc('SELECT * FROM user u where u.username=?', [$profile]);
        $user['repository'] = $app['db']->fetchAssoc('SELECT * FROM repository r where r.id=?', [$user['repository_id']]);
        $user['repository']['ratio'] = $app['db']->fetchAll('SELECT * FROM ratio r where r.repository_id=?', [$user['repository_id']]);
        return new Response(json_encode(['result' => 'OK', "profile" => $user]), 200);
    }

    public function setProfile($profile, Application $app, Request $request)
    {

        if (($app['jwt.user']->name != $profile) && ($app['jwt.user']->admin !== true)) {
            return new Response(json_encode(['result' => 'error', 'error' => 'PROF-E002', 'message' => 'Update your own profile']), 403);
        }
        $user = $app['request_stack']->getCurrentRequest()->request->all();
        var_dump($user);

        //var_dump(password_hash($user['password'], PASSWORD_BCRYPT, array( 'cost' => 10 )));
        // update BDD
        foreach ($user['repository']['ratios'] as &$ratio) {
            $app['db']->executeUpdate('REPLACE INTO ratio set repository_id = (select repository_id from user where username=?), name=?, width=?, height=?', [$user['username'], $ratio['name'], $ratio['width'], $ratio['height']]);
        }
        $app['db']->executeUpdate('UPDATE user SET company=?, password=?, role=? where username=?', [$user['company'], password_hash($user['password'], PASSWORD_BCRYPT, array('cost' => 10)), $user['role'], $user['username']]);

        return 'OK';
    }

    public function logout(Application $app, Request $request)
    {
        $response = new RedirectResponse('/login', 302, []);
        $response->headers->clearCookie('Authorization');
        return $response;
    }

    public function subscribe(Application $app, Request $request)
    {
        $postParams = $app['request_stack']->getCurrentRequest()->request->all();

        // if repository name && username is OK

        // store a pending account && a repository

        // store defaults ratio for the repository

        // send a mail with a querystring parameter (jwt) to end registration

        return 'OK';
    }

    public function subscribeFinish(Application $app, Request $request)
    {
        $postParams = $app['request_stack']->getCurrentRequest()->request->all();
        return 'OK';
    }
}