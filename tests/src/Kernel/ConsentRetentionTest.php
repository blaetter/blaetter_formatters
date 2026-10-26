<?php

namespace Drupal\Tests\blaetter_formatters\Kernel;

use Drupal\blaetter_formatters\ConsentRetention;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the deletion of cookie consent records after six months.
 *
 * @group blaetter_formatters
 */
#[CoversClass(ConsentRetention::class)]
#[Group('blaetter_formatters')]
#[RunTestsInSeparateProcesses]
class ConsentRetentionTest extends KernelTestBase
{
    /**
     * The time of the test: 26 October 2026, 12:00 UTC.
     */
    private const NOW = 1793016000;

    /**
     * {@inheritdoc}
     */
    protected static $modules = ['system'];

    protected function setUp(): void
    {
        parent::setUp();
        // The table of EU Cookie Compliance, without installing the module.
        $this->container->get('database')->schema()->createTable(ConsentRetention::TABLE, [
            'fields' => [
                'cid' => ['type' => 'serial', 'not null' => true],
                'uid' => ['type' => 'int', 'not null' => false, 'default' => 0],
                'timestamp' => ['type' => 'int', 'not null' => true, 'default' => 0],
                'ip_address' => ['type' => 'varchar', 'length' => 255, 'not null' => true, 'default' => ''],
                'consent_type' => ['type' => 'varchar', 'length' => 255, 'not null' => true, 'default' => ''],
                'revision_id' => ['type' => 'int', 'not null' => true, 'default' => 0],
            ],
            'primary key' => ['cid'],
        ]);
    }

    /**
     * Returns the service with a fixed time and the given batches.
     */
    private function retention(int $batch_size = 5000, int $max_batches = 10): ConsentRetention
    {
        $time = $this->createMock(TimeInterface::class);
        $time->method('getRequestTime')->willReturn(self::NOW);
        return new ConsentRetention($this->container->get('database'), $time, $batch_size, $max_batches);
    }

    /**
     * Adds records with the given timestamps, oldest first like the real table.
     */
    private function addRecords(array $timestamps): void
    {
        foreach ($timestamps as $timestamp) {
            $this->container->get('database')->insert(ConsentRetention::TABLE)
                ->fields(['timestamp' => $timestamp, 'ip_address' => '192.0.2.1', 'consent_type' => 'banner'])
                ->execute();
        }
    }

    /**
     * Returns the timestamps of the records left.
     */
    private function remaining(): array
    {
        return array_map('intval', $this->container->get('database')->select(ConsentRetention::TABLE, 'c')
            ->fields('c', ['timestamp'])
            ->orderBy('cid')
            ->execute()
            ->fetchCol());
    }

    public function testCutoffIsSixMonthsBefore(): void
    {
        $this->assertSame('2026-04-26 12:00:00', gmdate('Y-m-d H:i:s', ConsentRetention::cutoff(self::NOW)));
    }

    public static function monthEnds(): array
    {
        return [
            'day the month has' => ['2026-12-31 08:00:00', '2026-06-30 08:00:00'],
            'end of August' => ['2027-08-31 12:00:00', '2027-02-28 12:00:00'],
            'leap year' => ['2028-08-30 12:00:00', '2028-02-29 12:00:00'],
            'same day' => ['2027-07-31 12:00:00', '2027-01-31 12:00:00'],
        ];
    }

    #[DataProvider('monthEnds')]
    public function testCutoffDoesNotOverflowAtTheEndOfAMonth(string $now, string $expected): void
    {
        $this->assertSame($expected, gmdate('Y-m-d H:i:s', ConsentRetention::cutoff(strtotime($now . ' UTC'))));
    }

    public function testOlderRecordsAreDeleted(): void
    {
        $cutoff = ConsentRetention::cutoff(self::NOW);
        $this->addRecords([$cutoff - 86400 * 30, $cutoff - 1, $cutoff, $cutoff + 86400, self::NOW]);
        $this->assertSame(2, $this->retention()->purge());
        $this->assertSame([$cutoff, $cutoff + 86400, self::NOW], $this->remaining());
        // A second run has nothing to do.
        $this->assertSame(0, $this->retention()->purge());
    }

    public function testBatchesPerRunAreLimited(): void
    {
        $cutoff = ConsentRetention::cutoff(self::NOW);
        $this->addRecords(array_merge(array_fill(0, 7, $cutoff - 3600), [self::NOW]));
        // At most two batches of three records per run: six deleted, one left for the next run.
        $this->assertSame(6, $this->retention(3, 2)->purge());
        $this->assertSame(1, $this->retention(3, 2)->purge());
        $this->assertSame([self::NOW], $this->remaining());
    }

    public function testRunStopsAtTheFirstNewerRecord(): void
    {
        $cutoff = ConsentRetention::cutoff(self::NOW);
        // An old record behind a newer one (a slow request) waits until the records before it are deleted.
        $this->addRecords([$cutoff - 7200, $cutoff + 60, $cutoff - 3600, self::NOW]);
        $this->assertSame(1, $this->retention()->purge());
        $this->assertSame([$cutoff + 60, $cutoff - 3600, self::NOW], $this->remaining());
    }

    public function testNothingToDoWithoutOldRecords(): void
    {
        $this->addRecords([self::NOW - 3600, self::NOW]);
        $this->assertSame(0, $this->retention()->purge());
        $this->assertCount(2, $this->remaining());
    }

    public function testMissingTableIsIgnored(): void
    {
        $this->container->get('database')->schema()->dropTable(ConsentRetention::TABLE);
        $this->assertSame(0, $this->retention()->purge());
    }
}
