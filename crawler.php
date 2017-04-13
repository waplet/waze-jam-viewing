<?php
include __DIR__ . '/_bootstrap.php';

ini_set("display_errors", "on");
$s = curl_init();

// Almost include whole Riga, Mājas -> Darbs
$params = [
    'latBottom' => 56.9126956,
    'lonRight' => 24.0262189,
    'latTop' => 56.9794156,
    'lonLeft' => 24.1759032,
];

$query = http_build_query($params);

curl_setopt($s, CURLOPT_URL, 'http://localhost:8080/waze/traffic-notifications?' . $query);
curl_setopt($s, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($s, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec($s);
curl_close($s);

$alerts = json_decode($response, true);

// Statement

foreach ($alerts as $type => $typeValues) {
    foreach ($typeValues as $alert) {
        \Illuminate\Database\Capsule\Manager::table('raw')->insert([
            'created' => time(),
            'response' => json_encode($alert),
            'type' => $type,
        ]);
    }
}


