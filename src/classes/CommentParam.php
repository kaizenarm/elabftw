<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Interfaces\ParamInterface;

final class CommentParam implements ParamInterface
{
    public function __construct(private string $content)
    {
    }

    public function getContent(): string
    {
        $comment = filter_var($this->content, FILTER_SANITIZE_STRING);
        if ($comment === false || empty($comment)) {
            throw new ImproperActionException('Invalid comment.');
        }
        return nl2br($comment);
    }
}
