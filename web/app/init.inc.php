<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 */

namespace Elabftw\Elabftw;

use function basename;
use function dirname;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Exceptions\InvalidCsrfTokenException;
use Elabftw\Exceptions\UnauthorizedException;
use Elabftw\Models\Config;
use Elabftw\Services\LoginHelper;
use Exception;
use function file_exists;
use function header;
use function in_array;
use Monolog\Logger;
use PDOException;
use function setcookie;
use function stripos;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 *   _       _ _
 *  (_)_ __ (_) |_
 *  | | '_ \| | __|
 *  | | | | | | |_
 *  |_|_| |_|_|\__|
 *
 * This must be included on top of every page.
 * It is the entrypoint of the app.
 */
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
// only used for tests in dev mode
if (file_exists(dirname(__DIR__, 2) . '/c3.php')) {
    require_once dirname(__DIR__, 2) . '/c3.php';
}

$Request = Request::createFromGlobals();
$Session = new Session();
$Session->start();
$Request->setSession($Session);

try {
    // Config::getConfig() will make the first SQL request
    // PDO will throw an exception if the SQL structure is not imported yet
    try {
        $Config = Config::getConfig();
    } catch (DatabaseErrorException | PDOException $e) {
        throw new ImproperActionException('<h1>Error encountered during MySQL initialization</h1><h2>Possible solutions:</h2><ul><li>Make sure to run <code>bin/console db:install</code></li><li>Make sure credentials are correct</li><li>Make sure the database is operational and reachable (firewalls)</li></ul>');
    }
    if (Config::fromEnv('SITE_URL') === '') {
        throw new ImproperActionException('<h1>Could not find mandatory <code>SITE_URL</code> variable! Please <a href="https://doc.elabftw.net/changelog.html#version-4-3-0">have a look at the changelog</a>.</h1>');
    }

    // CSRF
    $Csrf = new Csrf($Request);
    if ($Session->has('csrf')) {
        // if a token is already present in session, add it into the class
        $Csrf->setToken($Session->get('csrf'));
    } else {
        // or generate a new one and add it into the session
        $Session->set('csrf', $Csrf->getToken());
    }
    // at the moment we don't validate csrf for saml login FIXME TODO
    if (basename($Request->getScriptName()) !== 'index.php') {
        $Csrf->validate();
    }
    // END CSRF

    $App = new App($Request, $Session, $Config, new Logger('elabftw'));
    //-*-*-*-*-*-*-**-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-//
    //     ____          _                            //
    //    / ___|___ _ __| |__   ___ _ __ _   _ ___    //
    //   | |   / _ \ '__| '_ \ / _ \ '__| | | / __|   //
    //   | |___  __/ |  | |_) |  __/ |  | |_| \__ \   //
    //    \____\___|_|  |_.__/ \___|_|   \__,_|___/   //
    //                                                //
    //-*-*-*-*-*-*-**-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-//
    // pages where you don't need to be logged in
    // only the script name, not the path because we use basename() on it
    $nologinArr = array(
        // the api can be access with session or token (or only token for v1) so we skip auth here to do it later with custom logic
        'ApiController.php',
        'change-pass.php',
        'index.php',
        'login.php',
        'logout.php',
        'LoginController.php',
        'metadata.php',
        'register.php',
        'RegisterController.php',
        'UnauthRequestHandler.php',
        'ResetPasswordController.php',
    );

    if (!in_array(basename($Request->getScriptName()), $nologinArr, true) && !$Session->has('is_auth')) {
        // try to login our user with session, cookie or other method not requiring a login action
        $Auth = new Auth($App->Config, $Request);
        // this will throw an UnauthorizedException if we don't have a valid auth
        $AuthResponse = $Auth->tryAuth();
        $LoginHelper = new LoginHelper($AuthResponse, $Session);
        $LoginHelper->login(false);
    }

    $App->boot();
} catch (UnauthorizedException | InvalidCsrfTokenException $e) {
    // KICK USER TO LOGOUT PAGE THAT WILL REDIRECT TO LOGIN PAGE
    $cookieOptions = array(
        'expires' => time() + 30,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    );
    // 1 is unauth or session expired, this is here to show a friendly message of the login page after an invalid request
    setcookie('kickreason', '1', $cookieOptions);

    // maybe we clicked an email link and we want to be redirected to the page upon successful login
    // so we store the url in a cookie expiring in 5 minutes to redirect to it after login
    // don't store a redirect cookie if we have been logged out and the redirect is to a controller page (or the logout page)
    if (!stripos($Request->getRequestUri(), 'controllers') && !stripos($Request->getRequestUri(), 'logout')) {
        $cookieOptions = array(
            'expires' => time() + 300,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        );
        setcookie('elab_redirect', $Request->getRequestUri(), $cookieOptions);
    }

    // used by ajax requests to detect a timed out session
    header('X-Elab-Need-Auth: 1');
    // don't send a GET app/logout.php if it's an ajax call because it messes up the jquery ajax
    if ($Request->headers->get('X-Requested-With') !== 'XMLHttpRequest') {
        $url = Config::fromEnv('SITE_URL') . '/app/logout.php?keep_redirect=1';
        header('Location: ' . $url);
    }
    exit;
} catch (Exception $e) {
    // if something went wrong here it should stop whatever is after
    die($e->getMessage());
}
