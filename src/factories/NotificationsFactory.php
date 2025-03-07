<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Factories;

use Elabftw\Enums\Notifications;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Interfaces\MailableInterface;
use Elabftw\Models\Notifications\CommentCreated;
use Elabftw\Models\Notifications\EventDeleted;
use Elabftw\Models\Notifications\SelfIsValidated;
use Elabftw\Models\Notifications\SelfNeedValidation;
use Elabftw\Models\Notifications\StepDeadline;
use Elabftw\Models\Notifications\UserCreated;
use Elabftw\Models\Notifications\UserNeedValidation;

/**
 * Get a Notification instance based on data from sql row
 */
class NotificationsFactory
{
    private array $body;

    public function __construct(private int $category, string $jsonBody)
    {
        $this->body = json_decode($jsonBody, true, 10, JSON_THROW_ON_ERROR);
    }

    public function getMailable(): MailableInterface
    {
        return match (Notifications::from($this->category)) {
            Notifications::CommentCreated => new CommentCreated($this->body['experiment_id'], $this->body['commenter_userid']),
            Notifications::UserCreated => new UserCreated($this->body['userid'], $this->body['team']),
            Notifications::UserNeedValidation => new UserNeedValidation($this->body['userid'], $this->body['team']),
            Notifications::StepDeadline => new StepDeadline($this->body['step_id'], $this->body['entity_id'], $this->body['entity_page'], $this->body['deadline']),
            Notifications::EventDeleted => new EventDeleted($this->body['event'], $this->body['actor']),
            Notifications::SelfNeedValidation => new SelfNeedValidation(),
            Notifications::SelfIsValidated => new SelfIsValidated(),
            default => throw new ImproperActionException('This notification is not mailable.'),
        };
    }
}
