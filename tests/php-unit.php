<?php
declare(strict_types=1);

$roots = glob('/rainloop/rainloop/v/*/app/libraries');
if (count($roots) !== 1) {
    throw new RuntimeException('Expected one built application version');
}
require $roots[0] . '/MailSo/MailSo.php';

$cases = [
    ['Alice <alice@example.test>', 'alice@example.test', 'Alice'],
    ['"Doe, Jane" <jane@example.test>', 'jane@example.test', 'Doe, Jane'],
    ['user@bücher.example', 'user@xn--bcher-kva.example', ''],
];
foreach ($cases as [$input, $address, $name]) {
    $email = MailSo\Mime\Email::Parse($input);
    if ($email->GetEmail() !== $address || $email->GetDisplayName() !== $name) {
        throw new RuntimeException('Address parsing failed: ' . $input);
    }
}
try {
    MailSo\Mime\Email::NewInstance('');
    throw new RuntimeException('Empty address was accepted');
} catch (MailSo\Base\Exceptions\InvalidArgumentException $expected) {
    // Expected rejection.
}
$collection = MailSo\Mime\EmailCollection::Parse('a@example.test, "Doe, Jane" <jane@example.test>');
if ($collection->Count() !== 2) {
    throw new RuntimeException('Recipient list parsing failed');
}
$decoded = MailSo\Base\Utils::DecodeHeaderValue('=?UTF-8?B?R3LDvMOfZQ==?=');
if ($decoded !== 'Grüße') {
    throw new RuntimeException('Unicode MIME header decoding failed');
}
echo "PHP mail compatibility tests passed\n";
