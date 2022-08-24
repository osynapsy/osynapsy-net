<?php

/*
 * This file is part of the Osynapsy package.
 *
 * (c) Pietro Celeste <p.celeste@osynapsy.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Osynapsy\Net\Imap;

/**
 * Description of ImapMessage
 *
 * @author Pietro
 */
class Message
{
    private $connection;
    private $messageIdx;
    private $message = [];
    private $flattenStructure = [];
    private $attachments = [];

    public function __construct($connection, $messageId)
    {
        $this->connection = $connection;
        $this->messageIdx = $messageId;
        $this->factory();
    }

    private function factory()
    {
        $structure = imap_fetchstructure($this->connection, $this->messageIdx);
        $this->buildflattenStructure($structure->parts);
    }

    public function buildFlattenStructure($parts, $prefix = '', $index = 1)
    {
        foreach($parts as $part) {
            $address  = $prefix . (empty($prefix) ? '' : '.') . $index;
            $this->flattenStructure[$address] = [
                'address' => $address,
                'filename' => $this->getPartFilename($part, $address)
            ];
            if ($part->type === 2) {
                $index = 0;
            } elseif ($index === 0) {
                $address = $prefix;
                $index = 1;
            }
            if (isset($part->parts)) {
                $this->buildFlattenStructure($part->parts, $address, $index);
            }
            $index++;
        }
    }

    private function getPartFilename($part, $address)
    {
        $filename = '';
        if($part->ifdparameters && $part->dparameters[0]->attribute === 'filename') {
            $filename = $part->dparameters[0]->value;
        } elseif ($part->ifparameters && $part->parameters[0]->attribute === 'name') {
            $filename = $part->parameters[0]->value;
        }
        if (!empty($filename)) {
            $this->attachments[$filename]  = $this->fetchBody($address, $part->encoding);
        }
        return $filename;
    }

    public function fetchBody($partIdx = 0, $encoding = 0)
    {
        if (empty($partIdx)) {
            return imap_body($this->connection, $this->messageIdx);
        }
        $text = imap_fetchbody($this->connection, $this->messageIdx, $partIdx);
        switch ($encoding) {
             # 7BIT
            case 0:
                return $text;
            # 8BIT
            case 1:
                return quoted_printable_decode(imap_8bit($text));
            # BINARY
            case 2:
                return imap_binary($text);
            # BASE64
            case 3:
                return imap_base64($text);
            # QUOTED-PRINTABLE
            case 4:
                return quoted_printable_decode($text);
            # OTHER
            case 5:
                return $text;
            # UNKNOWN
            default:
                return $text;
        }
    }

    public function get()
    {
        return $this->message;
    }

    public function getAttachments()
    {
        return $this->attachments;
    }

    public function getFilteredAttachments($filenameSearched)
    {
        return array_filter($this->attachments, function($attachmentFilename) use ($filenameSearched) {
           return (strpos($attachmentFilename, $filenameSearched) !== false);
        }, \ARRAY_FILTER_USE_KEY);
    }

    public function getFlattenStructure()
    {
        return $this->flattenStructure;
    }

    public function getDate()
    {
        $date = $this->getMessageInfo('date');
        if (empty($date)) {
            return null;
        }
        return (new \DateTime($date))->format('Y-m-d H:i:s');
    }

    public function getFrom()
    {
        $from = $this->getMessageInfo('from');
        if (empty($from)) {
            return null;
        }
        return sprintf('%s <%s@%s>', $from[0]->personal, $from[0]->mailbox, $from[0]->host);
    }

    public function getId()
    {
        return $this->messageIdx;
    }

    public function getMessageInfo($key)
    {
        $header = imap_headerinfo($this->connection, $this->messageIdx);
        return $header->{$key};
    }

    public function getSubject()
    {
        return $this->getMessageInfo('subject');
    }

    public function getUid()
    {
        return imap_uid($this->connection, $this->getId());
    }
}
