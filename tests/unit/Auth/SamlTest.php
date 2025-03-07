<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Auth;

use Elabftw\Auth\Saml as SamlAuth;
use Elabftw\Elabftw\AuthResponse;
use Elabftw\Elabftw\Saml;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Exceptions\UnauthorizedException;
use Elabftw\Models\Config;
use Elabftw\Models\Idps;
use OneLogin\Saml2\Auth as SamlAuthLib;

class SamlTest extends \PHPUnit\Framework\TestCase
{
    private array $configArr;

    private SamlAuthLib $SamlAuthLib;

    private array $samlUserdata;

    private array $settings;

    protected function setUp(): void
    {
        $this->configArr = array(
            'debug' => '0',
            'saml_sync_teams' => '0',
            'saml_team_default' => '2',
            'saml_user_default' => '0',
            'saml_fallback_orgid' => '0',
            'saml_sync_email_idp' => '0',
        );

        // don't use the real saml lib but create a mock
        $this->SamlAuthLib = $this->createMock(SamlAuthLib::class);
        $this->SamlAuthLib->method('login')->willReturn(null);
        $this->SamlAuthLib->method('processResponse')->willReturn(null);
        $this->SamlAuthLib->method('getErrors')->willReturn(null);
        $this->SamlAuthLib->method('getSessionIndex')->willReturn('abcdef');
        $this->SamlAuthLib->method('isAuthenticated')->willReturn(true);

        // create fake saml idp response
        $this->samlUserdata = array();
        $this->samlUserdata['User.email'] = 'phpunit@example.com';
        $this->samlUserdata['User.firstname'] = 'Phpunit';
        $this->samlUserdata['User.lastname'] = 'FTW';
        $this->samlUserdata['User.team'] = 'Alpha';
        $this->SamlAuthLib->method('getAttributes')->willReturn($this->samlUserdata);

        $Saml = new Saml(Config::getConfig(), new Idps());
        $idpId = 1;
        $this->settings = $Saml->getSettings($idpId);
    }

    public function testTryAuth(): void
    {
        $AuthService = new SamlAuth($this->SamlAuthLib, $this->configArr, $this->settings);
        $authResponse = $AuthService->tryAuth();
        $this->assertInstanceOf(AuthResponse::class, $authResponse);
    }

    public function testAssertIdpResponse(): void
    {
        // happy path
        $AuthService = new SamlAuth($this->SamlAuthLib, $this->configArr, $this->settings);
        $authResponse = $AuthService->assertIdpResponse();
        $this->assertInstanceOf(AuthResponse::class, $authResponse);
        $this->assertEquals(1, $authResponse->userid);
        $this->assertFalse($authResponse->isAnonymous);
        $this->assertEquals(1, $authResponse->selectedTeam);
    }

    public function testAssertIdpResponseSyncTeams(): void
    {
        $configArr = $this->configArr;
        $configArr['saml_sync_teams'] = '1';
        $AuthService = new SamlAuth($this->SamlAuthLib, $configArr, $this->settings);
        $authResponse = $AuthService->assertIdpResponse();
        $this->assertEquals(1, $authResponse->selectedTeam);
    }

    public function testAssertIdpResponseFailedAuth(): void
    {
        // now try with a failed auth
        // don't use the real saml lib but create a mock
        $this->SamlAuthLib = $this->createMock(SamlAuthLib::class);
        $this->SamlAuthLib->method('login')->willReturn(null);
        $this->SamlAuthLib->method('processResponse')->willReturn(null);
        $this->SamlAuthLib->method('getErrors')->willReturn(null);
        $this->SamlAuthLib->method('isAuthenticated')->willReturn(false);
        $AuthService = new SamlAuth($this->SamlAuthLib, $this->configArr, $this->settings);
        $this->expectException(UnauthorizedException::class);
        $AuthService->assertIdpResponse();
    }

    /**
     * Idp doesn't send back a team
     */
    public function testAssertIdpResponseNoTeamResponse(): void
    {
        $samlUserdata = $this->samlUserdata;
        unset($samlUserdata['User.team']);

        $authResponse = $this->getAuthResponse($samlUserdata);
        $this->assertEquals(1, $authResponse->selectedTeam);
    }

    /**
     * Idp doesn't send back a team and there are no default team
     */
    public function testAssertIdpResponseNoTeamResponseNoDefaultTeam(): void
    {
        $samlUserdata = $this->samlUserdata;
        unset($samlUserdata['User.team']);
        // same but with no configured default team
        $config = $this->configArr;
        $config['saml_team_default'] = '0';
        $authResponse = $this->getAuthResponse($samlUserdata, $config);
        // as user exists already, they'll be in team 1
        $this->assertEquals(1, $authResponse->selectedTeam);
    }

    /**
     * Idp sends an array of teams
     */
    public function testAssertIdpResponseTeamsArrayResponse(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['User.team'] = array('Alpha');

        $authResponse = $this->getAuthResponse($samlUserdata);
        $this->assertEquals(1, $authResponse->selectedTeam);
    }

    /**
     * Idp sends an array of email
     */
    public function testAssertIdpResponseEmailArrayResponse(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['User.email'] = array('phpunit@example.com');

        $authResponse = $this->getAuthResponse($samlUserdata);
        $this->assertEquals(1, $authResponse->selectedTeam);
    }

    /**
     * Idp doesn't send back an email
     */
    public function testAssertIdpResponseNoEmail(): void
    {
        $samlUserdata = $this->samlUserdata;
        unset($samlUserdata['User.email']);

        $this->expectException(ImproperActionException::class);
        $this->getAuthResponse($samlUserdata);
    }

    /**
     * User is not found with email but with orgid
     */
    public function testMatchUserWithOrgid(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['internal_id'] = 'internal_id_1';
        $samlUserdata['User.email'] = 'userchangedemail@example.com';
        $this->settings['idp']['orgidAttr'] = 'internal_id';
        $config = $this->configArr;
        $config['saml_fallback_orgid'] = '1';

        $authResponse = $this->getAuthResponse($samlUserdata, $config);
        $this->assertEquals(1, $authResponse->userid);
    }

    /**
     * User is not found with email but with orgid and we update the email
     */
    public function testMatchUserWithOrgidAndChangeEmail(): void
    {
        $samlUserdata = $this->samlUserdata;
        // this will match the user with original email "somesamluser@example.com"
        $samlUserdata['internal_id'] = 'internal_id_42';
        // we assign a new email to that user from the idp response
        $samlUserdata['User.email'] = 'somesamluser42@example.com';
        // make sure the orgid is picked up
        $this->settings['idp']['orgidAttr'] = 'internal_id';
        $config = $this->configArr;
        $config['saml_fallback_orgid'] = '1';
        // the email will be modified here, and updated with the value coming from idp
        $config['saml_sync_email_idp'] = '1';

        $authResponse = $this->getAuthResponse($samlUserdata, $config);
        $this->assertEquals(5, $authResponse->userid);
    }

    /**
     * User is not found with email nor with orgid and we can't create new user
     */
    public function testMatchUserWithOrgidFail(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['internal_id'] = 'internal_id_23';
        $samlUserdata['User.email'] = 'userchangedemailagain@example.com';

        // exception will be thrown because we have saml_user_default to 0
        $this->expectException(ImproperActionException::class);
        $this->getAuthResponse($samlUserdata);
    }

    /**
     * User is not found with email nor with orgid so the user is created
     */
    public function testMatchUserWithOrgidAndCreateUser(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['User.email'] = 'a_new_never_seen_before_user@example.com';
        $settings = $this->settings;
        unset($settings['idp']['teamAttr']);

        // create the user on the fly
        $config = $this->configArr;
        $config['saml_user_default'] = '1';

        $authResponse = $this->getAuthResponse($samlUserdata, $config, $settings);
        $this->assertIsInt($authResponse->userid);
    }

    /**
     * User is not found with email nor with orgid so the user is created but we cannot find a team!
     */
    public function testCreateUserButTeamCannotBeFound(): void
    {
        // copy so we don't pollute global
        $samlUserdata = $this->samlUserdata;
        $settings = $this->settings;
        $config = $this->configArr;

        // use a fresh email address
        $samlUserdata['User.email'] = 'a_new_never_seen_before_user_for_real@example.com';

        // remove the team attribute setting
        unset($settings['idp']['teamAttr']);

        // create the user on the fly
        $config['saml_user_default'] = '1';
        // throw error if team cannot be found
        $config['saml_team_default'] = '0';

        $this->expectException(ImproperActionException::class);
        $this->getAuthResponse($samlUserdata, $config, $settings);
    }

    /**
     * User is not found with email nor with orgid so the user is created and we let them select a team
     */
    public function testCreateUserButTeamMustBeSelected(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['User.email'] = 'a_new_never_seen_before_user_for_real@example.com';
        $samlUserdata['internal_id'] = 'something else';
        // remove the team attribute setting
        $settings = $this->settings;
        unset($settings['idp']['teamAttr']);

        // create the user on the fly
        $config = $this->configArr;
        $config['saml_user_default'] = '1';
        // let user select a team
        $config['saml_team_default'] = '-1';
        // we try to match with orgid too but it won't work
        $config['saml_fallback_orgid'] = '1';

        $authResponse = $this->getAuthResponse($samlUserdata, $config, $settings);
        $this->assertEmpty($authResponse->selectableTeams);
    }

    /**
     * User is not found with email nor with orgid so the user is created
     */
    public function testCreateUserWithTeamsFromIdp(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['User.email'] = 'a_new_never_seen_before_user_for_real@example.com';
        $samlUserdata['User.team'] = 'Bravo';

        // create the user on the fly
        $config = $this->configArr;
        $config['saml_user_default'] = '1';

        $authResponse = $this->getAuthResponse($samlUserdata, $config);
        $this->assertEquals(2, $authResponse->selectedTeam);
    }

    public function testCreateUserWithTeamsFromIdpButConfigIsEmpty(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['User.email'] = 'a_new_never_seen_before_user_for_real_yes@example.com';
        $samlUserdata['User.team'] = 'Bravo';
        $settings = $this->settings;
        // set an empty idp team attribute
        $settings['idp']['teamAttr'] = '';

        // create the user on the fly
        $config = $this->configArr;
        $config['saml_user_default'] = '1';
        // try to synchronize the teams from idp but the team attribute is empty
        $config['saml_sync_teams'] = '1';

        $this->expectException(ImproperActionException::class);
        $this->getAuthResponse($samlUserdata, $config, $settings);
    }

    public function testCreateUserWithTeamsFromIdpAndTeamsIsArray(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['User.email'] = 'a_new_never_seen_before_user_for_real_yes@example.com';
        $samlUserdata['User.team'] = array('Bravo', 'Alpha');
        $settings = $this->settings;
        // set an empty idp team attribute
        $settings['idp']['teamAttr'] = 'User.team';

        // create the user on the fly
        $config = $this->configArr;
        $config['saml_user_default'] = '1';
        // try to synchronize the teams from idp but the team attribute is empty
        $config['saml_sync_teams'] = '1';

        $response = $this->getAuthResponse($samlUserdata, $config, $settings);
        $this->assertEquals(2, count($response->selectableTeams));
    }

    public function testCreateUserWithTeamsFromIdpButIdpValueIsEmpty(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['User.email'] = 'a_new_never_seen_before_user_for_real_yes@example.com';
        $samlUserdata['User.team'] = '';
        $settings = $this->settings;

        // create the user on the fly
        $config = $this->configArr;
        $config['saml_user_default'] = '1';
        // try to synchronize the teams from idp but the team attribute is empty
        $config['saml_sync_teams'] = '1';

        $this->expectException(ImproperActionException::class);
        $this->getAuthResponse($samlUserdata, $config, $settings);
    }

    /**
     * User is not found with email nor with orgid so the user is created in several teams
     */
    public function testCreateUserWithTeamsFromIdpInSeveralTeams(): void
    {
        $samlUserdata = $this->samlUserdata;
        $samlUserdata['User.email'] = 'a_new_never_seen_before_user_for_real_again@example.com';
        $samlUserdata['User.team'] = array('Bravo', 'Fresh new team');

        // create the user on the fly
        $config = $this->configArr;
        $config['saml_user_default'] = '1';

        $authResponse = $this->getAuthResponse($samlUserdata, $config);
        $this->assertEquals(2, count($authResponse->selectableTeams));
    }

    /**
     * Try with errors in the response
     */
    public function testAssertIdpResponseError(): void
    {
        $this->SamlAuthLib = $this->createMock(SamlAuthLib::class);
        $this->SamlAuthLib->method('getErrors')->willReturn(array('Error' => 'Something went wrong!'));
        $AuthService = new SamlAuth($this->SamlAuthLib, $this->configArr, $this->settings);
        $this->expectException(UnauthorizedException::class);
        $AuthService->assertIdpResponse();
    }

    /**
     * With debug mode on and errors
     */
    public function testAssertIdpResponseErrorDebug(): void
    {
        $this->SamlAuthLib = $this->createMock(SamlAuthLib::class);
        $this->SamlAuthLib->method('getErrors')->willReturn(array('Error' => 'Something went wrong!'));
        $configArr = $this->configArr;
        $configArr['debug'] = '1';
        $AuthService = new SamlAuth($this->SamlAuthLib, $configArr, $this->settings);
        $this->expectException(UnauthorizedException::class);
        $AuthService->assertIdpResponse();
    }

    public function testGetSessionIndex(): void
    {
        $AuthService = new SamlAuth($this->SamlAuthLib, $this->configArr, $this->settings);
        $AuthService->assertIdpResponse();
        $this->assertEquals('abcdef', $AuthService->getSessionIndex());
    }

    public function testEncodeDecodeToken(): void
    {
        $AuthService = new SamlAuth($this->SamlAuthLib, $this->configArr, $this->settings);
        $AuthService->assertIdpResponse();

        $token = $AuthService->encodeToken(1);
        $this->assertIsString($token);

        [$sid, $idpId] = SamlAuth::decodeToken($token);
        $this->assertEquals('abcdef', $sid);
        $this->assertEquals(1, $idpId);
    }

    public function testEmptyToken(): void
    {
        $this->expectException(UnauthorizedException::class);
        SamlAuth::decodeToken('');
    }

    public function testUndecodableToken(): void
    {
        $this->expectException(UnauthorizedException::class);
        SamlAuth::decodeToken('..');
    }

    public function testNotParsableToken(): void
    {
        $this->expectException(UnauthorizedException::class);
        SamlAuth::decodeToken('this can not be parsed');
    }

    /**
     * Helper function to avoid code repetition
     */
    private function getAuthResponse(?array $samlUserdata = null, ?array $config = null, ?array $settings = null): AuthResponse
    {
        $samlUserdata = $samlUserdata ?? $this->samlUserdata;
        $config = $config ?? $this->configArr;
        $settings = $settings ?? $this->settings;

        $SamlAuthLib = $this->createMock(SamlAuthLib::class);
        $SamlAuthLib->method('login')->willReturn(null);
        $SamlAuthLib->method('processResponse')->willReturn(null);
        $SamlAuthLib->method('getErrors')->willReturn(null);
        $SamlAuthLib->method('getAttributes')->willReturn($samlUserdata);
        $SamlAuthLib->method('isAuthenticated')->willReturn(true);
        $AuthService = new SamlAuth($SamlAuthLib, $config, $settings);
        return $AuthService->assertIdpResponse();
    }
}
