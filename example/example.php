<?php
/**
 * example how Mood process data
 * keep an array of object and execute an action over it.
 */
require __DIR__.'/../vendor/autoload.php';

use SdotB\Mood\User;
use SdotB\Utils\Utils;

$db = null;
define('SESSION_DURATION_TIME', 7200);

$unit1 = [
    'id' => 'US123456',
    'firstName' => 'mario',
    'lastName' => 'rossi',
];
$unit2 = [
    'id' => 'US111111',
    'firstName' => 'giuseppe',
    'lastName' => 'verdi',
];
$unit3 = [
    'id' => 'US222222',
    'firstName' => 'paola',
    'lastName' => 'bianchi',
];

$list = [
    $unit1,
    $unit2,
    $unit3,
];

$data['REQUEST'] = $list;

$user = new User([$db, Utils::randStr(16)]);

$data['RESPONSE'] = $user->uppercase($list);

echo json_encode($data);
