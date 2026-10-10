<?php

declare(strict_types=1);

namespace SilverShop\Analytics\Task;

use SilverShop\Analytics\Model\ProductView;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Removes raw product-view rows older than ProductView.retention_days. Schedule it daily (cron / queuedjobs)
 * to bound the log. Idempotent and safe to run repeatedly.
 */
class PruneProductViewsTask extends BuildTask
{
    protected string $title = 'Analytics: prune old product views';

    protected static string $description =
        'Deletes raw product-view rows older than the configured retention window (ProductView.retention_days).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $days = (int) ProductView::config()->get('retention_days');
        if ($days <= 0) {
            $output->writeln('Retention disabled (retention_days <= 0); nothing pruned.');

            return Command::SUCCESS;
        }

        $pruned = ProductView::prune($days);
        $output->writeln("Pruned {$pruned} product-view row(s) older than {$days} day(s).");

        return Command::SUCCESS;
    }
}
