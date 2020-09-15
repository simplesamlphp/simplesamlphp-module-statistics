<?php

use SimpleSAML\Module;
use Webmozart\Assert\Assert;

/**
 * Hook to add the modinfo module to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 */
function statistics_hook_frontpage(array &$links): void
{
    Assert::keyExists($links, 'links');

    $links['config']['statistics'] = [
        'href' => Module::getModuleURL('statistics/showstats.php'),
        'text' => '{statistics:statistics:link_statistics}',
    ];
    $links['config']['statisticsmeta'] = [
        'href' => Module::getModuleURL('statistics/statmeta.php'),
        'text' => '{statistics:statistics:link_statistics_metadata}',
        'shorttext' => ['en' => 'Statistics metadata', 'no' => 'Statistikk metadata'],
    ];
}
