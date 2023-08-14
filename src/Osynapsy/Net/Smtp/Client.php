<?php

/**
 * Copyright (c) 2011, Howard Walsh, KidMoses.com.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *  * Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above
 *    copyright notice, this list of conditions and the following
 *    disclaimer in the documentation and/or other materials provided
 *    with the distribution.
 *
 *  * Neither the names of Howard Walsh or KidMoses.com, nor
 *    the names of its contributors may be used to endorse or promote
 *    products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY
 * WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY
 * OF SUCH DAMAGE.
 **/

namespace Osynapsy\Net\Smtp;

/**
 * Osynapsy\Net\Smtp\Client is derived by KM_Mailer 1.5
 * KM_Mailer is a SMTP Class for PHP, Version 1.5
 * * created by KidMoses (Howard Walsh)
 *
 * This is a smiple SMTP class that supports secure login
 * through TLS or SSL connections. Can be used to send email
 * through GMail for example.
 *
 * Email can be sent as either plain text, html text or a
 * combination of both.

 * Please send support questions to support@kidmoses.com
 *
 * INSTRUCTIONS
 * Create an instance of the class with a call to :
 * $mail = new Osynapsy\Net\Smtp\Client(server, port, username=null, password=null, secure=null);
 *
 * server : the name of the server you are connecting to
 * port : the port number to use (typically 25, 465 or 587)
 * username : your username needed to log into the server
 * password : the password needed to log into the server
 * secure : can be tls, ssl or none
 *
 * You can check if your have successfully logged in by checking $mail->isLogin
 *
 * Once the instance is created, you can send mail by calling :
 * $mail->send(from, to, subject, body, headers = optional);
 *
 * from : sender's email address (myname@mydomain.com OR MyName <myname@mydomain.com>)
 * to : recipient's email address (ie: yourname@yourdomain.com OR YourName <yourname@yourdomain.com>)
 * subject : email subject
 * body : email message body, usually in HTML format
 * headers : any special headers required
 *
 * See example.php for more tips
 *
 * In this version you can also add multiple recipents, carbon-copies(CC), blind-copies(BCC) and attachments
 * For example:
 * $mail->addRecipient("yourname@yourdomain.com");
 * $mail->addCC("yourname@yourdomain.com");
 * $mail->addBCC("yourname@yourdomain.com");
 * $mail->addAttachment("pathToAttachment");
 *
 * To clear recipients and attachments use:
 * $mail->clearRecipients();
 * $mail->clearCC();
 * $mail->clearBCC();
 * $mail->clearAttachments();
 *
 **/
class Client
{
    const NEWLINE = "\r\n";

    public $server;
    public $port;
    public $username;
    public $password;
    public $secure;    /* can be tls, ssl, or none */

    public $charset = "\"iso-8859-1\""; /* included double quotes on purpose */
    //public $charset = "\"utf-8\"";
    public $contentType = "multipart/mixed";  /* can be set to: text/plain, text/html, multipart/mixed */
    public $transferEncodeing = "quoted-printable"; /* or 8-bit  */
    public $altBody = "";
    public $isLogin = false;
    public $recipients = array();
    public $cc = array();
    public $bcc = array();
    public $attachments = array();

    private $conn;
    private $newline = "\r\n";
    private $localhost = 'localhost';
    private $timeout = '60';
    private $debug = false;
    private $certificateVerify = false;
    public $errors = [];
    private $response = [];
    private $contextOptions = [];
    private $currentResponse;

    public function __construct($server, $port, $username = null, $password = null, $secure = null)
    {
        $this->server = $server;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->secure = strtolower(trim($secure));
        if ($this->secure == 'ssl') {
            $this->server = 'ssl://'.$server;
        }
        if (in_array($this->secure,['tls','ssl'])) {
            $this->verifyCertificate(false);
        }
        $this->connect();
        $this->auth();
    }

    /* Connect to the server */
    private function connect()
    {
        $streamContext = stream_context_create($this->contextOptions);
        $this->conn = stream_socket_client(
            $this->server.':'.$this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $streamContext
        );
        if (!$this->conn) {
            $this->raiseException(sprintf('ERROR : %s %s', $errno, $errstr));
        }
        if (substr($this->getServerResponse(),0,3) != '220') {
            $this->raiseException($this->currentResponse);
        }
    }

    public function verifyCertificate($verify)
    {
        if (!array_key_exists('ssl', $this->contextOptions)) {
            $this->contextOptions['ssl'] = [];
        }
        $this->contextOptions['ssl']['verify_peer'] = $verify;
        $this->contextOptions['ssl']['verify_peer_name'] = $verify;
        $this->contextOptions['ssl']['allow_self_signed'] = !$verify;
        return $this;
    }

    /* sign in / authenicate */
    private function auth()
    {
        $this->putRow('HELO ' . $this->localhost);
        if ($this->secure == 'tls') {
            $this->authTls();
        }
        if ($this->server == 'localhost') {
            $this->isLogin = true;
            return;
        }
        if ($this->putRow('AUTH LOGIN') != '334') {
            $this->raiseException($this->currentResponse);
        }
        if ($this->putRow(base64_encode($this->username)) != '334') {
            $this->raiseException($this->currentResponse);
        }
        if ($this->putRow(base64_encode($this->password)) != '235') {
            $this->raiseException($this->currentResponse);
        }
        $this->isLogin = true;
    }

    private function authTls()
    {
        if ($this->putRow('STARTTLS') != '220') {
            $this->raiseException($this->currentResponse);
        }
        stream_socket_enable_crypto($this->conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($this->putRow('HELO ' . $this->localhost) != '250') {
            $this->raiseException($this->currentResponse);
        }
    }

    private function putRow($command)
    {
        fputs($this->conn, $command . self::NEWLINE);
        $this->currentResponse = $this->getServerResponse();
        return substr($this->currentResponse, 0, 3);
    }

    /* send the email message */
    public function send($from, $to, $subject, $message, $headers=null, $utf8=false)
    {
        /* set up the headers and message body with attachments if necessary */
        $email  = "Date: " . date("D, j M Y G:i:s") . " +0200" . self::NEWLINE;
        $email .= "From: $from" . self::NEWLINE;
        $email .= "Reply-To: $from" . self::NEWLINE;
        $email .= $this->setRecipients($to);

        if ($headers != null) {
            $email .= $headers . self::NEWLINE;
        }
        if ($utf8) {
            $message = utf8_decode($message);
        }
        $email .= "Subject: $subject" . self::NEWLINE;
        $email .= "MIME-Version: 1.0" . self::NEWLINE;
        if ($this->contentType == "multipart/mixed") {
            $boundary = $this->generateBoundary();
            $message = $this->multipartMessage($message,$boundary);
            $email .= "Content-Type: $this->contentType;" . self::NEWLINE;
            $email .= "    boundary=\"$boundary\"";
        } else {
            $email .= "Content-Type: $this->contentType; charset=$this->charset";
        }
        $email .= self::NEWLINE . self::NEWLINE . $message . self::NEWLINE;
        //$email .= "." . self::NEWLINE;
        $email .= ".";
        /* set up the server commands and send */
        $this->putRow('MAIL FROM: <'. $this->getMailAddr($from) .'>');
        if (!empty($to)) {
            $this->putRow('RCPT TO: <'. $this->getMailAddr($to) .'>');
        }
        $this->sendRecipients($this->recipients);
        $this->sendRecipients($this->cc);
        $this->sendRecipients($this->bcc);
        $this->putRow('DATA');
        //fputs($this->conn, $email);  /* transmit the entire email here */
        //return substr($this->getServerResponse(),0,3) != '250'  ? false : true;
        /* transmit the entire email here */
        if ($this->putRow($email) != '250') {
            $this->errors[] = $email;
            throw new \Exception(sprintf("Mail don't send : %s", $this->currentResponse));
        }
    }

    private function setRecipients($to) /* assumes there is at least one recipient */
    {
        $r = 'To: ';
        if (!($to == '')) {
            $r .= $to . ',';
        }
        if (count($this->recipients) > 0) {
            for($i=0;$i<count($this->recipients);$i++) {
                $r .= $this->recipients[$i] . ',';
            }
        }
        $r = substr($r,0,-1) . self::NEWLINE;  /* strip last comma */;
        if(count($this->cc)>0) { /* now add in any CCs */
            $r .= 'CC: ';
            for($i=0;$i<count($this->cc);$i++) {
                $r .= $this->cc[$i] . ',';
            }
            $r = substr($r,0,-1) . self::NEWLINE;  /* strip last comma */
        }
        return $r;
    }

    private function sendRecipients(array $recipients)
    {
        if (empty($recipients)) {
            return;
        }
        foreach($recipients as $recipient) {
            $this->putRow('RCPT TO: <'. $this->getMailAddr($recipient) .'>');
        }
    }

    public function addRecipient($recipient)
    {
        $this->recipients[] = $recipient;
    }

    public function clearRecipients()
    {
        $this->recipients = [];
    }

    public function addCC($c)
    {
        $this->cc[] = $c;
    }

    public function clearCC()
    {
        unset($this->cc);
        $this->cc = array();
    }

    public function addBCC($bc)
    {
        $this->bcc[] = $bc;
    }

    public function clearBCC()
    {
        unset($this->bcc);
        $this->bcc = array();
    }

    public function addAttachmentFromFile($filePath)
    {
        if (!is_file($filePath)) {
            throw new \Exception(sprintf('Attachment file %s do not exists', $filePath));
        }
        $content = file_get_contents($filePath);
        $this->attachments[] = [
            'filename' => $filePath,
            'content' => $content
        ];
    }

    public function addAttachmentFromString($filename, $filecontent)
    {
        if (empty($filecontent)) {
            throw new \Exception('Attachment filename is empty');
        }
        if (empty($filecontent)) {
            throw new \Exception('Attachment content is empty');
        }
        $this->attachments[] = [
            'filename' => $filename,
            'content' => $filecontent
        ];
    }

    public function clearAttachments()
    {
        unset($this->attachments);
        $this->attachments = array();
    }

    /* Quit and disconnect */
    function __destruct()
    {
        fputs($this->conn, 'QUIT' . self::NEWLINE);
        $this->getServerResponse();
        fclose($this->conn);
    }

    /* private functions used internally */
    private function getServerResponse()
    {
        $data = "";
        while($str = fgets($this->conn,4096)) {
            $data .= $str;
            if(substr($str,3,1) == " ") {
                break;
            }
        }
        if($this->debug) {
            echo $data . "<br>";
        }
        $this->response[] = trim($data);
        return $data;
    }

    private function getMailAddr($emailaddr)
    {
        $addr = $emailaddr;
        $strSpace = strrpos($emailaddr,' ');
        if ($strSpace > 0) {
            $addr = substr($emailaddr, $strSpace+1);
            $addr = str_replace("<","",$addr);
            $addr = str_replace(">","",$addr);
        }
        return $addr;
    }

    public function getResponse()
    {
        return implode(PHP_EOL,$this->response);
    }

    private function randID($len)
    {
        $index = "abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $out = "";
        for ($t=0; $t<$len;$t++) {
            $r = rand(0,61);
            $out = $out . substr($index,$r,1);
        }
        return $out;
    }

    private function generateBoundary()
    {
        $boundary = "--=_NextPart_000_";
        $boundary .= $this->randID(4) . "_";
        $boundary .= $this->randID(8) . ".";
        $boundary .= $this->randID(8);
        return $boundary;
    }

    private function multipartMessage($htmlpart, $boundary)
    {
        if($this->altBody == "") {
            $this->altBody = strip_tags($htmlpart);
        }
        $altBoundary = $this->generateBoundary();
        //ob_start(); //Turn on output buffering
        $parts  = "This is a multi-part message in MIME format." . self::NEWLINE . self::NEWLINE;
        $parts .= "--" . $boundary . self::NEWLINE;
        $parts .= "Content-Type: multipart/alternative;" . self::NEWLINE;
        $parts .= "    boundary=\"$altBoundary\"" . self::NEWLINE . self::NEWLINE;
        $parts .= "--" . $altBoundary . self::NEWLINE;
        $parts .= "Content-Type: text/plain; charset=$this->charset" . self::NEWLINE;
        $parts .= "Content-Transfer-Encoding: $this->transferEncodeing" . self::NEWLINE . self::NEWLINE;
        $parts .= $this->altBody . self::NEWLINE . self::NEWLINE;
        $parts .= "--" . $altBoundary . self::NEWLINE;
        $parts .= "Content-Type: text/html; charset=$this->charset" . self::NEWLINE;
        $parts .= "Content-Transfer-Encoding: $this->transferEncodeing" . self::NEWLINE . self::NEWLINE;
        $parts .= $htmlpart . self::NEWLINE . self::NEWLINE;
        $parts .= "--" . $altBoundary . "--" . self::NEWLINE . self::NEWLINE;
        if (count($this->attachments) > 0) {
            foreach ($this->attachments as $attachment) {
                $parts .= $this->multipartMessageAttach($boundary, $attachment);
            }
        }
        $parts .= "--" . $boundary . "--";
        //$message = ob_get_clean(); //Turn off output buffering
        return $parts;
    }

    protected function multipartMessageAttach($boundary, $attachment)
    {
        $filename = basename($attachment['filename']);
        $attachmentBody = chunk_split(base64_encode($attachment['content']));
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $parts = "--" . $boundary . self::NEWLINE;
        $parts .= "Content-Type: application/$ext; name=\"$filename\"" . self::NEWLINE;
        $parts .= "Content-Transfer-Encoding: base64" . self::NEWLINE;
        $parts .= "Content-Disposition: attachment; filename=\"$filename\"" . self::NEWLINE . self::NEWLINE;
        $parts .= $attachmentBody . self::NEWLINE;
        return $parts;
    }
    
    protected function setCharset($charset)
    {
        $this->charset = sprintf("%s", $charset);
    }
    
    protected function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    protected function raiseException($message)
    {
        throw new \Exception($message);
    }
}
