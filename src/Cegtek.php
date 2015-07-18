<?php

namespace AbuseIO\Parsers;

use Ddeboer\DataImport\Reader;
use Ddeboer\DataImport\Writer;
use Ddeboer\DataImport\Filter;
use Illuminate\Filesystem\Filesystem;
use SplFileObject;
use Uuid;
use Log;

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
        Log::info(
            get_class($this) . ': Received message from: ' .
            $this->parsedMail->getHeader('from') . " with subject: '" .
            $this->parsedMail->getHeader('subject') . "' arrived at parser: " .
            config('Cegtek.parser.name')
        );

        $events     = [ ];
        $feedName   = 'default';

        // XML is placed in the body
        if (preg_match(
            '/(?<=- ----Start ACNS XML\n)(.*)(?=\n- ----End ACNS XML)/s',
            $this->parsedMail->getMessageBody(),
            $regs
        )) {
            $xml = $regs[0];
        }

        if (empty(config("Cegtek.feeds.{$feedName}"))) {
            return $this->failed(
                "Detected feed '{$feedName}' is unknown."
            );
        }

        // If the feed is disabled, then just return as there is nothing more to do then
        // however its not a 'fail' in the sense we should start alerting as it was disabled
        // by design or user configuration
        if (config("Cegtek.feeds.{$feedName}.enabled") !== true) {
            return $this->success($events);
        }

        if (!empty($xml) && $xml = simplexml_load_string($xml)) {
            // Work around the crappy timestamp used by IP-echelon, i.e.: 2015-05-06T05-00-00UTC
            // We loose some timezone information, but hey it's close enough ;)
            if (preg_match('/^([0-9-]+)T([0-9]{2})-([0-9]{2})-([0-9]{2})/', $xml->Source->TimeStamp, $regs)) {
                $timestamp = strtotime($regs[1].' '.$regs[2].':'.$regs[3].':'.$regs[4]);
                // Fall back to now if we can't parse the timestamp
            } else {
                $timestamp = time();
            }

            $infoBlob = [
                'type'          => (string)$xml->Source->Type,
                'port'          => (string)$xml->Source->Port,
                'number_files'  => (string)$xml->Source->Number_Files,
                'complainant'   => (string)$xml->Complainant->Entity,
            ];

            $event = [
                'source'        => $this->config['parser']['name'],
                'ip'            => (string)$xml->Source->IP_Address,
                'domain'        => false,
                'uri'           => false,
                'class'         => config("Cegtek.feeds.{$feedName}.class"),
                'type'          => config("Cegtek.feeds.{$feedName}.type"),
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
