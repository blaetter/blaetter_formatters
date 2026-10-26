<?php

namespace Drupal\blaetter_formatters;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Deletes cookie consent records after six months.
 *
 * EU Cookie Compliance stores every consent and refusal with the IP address
 * (table eu_cookie_compliance_basic_consent) and never deletes them. A
 * consent is asked again after the lifetime of its cookie (100 days), so the
 * records are only needed for some time beyond that; six months were decided
 * on 26 October 2026 (server handbook).
 *
 * The records are deleted from the oldest on, in batches, so a large backlog
 * does not lock the table for long. The table has no index on the timestamp,
 * so the records are read in the order of the primary key only: the serial id
 * grows with the time of the record (the request time of the consent), and a
 * run stops at the first record that is new enough. A record older than one
 * before it (a slow request) is deleted a little later, when the records
 * before it are; this is accepted. The run on every cron is cheap: it reads
 * at most one batch by the primary key when there is nothing to delete.
 */
class ConsentRetention
{
    /**
     * The table of EU Cookie Compliance (basic consent storage).
     */
    public const TABLE = 'eu_cookie_compliance_basic_consent';

    /**
     * How long records are kept (a relative date format).
     */
    public const RETENTION = '-6 months';

    public function __construct(
        protected Connection $database,
        protected TimeInterface $time,
        protected int $batchSize = 5000,
        protected int $maxBatches = 10
    ) {
    }

    /**
     * Returns the time before which records are deleted.
     *
     * @param int $now
     *   The current time as Unix timestamp.
     *
     * @return int
     *   The Unix timestamp six months before (UTC). On a day the earlier month
     *   does not have, its last day: 31 August gives 28 February, not 3 March.
     */
    public static function cutoff(int $now): int
    {
        $date = new \DateTimeImmutable('@' . $now);
        $month = $date->modify('first day of ' . self::RETENTION);
        $day = min((int) $date->format('j'), (int) $month->format('t'));
        return $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $day)->getTimestamp();
    }

    /**
     * Deletes the records older than the retention period.
     *
     * @return int
     *   The number of deleted records (at most batch size times batches per
     *   call; the next cron run continues).
     */
    public function purge(): int
    {
        if (!$this->database->schema()->tableExists(self::TABLE)) {
            return 0;
        }
        $cutoff = self::cutoff($this->time->getRequestTime());

        $deleted = 0;
        for ($batch = 0; $batch < $this->maxBatches; $batch++) {
            $records = $this->database->select(self::TABLE, 'c')
                ->fields('c', ['cid', 'timestamp'])
                ->orderBy('cid')
                ->range(0, $this->batchSize)
                ->execute()
                ->fetchAllKeyed();
            // The leading records older than the cutoff.
            $ids = [];
            foreach ($records as $cid => $timestamp) {
                if ((int) $timestamp >= $cutoff) {
                    break;
                }
                $ids[] = $cid;
            }
            if ($ids) {
                $deleted += $this->database->delete(self::TABLE)
                    ->condition('cid', $ids, 'IN')
                    ->execute();
            }
            if (count($ids) < $this->batchSize) {
                break;
            }
        }
        return $deleted;
    }
}
