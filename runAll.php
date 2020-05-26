<?php

if (strpos($_SERVER["SCRIPT_FILENAME"], "XAMPP") !== false) {
    require("../difi/difiscripts/difilib.php");
} else {
    require("../difiscripts/difilib.php");
}

function startsWithDatahotel($string) {
    return (startsWith($string, "https://hotell.difi.no/?dataset=") || startsWith($string, "http://hotell.difi.no/?dataset="));
    }

$datanorgeEntries_raw = getHTTPCached("https://livarbergheim.no/api/datanorge_api_dump_pluss2.php");
$datanorgeEntries = json_decode($datanorgeEntries_raw["content"], true);

// Delete previously generated files
// https://stackoverflow.com/a/4594262
$files = glob('specs/*'); // get all file names
foreach($files as $file){ // iterate files
  if(is_file($file))
    unlink($file); // delete file
    // print($file . "\n");
}

// die();

$i = 0;
foreach ($datanorgeEntries as $datanorgeEntry) {
    $hasDatahotelDistribution = false;
    if (!array_key_exists("distribution", $datanorgeEntry)) continue; // data.norge-entry without distribution
    foreach ($datanorgeEntry["distribution"] as $distribution) {
        if (startsWithDatahotel($distribution["accessURL"])) {
            $hasDatahotelDistribution = true;
            break;
        }
    }

    if ($hasDatahotelDistribution) {
        $datanorgeId_exploded = explode("/", $datanorgeEntry["id"]);
        $nodeId = $datanorgeId_exploded[count($datanorgeId_exploded)-1];
        print("Getting $nodeId<br/>\n");
        file_get_contents("http://localhost/datahotelswagger/?node=$nodeId");
        $i++;
        // if ($i > 2) break;
    }
}

print("Genererte $i\n");
?>