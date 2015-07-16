<?php

namespace AbuseIO\Parsers;

use AbuseIO\Parsers\Parser;
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
    public $config;

    public function __construct($parsedMail, $arfMail, $config = false)
    {
        $this->configFile = __DIR__ . '/../config/' . basename(__FILE__);
        $this->config = $config;
        $this->parsedMail = $parsedMail;
        $this->arfMail = $arfMail;

    }

    public function parse()
    {

        Log::info(
            get_class($this) . ': Received message from: ' .
            $this->parsedMail->getHeader('from') . " with subject: '" .
            $this->parsedMail->getHeader('subject') . "' arrived at parser: " .
            $this->config['parser']['name']
        );

        $events = [];
        $feed   = 'default';

        // XML is placed in the body
        if (preg_match('/(?<=- ----Start ACNS XML\n)(.*)(?=\n- ----End ACNS XML)/s', $this->parsedMail->getMessageBody(), $regs)) {
            $xml    = $regs[0];
        }

        if (!isset($this->config['feeds'][$feed])) {
            return $this->failed("Detected feed ${feed} is unknown. No sense in trying to parse.");
        } else {
            $feedConfig = $this->config['feeds'][$feed];
        }

        if ($feedConfig['enabled'] !== true) {
            return $this->success(
                "Detected feed ${feed} has been disabled by configuration. No sense in trying to parse."
            );
        }

        if (!empty($xml) && $xml = simplexml_load_string($xml)) {
            // Work around the crappy timestamp used by IP-echelon, i.e.: 2015-05-06T05-00-00UTC
            // We loose some timezone information, but hey it's close enough ;)
            if (preg_match('/^([0-9-]+)T([0-9]{2})-([0-9]{2})-([0-9]{2})/',$xml->Source->TimeStamp,$regs)) {
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
                'class'         => $feedConfig['class'],
                'type'          => $feedConfig['type'],
                'timestamp'     => $timestamp,
                'information'   => json_encode($infoBlob),
            ];

            $events[] = $event;
        } else {
            return $this->failed(
                "Unable to get a valid XML. No sense in trying to parse."
            );
        }

        return $this->success($events);
    }
}
