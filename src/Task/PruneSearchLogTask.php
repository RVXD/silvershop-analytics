<?php

declare(strict_types=1);

namespace SilverShop\Analytics\Task;

use SilverShop\Analytics\Model\SearchLog;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Removes search-log rows older than SearchLog.retention_days. Schedule it daily (cron / queuedjobs) to bound the
 * log. Idempotent and safe to run repeatedly.
 */
class PruneSearchLogTask extends BuildTask
{
    protected string $title = 'Analytics: prune old search log';

    protected static string $description =
        'Deletes search-log rows older than the configured retention window (SearchLog.retention_days).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $days = (int) SearchLog::config()->get('retention_days');
        if ($days <= 0) {
            $output->writeln('Retention disabled (retention_days <= 0); nothing pruned.');

            return Command::SUCCESS;
        }

        $pruned = SearchLog::prune($days);
        $output->writeln("Pruned {$pruned} search-log row(s) older than {$days} day(s).");

        return Command::SUCCESS;
    }
}
