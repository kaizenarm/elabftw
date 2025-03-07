<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use function array_filter;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\FilesystemErrorException;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\ItemsTypes;
use Elabftw\Models\Status;
use Elabftw\Models\TeamGroups;
use Elabftw\Models\Teams;
use Elabftw\Models\TeamTags;
use Elabftw\Services\DummyRemoteDirectory;
use Elabftw\Services\EairefRemoteDirectory;
use Elabftw\Services\UsersHelper;
use Exception;
use function filter_var;

use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration panel of a team
 */
require_once 'app/init.inc.php';
$App->pageTitle = _('Admin panel'); // @phan-suppress PhanTypeExepectedObjectPropAccessButGotNull
$Response = new Response();
$Response->prepare($Request);

$template = 'error.html';
$renderArr = array();

try {
    if (!$App->Users->isAdmin) {
        throw new IllegalActionException('Non admin user tried to access admin controller.');
    }

    $ItemsTypes = new ItemsTypes($App->Users);
    $Teams = new Teams($App->Users, $App->Users->userData['team']);
    $Status = new Status($Teams);
    $Tags = new TeamTags($App->Users);
    $TeamGroups = new TeamGroups($App->Users);
    $PermissionsHelper = new PermissionsHelper();

    $itemsCategoryArr = $ItemsTypes->readAll();
    if ($Request->query->has('templateid')) {
        $ItemsTypes->setId($App->Request->query->getInt('templateid'));
    }
    $statusArr = $Status->readAll();
    $teamGroupsArr = $TeamGroups->readAll();
    $teamsArr = $Teams->readAll();
    $allTeamUsersArr = $App->Users->readAllFromTeam();
    // only the unvalidated ones
    $unvalidatedUsersArr = array_filter($allTeamUsersArr, function ($u) {
        return $u['validated'] === 0;
    });
    // Users search
    $isSearching = false;
    $usersArr = array();
    if ($Request->query->has('q')) {
        $isSearching = true;
        $usersArr = $App->Users->readFromQuery(
            filter_var($Request->query->get('q'), FILTER_SANITIZE_STRING),
            $App->Users->userData['team'],
            $App->Request->query->getBoolean('includeArchived'),
        );
        foreach ($usersArr as &$user) {
            $UsersHelper = new UsersHelper((int) $user['userid']);
            $user['teams'] = $UsersHelper->getTeamsFromUserid();
        }
    }

    // Remote directory search
    $remoteDirectoryUsersArr = array();
    if ($App->Request->query->has('remote_dir_query')) {
        if ($App->Config->configArr['remote_dir_service'] === 'eairef') {
            $RemoteDirectory = new EairefRemoteDirectory(new Client(), $App->Config->configArr['remote_dir_config']);
        } else {
            $RemoteDirectory = new DummyRemoteDirectory(new Client(), $App->Config->configArr['remote_dir_config']);
        }
        $remoteDirectoryUsersArr = $RemoteDirectory->search((string) $App->Request->query->get('remote_dir_query'));
        if (empty($remoteDirectoryUsersArr)) {
            $App->warning[] = _('No users found. Try another search.');
        }
    }

    // all the tags for the team
    $tagsArr = $Tags->readFull();

    $metadataGroups = array();
    if (isset($ItemsTypes->entityData['metadata'])) {
        $metadataGroups = (new Metadata($ItemsTypes->entityData['metadata']))->getGroups();
    }

    $template = 'admin.html';
    $renderArr = array(
        'Entity' => $ItemsTypes,
        'allTeamUsersArr' => $allTeamUsersArr,
        'tagsArr' => $tagsArr,
        'isSearching' => $isSearching,
        'itemsCategoryArr' => $itemsCategoryArr,
        'metadataGroups' => $metadataGroups,
        'myTeamgroupsArr' => $TeamGroups->readAllSimple(),
        'statusArr' => $statusArr,
        'teamGroupsArr' => $teamGroupsArr,
        'visibilityArr' => $PermissionsHelper->getAssociativeArray(),
        'remoteDirectoryUsersArr' => $remoteDirectoryUsersArr,
        'teamsArr' => $teamsArr,
        'unvalidatedUsersArr' => $unvalidatedUsersArr,
        'usersArr' => $usersArr,
    );
} catch (IllegalActionException $e) {
    $App->Log->notice('', array(array('userid' => $App->Session->get('userid')), array('IllegalAction', $e)));
    $renderArr['error'] = Tools::error(true);
} catch (DatabaseErrorException | FilesystemErrorException | ImproperActionException $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Error', $e)));
    $renderArr['error'] = $e->getMessage();
} catch (Exception $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Exception' => $e)));
    $renderArr['error'] = Tools::error();
} finally {
    $Response->setContent($App->render($template, $renderArr));
    $Response->send();
}
