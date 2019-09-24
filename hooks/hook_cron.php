<?php

use Exception;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module\statistics\Aggregator;
use Webmozart\Assert\Assert;

/**
 * Hook to run a cron job.
 *
 * @param array &$croninfo  Output
 * @return void
 */
function statistics_hook_cron(array &$croninfo): void
{
    Assert::keyExists($croninfo, 'summary');
    Assert::keyExists($croninfo, 'tag');

    $statconfig = Configuration::getConfig('module_statistics.php');

    if (is_null($statconfig->getValue('cron_tag', null))) {
        return;
    }
    if ($statconfig->getValue('cron_tag', null) !== $croninfo['tag']) {
        return;
    }

    $maxtime = $statconfig->getInteger('time_limit', null);
    if ($maxtime) {
        set_time_limit($maxtime);
    }

    try {
        $aggregator = new Aggregator();
        $results = $aggregator->aggregate();
        if (empty($results)) {
            Logger::notice('Output from statistics aggregator was empty.');
        } else {
            $aggregator->store($results);
        }
    } catch (Exception $e) {
        $message = 'Loganalyzer threw exception: ' . $e->getMessage();
        Logger::warning($message);
        $croninfo['summary'][] = $message;
    }
}
