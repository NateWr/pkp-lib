<?php
/**
 * @file classes/announcement/maps/OAI.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAI
 *
 * @brief A class to get announcements and information about announcements
 */

namespace PKP\Announcement\Maps;

use Illuminate\Support\Enumerable;
use PKP\core\Map as MapBase;

class OAI extends MapBase
{
    public \DomDocument $xml;

    public function map(Enumerable $collection, \DomDocument $xml) : Enumerable
    {
        $this->collection = $collection;
        $this->xml = $xml;
        return $this->collection->map(function($item) use ($xml) {
            $rootNode = $xml->createElementNS('example', 'record');

            $headerNode = $xml->createElement('header');
            $headerNode->appendChild($xml->createElement('identifier', 'example'));
            $headerNode->appendChild($xml->createElement('datestamp', date('c')));
            $headerNode->appendChild($xml->createElement('setSpect', 'example'));

            $metadataNode = $xml->createElement('metadata');

            $oaidcNode = $xml->createElement('oai_dc:dc');
            $oaidcNode->setAttribute('xmlns:oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
            $oaidcNode->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
            // ...

            $titleNode = $xml->createElement('dc:title', $item->getLocalizedData('title'));
            $titleNode->setAttribute('xml:lang', 'en-US');
            // ...

            $oaidcNode->appendChild($titleNode);
            $metadataNode->appendChild($oaidcNode);

            $rootNode->appendChild($headerNode);
            $rootNode->appendChild($metadataNode);

            return $this->withExtensions($rootNode, $item);
        });
    }
}
