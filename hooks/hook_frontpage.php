<?php

declare(strict_types=1);

use SimpleSAML\Assert\Assert;
use SimpleSAML\Module;

/**
 * Hook to add the modinfo module to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 */
function statistics_hook_frontpage(array &$links): void
{
    Assert::keyExists($links, 'links');

    $links['config']['statistics'] = [
        'href' => Module::getModuleURL('statistics/'),
        'text' => '{statistics:statistics:link_statistics}',
    ];
    $links['config']['statisticsmeta'] = [
        'href' => Module::getModuleURL('statistics/metadata'),
        'text' => '{statistics:statistics:link_statistics_metadata}',
        'shorttext' => ['en' => 'Statistics metadata', 'no' => 'Statistikk metadata'],
    ];
}
