<?php

namespace AbuseIO\Parsers;

use Ddeboer\DataImport\Reader;
use Ddeboer\DataImport\Writer;
use Ddeboer\DataImport\Filter;
use Log;
use ReflectionClass;

class Cegtek extends Parser
{
    public $parsedMail;
    public $arfMail;

    /**
     * Create a new Cegtek instance
     */
    public function __construct($parsedMail, $arfMail)
    {
        $this->parsedMail = $parsedMail;
        $this->arfMail = $arfMail;
    }

    /**
     * Parse attachments
     * @return Array    Returns array with failed or success data
     *                  (See parser-common/src/Parser.php) for more info.
     */
    public function parse()
    {
        // Generalize the local config based on the parser class name.
        $reflect = new ReflectionClass($this);
        $configBase = 'parsers.' . $reflect->getShortName();

        Log::info(
            get_class($this) . ': Received message from: ' .
            $this->parsedMail->getHeader('from') . " with subject: '" .
            $this->parsedMail->getHeader('subject') . "' arrived at parser: " .
            config("{$configBase}.parser.name")
        );

        $events = [ ];
        $report = [ ];

        $this->feedName = 'default';

        // XML is placed in the body
        if (preg_match(
            '/(?<=- ----Start ACNS XML\n)(.*)(?=\n- ----End ACNS XML)/s',
            $this->parsedMail->getMessageBody(),
            $regs
        )) {
            $report = $regs[0];
        }

        if (!$this->isKnownFeed()) {
            return $this->failed(
                "Detected feed {$this->feedName} is unknown."
            );
        }

        if (!$this->isEnabledFeed()) {
            return $this->success($events);
        }

        if (!$this->hasRequiredFields($report)) {
            return $this->failed(
                "Required field {$this->requiredField} is missing or the config is incorrect."
            );
        }

        if (!empty($report) && $report = simplexml_load_string($report)) {
            // Work around the crappy timestamp used by IP-echelon, i.e.: 2015-05-06T05-00-00UTC
            // We loose some timezone information, but hey it's close enough ;)
            if (preg_match('/^([0-9-]+)T([0-9]{2})-([0-9]{2})-([0-9]{2})/', $report->Source->TimeStamp, $regs)) {
                $timestamp = strtotime($regs[1].' '.$regs[2].':'.$regs[3].':'.$regs[4]);
                // Fall back to now if we can't parse the timestamp
            } else {
                $timestamp = time();
            }

            // The XML contains so many crap you cant even think about filters here so we grab the fields ourselves.
            $infoBlob = [
                'type'          => (string)$report->Source->Type,
                'port'          => (string)$report->Source->Port,
                'number_files'  => (string)$report->Source->Number_Files,
                'complainant'   => (string)$report->Complainant->Entity,
            ];

            $event = [
                'source'        => config("{$configBase}.parser.name"),
                'ip'            => (string)$report->Source->IP_Address,
                'domain'        => false,
                'uri'           => false,
                'class'         => config("{$configBase}.feeds.{$this->feedName}.class"),
                'type'          => config("{$configBase}.feeds.{$this->feedName}.type"),
                'timestamp'     => $timestamp,
                'information'   => json_encode($infoBlob),
            ];

            $events[] = $event;
        } else {
            return $this->failed(
                "Unable to get a valid XML."
            );
        }

        return $this->success($events);
    }
}
