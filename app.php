<?php
include __DIR__ . '/_bootstrap.php';

$jams = \Illuminate\Database\Capsule\Manager::table('raw')
    ->where(['type' => 'jams'])
    // ->where('created', '>',  strtotime('2017-04-02 07:00:00'))
    // ->where('created', '<',  strtotime('2017-04-06 20:00:00'))
    // ->limit(10)
    ->get();

// Pēc 03.04.2017 10:00 ir lielāks range

$beginning = 7; // start hour
$end = 20; // end hour

$range = 15; // minutes

$totalAmplitude = 2 * $range;

$jamsClear = array_map(function ($jam) {
    $data = json_decode($jam->response);

    return [
        'created' => $jam->created - $data->delayInSec,
        'data' => $data,
    ];
}, $jams->all());

$jamsCalculated = array_map(function ($jam) {
    $date = \DateTime::createFromFormat('U', $jam['created'])
        ->setTimezone(new DateTimeZone('Europe/Riga'));

    $hoursS = $date->format('G') * 60 * 60; // seconds
    $minutesS = $date->format('i') * 60; // seconds
    $seconds = $date->format('s'); // seconds
    $secondsTotal = (int)$hoursS + (int)$minutesS + (int)$seconds;

    return [
        'secondsFromMidnight' => $secondsTotal,
        'jam' => $jam['data'],
    ];
}, $jamsClear);


usort($jamsCalculated, function ($jamA, $jamB) {
    return $jamA['secondsFromMidnight'] > $jamB['secondsFromMidnight'];
});

// dd($jamsCalculated);
// $jamsFiltered = [];
// for ($i = $beginning * 60; $i <= $end * 60; $i = $i + $range) {
//     $jamsFiltered[$i] = array_filter($jamsCalculated, function ($jam) use ($i, $range) {
//         return $i * 60 - $range * 60 < $jam['secondsFromMidnight'] && $i * 60 + $range * 60 > $jam['secondsFromMidnight'];
//     });
// }

// dd($jamsFiltered);
// $result = $jamsFiltered[rand(0, count($jamsFiltered) - 1)];

// $minute = array_rand($jamsFiltered, 1);
// $result = $jamsFiltered[$minute];

$minute = ($beginning + 1) * 60; // Starting value
$result = $jamsCalculated;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Map</title>
    <meta name="viewport" content="initial-scale=1.0">
    <meta charset="utf-8">
    <style>
        /* Always set the map height explicitly to define the size of the div
         * element that contains the map. */
        #map {
            height: 100%;
        }
        /* Optional: Makes the sample page fill the window. */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        #info {
            width:100%;
            height:50px;
            background: white;
            position: absolute;
            top: 0;
            padding-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
<div id="map"></div>
<div id="info">
    <div id="info-text"></div>
    <div>
        Time: <input
                id="time-input"
                type="range"
                min="<?= $beginning * 60; ?>"
                step="<?= $range; ?>"
                max="<?= $end * 60; ?>"
                value="<?= $minute; ?>"
                onchange="onTimeChanged()"
                oninput="onTimeChanged()"/>
        <span id="time-value"></span>
    </div>
</div>
<script>
    var map;
    var lines = [];

    var timeRangeInput = document.getElementById('time-input');
    var timeRangeOutput = document.getElementById('time-value');
    var infoNode = document.getElementById('info-text');
    var time = <?= json_encode($minute); ?>;
    var timeRange = <?= json_encode($range); ?>;
    var jams = <?= json_encode($result); ?>;


    /**
     * Inits google maps
     */
    function initMap() {
        map = new google.maps.Map(document.getElementById('map'), {
            center: {lat: 56.9431319, lng: 24.1060971},
            zoom: 13
        });

        var jamsInRange = getJamsInRange(time, timeRange);
        draw(jamsInRange);

        updateInfoNode();
        updateTime();
    }

    function updateTime() {
        timeRangeOutput.textContent = convertMinsToHrsMins(timeRangeInput.value);
        time = timeRangeInput.value;
    }

    /**
     * @param minutes
     * @return {string}
     */
    function convertMinsToHrsMins(minutes) {
        var h = Math.floor(minutes / 60);
        var m = minutes % 60;
        h = h < 10 ? '0' + h : h;
        m = m < 10 ? '0' + m : m;
        return h + ':' + m;
    }

    /**
     * @param {int} time
     * @param {int} range
     */
    function getJamsInRange(time, range) {
        var result = [];

        for (var i in jams) {
            var jam = jams[i];

            // console.log('Bottom: ', convertMinsToHrsMins(time - range));
            // console.log('End: ', convertMinsToHrsMins(jam.secondsFromMidnight / 60));
            // console.log('Diff: ', (time * 60 - range * 60) <= jam.secondsFromMidnight);
            // console.log('Diff2: ', (time * 60 + range * 60) >= jam.secondsFromMidnight);
            if (((time * 60 - range * 60) <= jam.secondsFromMidnight) && ((time * 60 + range * 60) >= jam.secondsFromMidnight)) {
                result.push(jam.jam);
            }
        }

        return result;
    }

    function onTimeChanged() {
        updateTime();
        clearMap();

        // get new range
        var jamsInRange = getJamsInRange(time, timeRange);
        draw(jamsInRange);
    }

    function draw(jams) {
        for (var i in jams) {
            var jam = jams[i];

            lines.push(new google.maps.Polyline({
                map: map,
                path: [
                    {lat: parseFloat(jam.startLatitude), lng: parseFloat(jam.startLongitude)},
                    {lat: parseFloat(jam.endLatitude), lng: parseFloat(jam.endLongitude)}
                ],
                strokeColor: '#FF0000',
                strokeOpacity: 1.0,
                strokeWeight: 3
            }));
        }
    }

    function updateInfoNode() {
        infoNode.textContent = 'Total jams: ' + Object.keys(jams).length +
            '; Time: ' + convertMinsToHrsMins(time) +
            '; Minute range: ' + timeRange;
    }

    function clearMap() {
        lines.map(function (line) {
            line.setMap(null);
        });
        lines.splice(0);
    }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBE0vjj6JkjDfCOWyELijL4rWvExHBR46s&callback=initMap" async defer></script>
</body>
</html>
