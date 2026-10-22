<?php
declare(strict_types=1);
require glob('/rainloop/rainloop/v/*/app/libraries/MailSo/MailSo.php')[0];

foreach (['imap', 'smtp'] as $protocol) {
    foreach ([true, false] as $validPassword) {
        $process = proc_open([PHP_BINARY, __DIR__ . '/mail-server.php', $protocol],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start mail fixture');
        }
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 15);
        $endpoint = trim((string) fgets($pipes[1]));
        $port = (int) substr(strrchr($endpoint, ':'), 1);
        $client = $protocol === 'imap' ? MailSo\Imap\ImapClient::NewInstance() : MailSo\Smtp\SmtpClient::NewInstance();
        $client->SetLogger(MailSo\Log\Logger::NewInstance());
        try {
            if ($protocol === 'imap') {
                $client->Connect('127.0.0.1', $port, MailSo\Net\Enumerations\ConnectionSecurityType::NONE);
            } else {
                $client->Connect('127.0.0.1', $port, 'localhost', MailSo\Net\Enumerations\ConnectionSecurityType::NONE);
            }
            $rejected = false;
            try {
                $client->Login('user@example.test', $validPassword ? 'synthetic-password' : 'wrong-password');
            } catch (MailSo\Imap\Exceptions\LoginBadCredentialsException | MailSo\Smtp\Exceptions\LoginBadCredentialsException $expected) {
                $rejected = true;
            }
            if ($rejected === $validPassword) {
                throw new RuntimeException('Unexpected authentication result for ' . $protocol);
            }
            if ($validPassword && $protocol === 'imap') {
                $client->FolderSelect('INBOX');
                $messages = $client->Fetch(['UID', 'BODY.PEEK[TEXT]'], '1', true);
                if (count($messages) !== 1 || $messages[0]->GetFetchValue('BODY[TEXT]') !== "Synthetic mailbox message\r\n") {
                    throw new RuntimeException('IMAP message retrieval failed');
                }
            } elseif ($validPassword) {
                $client->MailFrom('user@example.test')->Rcpt('recipient@example.test');
                $client->Data("From: user@example.test\r\nTo: recipient@example.test\r\nSubject: synthetic\r\n\r\nSynthetic outgoing message\r\n");
            }
            $client->LogoutAndDisconnect();
            $observed = json_decode(trim(stream_get_contents($pipes[1])), true, 512, JSON_THROW_ON_ERROR);
            if ($observed['authenticated'] !== $validPassword ||
                ($validPassword && !$observed[$protocol === 'imap' ? 'fetched' : 'submitted'])) {
                throw new RuntimeException('Mail fixture did not observe the expected operation');
            }
        } finally {
            $client->Disconnect();
            fclose($pipes[1]);
            proc_terminate($process);
            proc_close($process);
        }
    }
}
echo "IMAP retrieval and SMTP submission tests passed\n";
