<?php
// Minimal loopback-only protocol fixture; never shipped in the production image.
declare(strict_types=1);
$protocol = $argv[1];
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$server) {
    throw new RuntimeException($error);
}
echo stream_socket_get_name($server, false) . "\n";
flush();
$connection = stream_socket_accept($server, 10);
if (!$connection) {
    throw new RuntimeException('No fixture client connected');
}
stream_set_timeout($connection, 5);
$emit = static function (string $line) use ($connection): void {
    fwrite($connection, $line . "\r\n");
};
$observed = ['authenticated' => false, 'fetched' => false, 'submitted' => false];
$emit($protocol === 'imap' ? '* OK [CAPABILITY IMAP4rev1] synthetic fixture' : '220 fixture ESMTP');
while (($line = fgets($connection)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($protocol === 'imap') {
        [$tag, $command, $arguments] = array_pad(explode(' ', $line, 3), 3, '');
        $command = strtoupper($command);
        if ($command === 'CAPABILITY') {
            $emit('* CAPABILITY IMAP4rev1');
        } elseif ($command === 'LOGIN') {
            $arguments = str_replace('"', '', $arguments);
            $observed['authenticated'] = $arguments === 'user@example.test synthetic-password';
            if (!$observed['authenticated']) {
                $emit($tag . ' NO [AUTHENTICATIONFAILED] Invalid credentials');
                continue;
            }
        } elseif ($command === 'LOGOUT') {
            $emit('* BYE logout');
            $emit($tag . ' OK LOGOUT');
            break;
        } elseif (!$observed['authenticated']) {
            $emit($tag . ' NO Authentication required');
            continue;
        } elseif ($command === 'SELECT') {
            $emit('* FLAGS (\\Seen)');
            $emit('* 1 EXISTS');
            $emit('* 0 RECENT');
            $emit('* OK [UIDVALIDITY 1] valid');
            $emit('* OK [UIDNEXT 2] next');
        } elseif ($command === 'FETCH' || ($command === 'UID' && str_starts_with($arguments, 'FETCH '))) {
            $body = "Synthetic mailbox message\r\n";
            fwrite($connection, '* 1 FETCH (UID 1 BODY[TEXT] {' . strlen($body) . "}\r\n" . $body . ")\r\n");
            $observed['fetched'] = true;
        } else {
            $emit($tag . ' BAD Unexpected command');
            continue;
        }
        $emit($tag . ' OK ' . $command . ' completed');
    } else {
        [$command, $arguments] = array_pad(explode(' ', $line, 2), 2, '');
        $command = strtoupper($command);
        if ($command === 'EHLO') {
            $emit('250-fixture');
            $emit('250 AUTH PLAIN');
        } elseif ($command === 'AUTH' && str_starts_with($arguments, 'PLAIN')) {
            $token = trim(substr($arguments, 5));
            if ($token === '') {
                $emit('334 ');
                $token = trim((string) fgets($connection));
            }
            $observed['authenticated'] = base64_decode($token, true) === "\0user@example.test\0synthetic-password";
            $emit($observed['authenticated'] ? '235 Authenticated' : '535 Invalid credentials');
        } elseif ($command === 'QUIT') {
            $emit('221 Bye');
            break;
        } elseif (!$observed['authenticated']) {
            $emit('530 Authentication required');
        } elseif ($command === 'MAIL' || $command === 'RCPT') {
            $emit('250 OK');
        } elseif ($command === 'DATA') {
            $emit('354 Send message');
            $message = '';
            while (($part = fgets($connection)) !== false && $part !== ".\r\n") {
                $message .= $part;
            }
            $observed['submitted'] = str_contains($message, 'Subject: synthetic') && str_contains($message, 'Synthetic outgoing message');
            $emit($observed['submitted'] ? '250 Queued synthetic message' : '554 Invalid fixture message');
        } else {
            $emit('500 Unexpected command');
        }
    }
}
fclose($connection);
fclose($server);
echo json_encode($observed) . "\n";
