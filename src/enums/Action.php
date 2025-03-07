<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Enums;

enum Action: string
{
    case AccessKey = 'accesskey';
    case Add = 'add';
    case Archive = 'archive';
    case Bloxberg = 'bloxberg';
    case Create = 'create';
    case CreateFromString = 'createfromstring';
    case Deduplicate = 'deduplicate';
    case Duplicate = 'duplicate';
    case Lock = 'lock';
    case Finish = 'finish';
    case Notif = 'notif';
    case PatchUser2Team = 'patchuser2team';
    case Pin = 'pin';
    case Replace = 'replace';
    case Timestamp = 'timestamp';
    case Update = 'update';
    case UpdateTag = 'updatetag';
    case UpdateMetadataField = 'updatemetadatafield';
    case Unreference = 'unreference';
    case Validate = 'validate';
}
