<?php

/*
 * This file is part of the Osynapsy package.
 *
 * (c) Pietro Celeste <p.celeste@osynapsy.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Osynapsy\Net\Imap;

/**
 * Description of ImapClient
 *
 * @author Pietro
 */
class Client
{
    private $connectionParameters = [];
    private $connection;

    public function __construct($username, $password, $host, $port = 143, $secure = null)
    {
        $this->connectionParameters = [
            'username' => $username,
            'password' => $password,
            'host' => $host,
            'port' => $port,
            'secure' => $secure
        ];
    }

    public function connect($mbox = 'INBOX')
    {
        $connectionString = $this->connectionStringFactory($mbox);
        $this->connection = imap_open(
            $connectionString,
            $this->connectionParameters['username'],
            $this->connectionParameters['password']
        );
        if (empty($this->connection)) {
            throw new \Exception(sprintf("Imap connection error %s", imap_last_error()));
        }
    }

    protected function connectionStringFactory($mbox)
    {
        $connectionString = '{';
        $connectionString .= $this->connectionParameters['host'];
        $connectionString .= ':';
        $connectionString .= $this->connectionParameters['port'];
        $connectionString .= '/imap';
        if ($this->connectionParameters['secure']) {
            $connectionString .= '/'.$this->connectionParameters['secure'];
            $connectionString .= '/novalidate-cert';
        }
        $connectionString .= '}';
        $connectionString .= $mbox;
        return $connectionString;
    }

    public function searchMessage($command)
    {
        return imap_search($this->connection, $command);
    }

    public function getAllMessage($messageIds)
    {
        if (empty($messageIds)) {
            return [];
        }
        $messages = [];
        foreach($messageIds as $messageId) {
            $messages[$messageId] = $this->messageFactory($messageId);
        }
        return $messages;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getMessage($messageIdx)
    {
        $Message = $this->getMessageRaw($messageIdx);
        return $Message->get();
    }

    public function messageFactory($messageIdx)
    {
        return new Message($this->connection, $messageIdx);
    }

    public function close()
    {
        imap_close($this->connection);
    }
}
