<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Make;

use function date;
use Elabftw\Elabftw\Db;
use Elabftw\Models\Scheduler;
use Elabftw\Services\UsersHelper;
use Elabftw\Traits\UploadTrait;
use function implode;

/**
 * Create a report of scheduler bookings
 */
class MakeSchedulerReport extends AbstractMakeCsv
{
    use UploadTrait;

    protected Db $Db;

    protected array $rows;

    public function __construct(Scheduler $scheduler)
    {
        $this->Db = Db::getConnection();
        $this->rows = $scheduler->readAll();
    }

    /**
     * The human friendly name
     */
    public function getFileName(): string
    {
        return date('Y-m-d') . '-report.elabftw.csv';
    }

    /**
     * Columns of the CSV
     */
    protected function getHeader(): array
    {
        $header = array_keys($this->rows[0]);
        $header[] = 'team(s)';
        return $header;
    }

    /**
     * Get the rows for each users
     */
    protected function getRows(): array
    {
        foreach ($this->rows as $key => $entry) {
            // append the team(s) of user
            $UsersHelper = new UsersHelper($entry['userid']);
            $teams = implode(',', $UsersHelper->getTeamsNameFromUserid());
            $this->rows[$key]['team(s)'] = $teams;
        }
        return $this->rows;
    }
}
