<?php
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use function dirname;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\FilesystemErrorException;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Exceptions\UnauthorizedException;
use Elabftw\Models\Experiments;
use Elabftw\Models\Items;
use Elabftw\Models\ItemsTypes;
use Elabftw\Models\Status;
use Elabftw\Models\Teams;
use Elabftw\Models\Templates;
use Elabftw\Models\Todolist;
use Exception;
use function json_decode;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Update ordering of various things
 */
require_once dirname(__DIR__) . '/init.inc.php';

$Response = new JsonResponse();
$Response->setData(array(
    'res' => true,
    'msg' => _('Saved'),
));

$reqBody = json_decode((string) $App->Request->getContent(), true, 512, JSON_THROW_ON_ERROR);
try {
    switch ($reqBody['table']) {
        case 'items_types':
            if (!$App->Users->isAdmin) {
                throw new IllegalActionException('Non admin user tried to access admin controller.');
            }
            $Entity = new ItemsTypes($App->Users);
            break;
        case 'status':
            if (!$App->Users->isAdmin) {
                throw new IllegalActionException('Non admin user tried to access admin controller.');
            }
            $Entity = new Status(new Teams($App->Users));
            break;
        case 'experiments_steps':
            $model = new Experiments($App->Users);
            $Entity = $model->Steps;
            break;
        case 'items_steps':
            $model = new Items($App->Users);
            $Entity = $model->Steps;
            break;
        case 'todolist':
            $Entity = new Todolist((int) $App->Users->userData['userid']);
            break;
        case 'experiments_templates':
            $Entity = new Templates($App->Users);
            break;
        case 'experiments_templates_steps':
            $model = new Templates($App->Users);
            $Entity = $model->Steps;
            break;
        case 'items_types_steps':
            $model = new ItemsTypes($App->Users);
            $Entity = $model->Steps;
            break;
        default:
            throw new IllegalActionException('Bad table for updateOrdering.');
    }
    $OrderingParams = new OrderingParams($reqBody['table'], $reqBody['ordering']);
    $Entity->updateOrdering($OrderingParams);
} catch (ImproperActionException | UnauthorizedException $e) {
    $Response->setData(array(
        'res' => false,
        'msg' => $e->getMessage(),
    ));
} catch (IllegalActionException $e) {
    $App->Log->notice('', array(array('userid' => $App->Session->get('userid')), array('IllegalAction', $e->getMessage())));
    $Response->setData(array(
        'res' => false,
        'msg' => Tools::error(true),
    ));
} catch (DatabaseErrorException | FilesystemErrorException $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Error', $e)));
    $Response->setData(array(
        'res' => false,
        'msg' => $e->getMessage(),
    ));
} catch (Exception $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('exception' => $e)));
    $Response->setData(array(
        'res' => false,
        'msg' => Tools::error(),
    ));
} finally {
    $Response->send();
}
