<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Auth;

use Elabftw\Elabftw\AuthResponse;
use Elabftw\Exceptions\InvalidMfaCodeException;
use Elabftw\Interfaces\AuthInterface;
use Elabftw\Models\Users;
use Elabftw\Services\MfaHelper;

/**
 * Multi Factor Auth service
 */
class Mfa implements AuthInterface
{
    private AuthResponse $AuthResponse;

    public function __construct(private MfaHelper $MfaHelper, private string $code)
    {
        $this->AuthResponse = new AuthResponse();
    }

    public function tryAuth(): AuthResponse
    {
        $Users = new Users($this->MfaHelper->userid);

        if (!$this->MfaHelper->verifyCode($this->code)) {
            throw new InvalidMfaCodeException($this->MfaHelper->userid);
        }

        $this->AuthResponse->hasVerifiedMfa = true;
        $this->AuthResponse->mfaSecret = $Users->userData['mfa_secret'];
        $this->AuthResponse->userid = $this->MfaHelper->userid;
        $this->AuthResponse->setTeams();

        return $this->AuthResponse;
    }
}
