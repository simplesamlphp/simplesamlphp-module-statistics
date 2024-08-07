<?php

declare(strict_types=1);

namespace SimpleSAML\Module\statistics;

use Exception;
use SimpleSAML\Configuration;

/**
 * @package SimpleSAMLphp
 */
class Aggregator
{
    /** @var \SimpleSAML\Configuration */
    private Configuration $statconfig;

    /** @var string */
    private string $statdir;

    /** @var string */
    private string $inputfile;

    /** @var array */
    private array $statrules;

    /** @var int */
    private int $offset;

    /** @var array|null */
    private ?array $metadata = null;

    /** @var bool */
    private bool $fromcmdline;

    /** @var int */
    private int $starttime;

    /** @var array */
    private array $timeres;


    /**
     * Constructor
     *
     * @param bool $fromcmdline
     */
    public function __construct(bool $fromcmdline = false)
    {
        $this->fromcmdline = $fromcmdline;
        $this->statconfig = Configuration::getConfig('module_statistics.php');

        $this->statdir = $this->statconfig->getString('statdir');
        $this->inputfile = $this->statconfig->getString('inputfile');
        $this->statrules = $this->statconfig->getValue('statrules');
        $this->timeres = $this->statconfig->getValue('timeres');
        $this->offset = $this->statconfig->getOptionalInteger('offset', 0);

        $this->starttime = time();
    }


    /**
     */
    public function dumpConfig(): void
    {
        echo 'Statistics directory   : ' . $this->statdir . "\n";
        echo 'Input file             : ' . $this->inputfile . "\n";
        echo 'Offset                 : ' . $this->offset . "\n";
    }


    /**
     */
    public function debugInfo(): void
    {
        // 1024*1024=1048576
        echo 'Memory usage           : ' . number_format(memory_get_usage() / 1048576, 2) . " MB\n";
    }


    /**
     */
    public function loadMetadata(): void
    {
        $filename = $this->statdir . '/.stat.metadata';
        $metadata = null;
        if (file_exists($filename)) {
            $metadata = unserialize(file_get_contents($filename));
        }
        $this->metadata = $metadata;
    }


    /**
     * @return array|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }


    /**
     */
    public function saveMetadata(): void
    {
        $this->metadata['time'] = time() - $this->starttime;
        $this->metadata['memory'] = memory_get_usage();
        $this->metadata['lastrun'] = time();

        $filename = $this->statdir . '/.stat.metadata';
        file_put_contents($filename, serialize($this->metadata), LOCK_EX);
    }


    /**
     * @param bool $debug
     * @return array
     * @throws \Exception
     */
    public function aggregate(bool $debug = false): array
    {
        $this->loadMetadata();

        if (!is_dir($this->statdir)) {
            throw new Exception('Statistics module: output dir does not exist [' . $this->statdir . ']');
        }

        if (!file_exists($this->inputfile)) {
            throw new Exception('Statistics module: input file does not exist [' . $this->inputfile . ']');
        }

        $file = fopen($this->inputfile, 'r');

        if ($file === false) {
            throw new Exception('Statistics module: unable to open file [' . $this->inputfile . ']');
        }

        $logparser = new LogParser(
            $this->statconfig->getOptionalValue('datestart', 0),
            $this->statconfig->getOptionalValue('datelength', 15),
            $this->statconfig->getOptionalValue('offsetspan', 44),
        );
        $datehandler = [
            'default' => new DateHandler($this->offset),
            'month' => new  DateHandlerMonth($this->offset),
        ];

        $notBefore = 0;
        $lastRead = 0;
        $lastlinehash = '-';

        if (isset($this->metadata)) {
            $notBefore = $this->metadata['notBefore'];
            $lastlinehash = $this->metadata['lastlinehash'];
        }

        $lastlogline = 'sdfsdf';
        $lastlineflip = false;
        $results = [];

        $i = 0;
        // Parse through log file, line by line
        while (!feof($file)) {
            $logline = strval(fgets($file, 4096));

            // Continue if STAT is not found on line
            if (!preg_match('/STAT/', $logline)) {
                continue;
            }

            $i++;
            $lastlogline = $logline;

            // Parse log, and extract epoch time and rest of content.
            $epoch = $logparser->parseEpoch($logline);
            $content = $logparser->parseContent($logline);
            $action = trim($content[5]);

            if ($this->fromcmdline && ($i % 10000) == 0) {
                echo "Read line " . $i . "\n";
            }

            if ($debug) {
                echo "----------------------------------------\n";
                echo 'Log line: ' . $logline . "\n";
                echo 'Date parse [' . substr($logline, 0, $this->statconfig->getOptionalValue('datelength', 15)) .
                    '] to [' . date(DATE_RFC822, $epoch) . ']' . "\n";
                $ret = print_r($content, true);
                echo htmlentities($ret);
                if ($i >= 13) {
                    exit;
                }
            }

            if ($epoch > $lastRead) {
                $lastRead = $epoch;
            }

            if ($epoch === $notBefore) {
                if (!$lastlineflip) {
                    if (sha1($logline) === $lastlinehash) {
                        $lastlineflip = true;
                    }
                    continue;
                }
            }

            if ($epoch < $notBefore) {
                continue;
            }

            // Iterate all the statrules from config.
            foreach ($this->statrules as $rulename => $rule) {
                $type = 'aggregate';

                if (array_key_exists('type', $rule)) {
                    $type = $rule['type'];
                }

                if ($type !== 'aggregate') {
                    continue;
                }

                foreach ($this->timeres as $tres => $tresconfig) {
                    $dh = 'default';
                    if (isset($tresconfig['customDateHandler'])) {
                        $dh = $tresconfig['customDateHandler'];
                    }

                    $timeslot = $datehandler['default']->toSlot($epoch, $tresconfig['slot']);
                    $fileslot = $datehandler[$dh]->toSlot($epoch, intval($tresconfig['fileslot']));

                    if (isset($rule['action']) && ($action !== $rule['action'])) {
                        continue;
                    }

                    $difcol = self::getDifCol($content, $rule['col']);

                    if (!isset($results[$rulename][$tres][$fileslot][$timeslot]['_'])) {
                        $results[$rulename][$tres][$fileslot][$timeslot]['_'] = 0;
                    }
                    if (!isset($results[$rulename][$tres][$fileslot][$timeslot][$difcol])) {
                        $results[$rulename][$tres][$fileslot][$timeslot][$difcol] = 0;
                    }

                    $results[$rulename][$tres][$fileslot][$timeslot]['_']++;
                    $results[$rulename][$tres][$fileslot][$timeslot][$difcol]++;
                }
            }
        }
        $this->metadata['notBefore'] = $lastRead;
        $this->metadata['lastline'] = $lastlogline;
        $this->metadata['lastlinehash'] = sha1($lastlogline);
        return $results;
    }


    /**
     * @param array $content
     * @param mixed $colrule
     * @return string
     */
    private static function getDifCol(array $content, $colrule): string
    {
        if (is_int($colrule)) {
            return trim($content[$colrule]);
        } elseif (is_array($colrule)) {
            $difcols = [];
            foreach ($colrule as $cr) {
                $difcols[] = trim($content[$cr]);
            }
            return join('|', $difcols);
        } else {
            return 'NA';
        }
    }


    /**
     * @param mixed $previous
     * @param array $newdata
     * @return array
     */
    private function cummulateData($previous, array $newdata): array
    {
        $dataset = [];
        foreach (func_get_args() as $item) {
            foreach ($item as $slot => $dataarray) {
                if (!array_key_exists($slot, $dataset)) {
                    $dataset[$slot] = [];
                }
                foreach ($dataarray as $key => $data) {
                    if (!array_key_exists($key, $dataset[$slot])) {
                        $dataset[$slot][$key] = 0;
                    }
                    $dataset[$slot][$key] += $data;
                }
            }
        }
        return $dataset;
    }


    /**
     * @param array $results
     */
    public function store(array $results): void
    {
        $datehandler = [
            'default' => new DateHandler($this->offset),
            'month' => new DateHandlerMonth($this->offset),
        ];

        // Iterate the first level of results, which is per rule, as defined in the config.
        foreach ($results as $rulename => $timeresdata) {
            // Iterate over time resolutions
            foreach ($timeresdata as $tres => $resres) {
                $dh = 'default';
                if (isset($this->timeres[$tres]['customDateHandler'])) {
                    $dh = $this->timeres[$tres]['customDateHandler'];
                }

                $filenos = array_keys($resres);
                $lastfile = $filenos[count($filenos) - 1];

                // Iterate the second level of results, which is the fileslot.
                foreach ($resres as $fileno => $fileres) {
                    // Slots that have data.
                    $slotlist = array_keys($fileres);

                    // The last slot.
                    $maxslot = $slotlist[count($slotlist) - 1];

                    // Get start and end slot number within the file, based on the fileslot.
                    $start = $datehandler['default']->toSlot(
                        $datehandler[$dh]->fromSlot($fileno, $this->timeres[$tres]['fileslot']),
                        $this->timeres[$tres]['slot'],
                    );
                    $end = $datehandler['default']->toSlot(
                        $datehandler[$dh]->fromSlot($fileno + 1, $this->timeres[$tres]['fileslot']),
                        $this->timeres[$tres]['slot'],
                    );

                    // Fill in missing entries and sort file results
                    $filledresult = [];
                    for ($slot = $start; $slot < $end; $slot++) {
                        if (array_key_exists($slot, $fileres)) {
                            $filledresult[$slot] = $fileres[$slot];
                        } else {
                            if ($lastfile == $fileno && $slot > $maxslot) {
                                $filledresult[$slot] = ['_' => null];
                            } else {
                                $filledresult[$slot] = ['_' => 0];
                            }
                        }
                    }

                    $filename = $this->statdir . '/' . $rulename . '-' . $tres . '-' . $fileno . '.stat';
                    if (file_exists($filename)) {
                        $previousData = unserialize(file_get_contents($filename));
                        $filledresult = $this->cummulateData($previousData, $filledresult);
                    }

                    // store file
                    file_put_contents($filename, serialize($filledresult), LOCK_EX);
                }
            }
        }
        $this->saveMetadata();
    }
}
