<?php
namespace Sugarcrm\Tidbit\Generator;

use Sugarcrm\Sugarcrm\Util\Uuid;

class MeetingsGenerator extends ModuleGenerator
{
    // Change these to generate different scenarios.
    public int $seriesCount = 2000;
    public int $occurrenceCount = 5;

    protected string $currentDateTime;

    public function __construct(\SugarBean $bean)
    {
        parent::__construct($bean);
        $this->currentDateTime = "'" . date('Y-m-d H:i:s') . "'";
    }

    public function clean(): void
    {
        parent::clean();

        // Delete guests from series occurrences.
        $guestTables = ['meetings_contacts', 'meetings_leads', 'meetings_users'];

        foreach ($guestTables as $relTable) {
            $GLOBALS['db']->query("DELETE FROM {$relTable} WHERE meeting_id IN (SELECT id FROM meetings WHERE repeat_parent_id LIKE 'seed-%')", true);
        }
    }

    public function generateRecord($n): array
    {
        $data = parent::generateRecord($n);

        if ($this->seriesCount > 0) {
            // Turn the meeting into a series.
            $data = $this->setSeries($data);
            $data = $this->generateSeries($data);
            $this->seriesCount--;
        }
    }

    protected function setSeries($data = []): array
    {
        $dateStart = \strtotime($this->stripQuotes($data['data']['meetings'][0]['date_start']));
        $rsetDateStart = \date('Ymd\THis', $dateStart);
        $rset = '{"rrule":"DTSTART;TZID=Etc/UTC:' . $rsetDateStart . '\nRRULE:FREQ=DAILY;INTERVAL=1;COUNT=' . $this->occurrenceCount . '","exdate":[],"sugarSupportedRrule":true}';

        $data['data']['meetings'][0]['date_recurrence_modified'] = $this->currentDateTime;
        $data['data']['meetings'][0]['event_type'] = "'master'";
        $data['data']['meetings'][0]['original_start_date'] = "'{$dateStart}'";
        $data['data']['meetings'][0]['recurring_source'] = "'Sugar'";
        $data['data']['meetings'][0]['recurrence_id'] = "'{$dateStart}'";
        $data['data']['meetings'][0]['repeat_type'] = "'Daily'";
        $data['data']['meetings'][0]['repeat_interval'] = 1;
        $data['data']['meetings'][0]['repeat_count'] = 2000;
        $data['data']['meetings'][0]['repeat_selector'] = "'None'";
        $data['data']['meetings'][0]['repeat_parent_id'] = "''";
        $data['data']['meetings'][0]['rset'] = "'{$rset}'";

        return $data;
    }

    protected function generateSeries($data = []): array
    {
        $masterDateStart = new \DateTime($this->stripQuotes($data['data']['meetings'][0]['date_start']));
        $masterDateEnd = new \DateTime($this->stripQuotes($data['data']['meetings'][0]['date_end']));

        for ($i = 1; $i <= $this->occurrenceCount; $i++) {
            $occurrenceId = Uuid::uuid4();
            $dateInterval = new \DateInterval("P{$i}D");
            $dateStart = $masterDateStart->add($dateInterval);
            $dateEnd = $masterDateEnd->add($dateInterval);

            $data['data']['meetings'][$i] = $data['data']['meetings'][0];
            $data['data']['meetings'][$i]['id'] = $occurrenceId;
            $data['data']['meetings'][$i]['date_recurrence_modified'] = "''";
            $data['data']['meetings'][$i]['date_end'] = "'" . $dateEnd->format('Y-m-d H:i:s') . "'";
            $data['data']['meetings'][$i]['date_start'] = "'" . $dateStart->format('Y-m-d H:i:s') . "'";
            $data['data']['meetings'][$i]['event_type'] = "'occurrence'";
            $data['data']['meetings'][$i]['original_start_date'] = "'" . $dateStart->format('Y-m-d H:i:s') . "'";
            $data['data']['meetings'][$i]['repeat_parent_id'] = $data['id'];
            $data['data']['meetings'][$i]['rset'] = "''";

            $guestTables = ['meetings_contacts', 'meetings_leads', 'meetings_users'];

            foreach ($guestTables as $relTable) {
                $rows = [];

                foreach ($data['data'][$relTable] as $row) {
                    $guestRow = $row;
                    $guestRow['id'] = "'" . Uuid::uuid4() . "'";
                    $guestRow['meeting_id'] = $occurrenceId;
                    $rows[] = $guestRow;
                }

                $data['data'][$relTable] = array_merge($data['data'][$relTable], $rows);
            }
        }
    }

    private function stripQuotes(string $value): string
    {
        $strLen = strlen($value);

        if ($strLen > 1 && $value[0] === "'" && $value[$strLen - 1] === "'") {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
