<?php

use SimpleSAML\Locale\Translate;
use SimpleSAML\Module;
use SimpleSAML\XHTML\Template;

/**
 * Hook to add the statistics module to the config page.
 *
 * @param \SimpleSAML\XHTML\Template &$template The template that we should alter in this hook.
 * @return void
 */
function statistics_hook_configpage(Template &$template): void
{
    $template->data['links']['statistics'] = [
        'href' => Module::getModuleURL('statistics/showstats.php'),
        'text' => Translate::noop('Show statistics'),
    ];
    $template->data['links']['statisticsmeta'] = [
        'href' => Module::getModuleURL('statistics/statmeta.php'),
        'text' => Translate::noop('Show statistics metadata'),
    ];
    $template->getLocalization()->addModuleDomain('statistics');
}
