<?php declare(strict_types=1);
/**
 * @package   Elabftw\Elabftw
 * @author    Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @license   https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @see       https://www.elabftw.net Official website
 */

namespace Elabftw\Elabftw;

use function basename;
use Elabftw\Auth\Anon;
use Elabftw\Auth\Cookie;
use Elabftw\Exceptions\UnauthorizedException;
use Elabftw\Interfaces\AuthInterface;
use Elabftw\Models\Config;
use Elabftw\Services\TeamFinder;
use function in_array;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provide methods to authenticate a user
 */
class Auth implements AuthInterface
{
    public function __construct(private Config $Config, private Request $Request)
    {
    }

    /**
     * Try to authenticate with session and cookie
     *     ____          _
     *    / ___|___ _ __| |__   ___ _ __ _   _ ___
     *   | |   / _ \ '__| '_ \ / _ \ '__| | | / __|
     *   | |___  __/ |  | |_) |  __/ |  | |_| \__ \
     *    \____\___|_|  |_.__/ \___|_|   \__,_|___/
     *
     */
    public function tryAuth(): AuthResponse
    {
        $AuthService = $this->getAuthService($this->getAuthType());
        return $AuthService->tryAuth();
    }

    private function getAuthType(): string
    {
        // try to login with the elabid for an entity in view mode
        $page = basename($this->Request->getScriptName());
        if ($this->Request->query->has('access_key')
            && ($page === 'experiments.php' || $page === 'database.php')
            && $this->Request->query->get('mode') === 'view') {
            return 'access_key';
        }

        // try to login with the cookie if we have one in the request
        if ($this->Request->cookies->has('token')) {
            return 'cookie';
        }

        // autologin as anon if it's allowed by sysadmin
        if ($this->Config->configArr['open_science']) {
            return 'open';
        }
        throw new UnauthorizedException();
    }

    private function getAuthService(string $authType): AuthInterface
    {
        switch ($authType) {
            // AUTH WITH COOKIE
            case 'cookie':
                return new Cookie(
                    (string) $this->Request->cookies->get('token'),
                    $this->Request->cookies->getDigits('token_team'),
                    $this->Config->configArr,
                );
            case 'access_key':
                // now we need to know in which team we autologin the user
                $TeamFinder = new TeamFinder($this->Request->getScriptName(), (string) ($this->Request->query->get('access_key') ?? ''));
                $team = $TeamFinder->findTeam();

                if ($team === 0) {
                    throw new UnauthorizedException();
                }
                return new Anon($this->Config->configArr, $team);
            case 'open':
                // don't do it if we have elabid in url
                // only autologin on selected pages and if we are not authenticated with an account
                $autoAnon = array('experiments.php', 'database.php', 'search.php');
                if (in_array(basename($this->Request->getScriptName()), $autoAnon, true)) {
                    return new Anon($this->Config->configArr, (int) ($this->Config->configArr['open_team'] ?? 1));
                }
                // no break
            default:
                throw new UnauthorizedException();
        }
    }
}
