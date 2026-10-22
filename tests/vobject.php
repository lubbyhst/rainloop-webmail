<?php
declare(strict_types=1);
$libraries = getenv('RAINLOOP_TEST_LIBRARIES') ?: glob('/rainloop/rainloop/v/*/app/libraries')[0];
spl_autoload_register(static function (string $name) use ($libraries): void {
    if (str_starts_with($name, 'SabreForRainLoop\\VObject\\')) {
        require $libraries . '/' . str_replace('\\', '/', $name) . '.php';
    }
});
$calendar = new SabreForRainLoop\VObject\Component\VCalendar();
$geo = $calendar->createProperty('GEO', [52.5, 13.4]);
if ($geo->getJsonValue() !== [[52.5, 13.4]] || !str_contains($geo->serialize(), '52.5;13.4')) {
    throw new RuntimeException('Calendar GEO floating-point values were not preserved');
}
foreach ([$calendar, new SabreForRainLoop\VObject\Component\VCard()] as $document) {
    $value = $document->createProperty('X-COORDINATE', 1.25, [], 'FLOAT');
    if ($value->getValueType() !== 'FLOAT' || $value->getJsonValue() !== [1.25]) {
        throw new RuntimeException('Explicit FLOAT values were not preserved');
    }
}
echo "vCard/calendar floating-point regression tests passed\n";
