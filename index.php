<?php
if (strpos($_SERVER["SCRIPT_FILENAME"], "XAMPP") !== false) {
    require("../difi/difiscripts/difilib.php");
} else {
    require("../difiscripts/difilib.php");
}

// "difi/elma/participants" --> "difi-elma-participants"
function generateDatasetId($hotelDataset) {
    $newId = str_replace("/", "-", $hotelDataset);
    return $newId;
}

function generateOperationId($hotelDataset, $prefix = "", $suffix = "") {
    $split = explode("/", $hotelDataset);
    $oi = implode("", array_map('ucfirst', $split));
    return $prefix . $oi . $suffix;
}

function startsWithDatahotel($string) {
    return (startsWith($string, "https://hotell.difi.no/?dataset=") || startsWith($string, "http://hotell.difi.no/?dataset="));
}

// Sjekk input-verdi
$node = 2865; // "Teknisk kjøretøyinformasjon"
$node = 1152; // "Norske mottakere i OpenPEPPOL"
$node = "X";
if (isset($_GET["node"]) && ctype_digit($_GET["node"])) {
    $node = $_GET["node"];
}

// Internationalization
$lang = "no";
if (isset($_GET["lang"])) {
    $lang_raw = $_GET["lang"];
    if ($lang_raw == "no") {
        // do nothing
    } else if ($lang_raw == "en") {
        $lang = "en";
    } else {
        die("Unsupported language.");
    }
}

// TODO: lag tag/lenke til _all på datahotellet

// Get datanorge-catalogue
$datanorgeEntries_raw = getHTTPCached("https://livarbergheim.no/api/datanorge_api_dump_pluss2.php");
$datanorgeEntries = json_decode($datanorgeEntries_raw["content"], true);

if ($node == "X") { // List all data.norge-entries with datahotel-entries

    print(($lang == "en") ? "Choose a data.norge-entry to view Open API-spec:<br/><br/>\n" : "Velg data.norge-oppføring å vise API-spec for:<br/><br/>\n");

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
            print(
                "Norsk: "
                    . "<a href=\"?node=" . $nodeId . "\">JSON</a>"
                    . "&nbsp; <a href=\"https://editor.swagger.io/?url=" . urlencode("https://livarbergheim.no/datahotelswagger/?node=" . $nodeId) . "\">Editor</a>"
                . " — Engelsk: "
                . "<a href=\"?node=" . $nodeId . "&lang=en\">JSON</a>"
                . "&nbsp; <a href=\"https://editor.swagger.io/?url=" . urlencode("https://livarbergheim.no/datahotelswagger/?lang=en&node=" . $nodeId) . "\">Editor</a>"
                . " — " . $datanorgeEntry["title"] . " — " . $datanorgeEntry["publisher"]["name"]
                . (array_key_exists("title_en", $datanorgeEntry) ? " — har engelsk beskrivelse" : "")
                . "<br/>\n"
            );
        }
    }

    exit();
} else {
    header('Content-Type: application/json; charset=utf-8');

    /// CORS (not full support, but should be enough)
    header("Access-Control-Allow-Origin: *");
}

// Find matching entry in catalogue
$datanorgeData = null;
foreach ($datanorgeEntries as $datanorgeEntry) {
    if (endsWith($datanorgeEntry["id"], "/" . $node)) {
        $datanorgeData = $datanorgeEntry;
    }
}
if ($datanorgeData === null) {
    http_response_code(404);
    die("No matching datanorge-entry to node id $node");
}

$datanorge_title = $datanorgeData["title"];
$datanorge_lang = "nb";
if ($lang == "en" && array_key_exists("title_en", $datanorgeData)) {
    $datanorge_title = $datanorgeData["title_en"];
    $datanorge_lang = "en";
}

$datanorgeDescription = "lorem ipsum";
foreach ($datanorgeData["description"] as $description) {
    if ($description["language"] == $datanorge_lang) {
        $datanorgeDescription = $description["value"];
        break;
    }
}
$datanorgeDescription = 
    (($lang == "en") ? "NB! Beta. This auto-genereated specification is under development" : ("<i>NB! Beta. Denne auto-genererte spesifikasjonen er under utvikling. " 
    . "For ytterlegare dokumentasjon av datahotellet, så <a href=\"https://hotell.difi.no/api\">https://hotell.difi.no/api</a></i>\n"))
    . $datanorgeDescription
    . (($lang == "en") ? ("<p>See <a href=\"" . $datanorgeData["id"] . "\">data.norge.no-entry</a> for more information (update frequency, links etc.)</p>\n") 
        : ("<p>Sjå <a href=\"" . $datanorgeData["id"] . "\">oppføring i data.norge.no</a> for meir informasjon (oppdateringsfrekvens, lenker etc.)</p>"));

$datanorgeDescription = (($lang == "en") ? 
    ("<p>See <a href=\"" . $datanorgeData["id"] . "\">data.norge.no-entry</a> for more information (update frequency, links etc.)</p>\n") 
    : ("<p>Sjå <a href=\"" . $datanorgeData["id"] . "\">datasett-oppføring</a> for meir informasjon (kontaktinformasjon, oppdateringsfrekvens, lenker til dokumentasjon etc.)</p>\n"));


// TODO: legg inn advarsel dersom data.norge-oppføring har lenke til distribusjonar som ikkje er datahotell-distribusjonar

// Check if entry has datahotel-distribution(s)
$hotelDatasets = array();
$datalisensUrl = "";
foreach ($datanorgeData["distribution"] as $distribution) {
    if (startsWithDatahotel($distribution["accessURL"])) {
        $stripped = str_replace("https://hotell.difi.no/?dataset=", "", $distribution["accessURL"]);
        $stripped = str_replace("http://hotell.difi.no/?dataset=", "", $stripped);
        $hotelDatasets[] = $stripped;
        $datalisensUrl = $distribution["license"];
    }
}
if (count($hotelDatasets) === 0) {
    http_response_code(400);
    die("Datanorge-entry does not have any datahotel-distribution(s).");
}

// $hotelDatasets = array("difi/elma/participants", "difi/elma/capabilities", "difi/elma/documenttypes");
// $hotelDatasets = array("vegvesen/tekn", "vegvesen/utek", "vegvesen/typg");


// Re-used strings
$description_page_no = ($lang == "en") ? "The page number returned." : "Side (page) av resultatet som er returnert.";
$description_pages_no = ($lang == "en") ? "Total number of pages." : "Tal på sider totalt.";
$description_posts_no = ($lang == "en") ? "Number of posts in total." : "Tal på postar totalt.";
$description_etag_no = ($lang == "en") ? "Timestamp for when the dataset was last modified on the data hotel. The value is a unix timestamp, either in seconds (10 digits) or milliseconds (13 digits)." : "Timestamp for når tid datasettet sist vart endra på datahotellet. Verdien er unix timestamp, enten i sekund (10 siffer) eller millisekund (13 siffer).";

$infoObject = array(
    "description" => $datanorgeDescription,
    "version" => "1.0.0",
    "title" => $datanorge_title,
    // "contact" => array(
    //     "name" => $datanorgeData["publisher"]["contactname"],
    //     "email" => $datanorgeData["publisher"]["mbox"]
    // ),
    "license" => array(
        "name" => ($lang == "en") ? "Data license" : "Data-lisens",
        "url" => $datalisensUrl
    ),
    "termsOfService" => "https://hotell.difi.no/api"
);

$serversObject = array(
    array(
        "url" => "https://hotell.difi.no"
    )
);

$componentsObject = array(
    "schemas" => array(
        "datahotelFormats" => array(
            "type" => "string",
            "enum" => array("json", "jsonp", "xml", "csv", "yaml")
        ),
        "datahotelMetadataFormats" => array(
            "type" => "string",
            "enum" => array("json", "jsonp", "xml", "yaml")
        ),
        "datasetMetadata" => array(
            "type" => "object",
            "properties" => array(
                "shortName" => array(
                    "type" => "string",
                    "description" => ($lang == "en") ? "Shortname for dataset. Same as the last part of location." : "Kortnamn for datasettet. Samme som siste ledd i location."
                ),
                "name" => array(
                    "type" => "string",
                    "description" => ($lang == "en") ? "Human readable name for the dataset" : "Menneskelesbart namn for datasettet."
                ),
                "location" => array(
                    "type" => "string",
                    "description" => ($lang == "en") ? "Unique dataset-id in the data hotel" : "Unik datasett-id i datahotellet"
                ),
                "updated" => array(
                    "type" => "integer",
                    "description" => $description_etag_no
                ),
                "dataset" => array(
                    "type" => "boolean",
                    "description" => ($lang == "en") ? "True if the location is a dataset. False if not. If false, location may be pointing to a folder containing datasets as subfolders." : "True dersom location er eit datasett, false dersom ikkje. Ved false kan location vere ein katalog som inneheld datasett."
                )
            )
        ),
        "datasetFields" => array(
            "type" => "array",
            "items" => array(
                "type" => "object",
                "properties" => array(
                    "name" => array(
                        "type" => "string",
                        "description" => ($lang == "en") ? "Human readable name of field" : "Menneskelesbart namn på feltet."
                    ),
                    "shortName" => array(
                        "type" => "string",
                        "description" => ($lang == "en") ? "Short name for field. Same as used in response and heading in CSV-file (when downloading entire dataset)." : "Kortnamn for felt. Samme som feltnamn brukt i respons og overskriftsrad i CSV-fila."                        
                    ),
                    "groupable" => array(
                        "type" => "boolean",
                        "description" => ($lang == "en") ? "Can group by/filter on this field. E.g. \"?field-shortName=value\"" : "Kan gruppere/filtrere på dette feltet via query-parameter. E.g. «?shortName=verdi»."
                    ),
                    "searchable" => array(
                        "type" => "boolean",
                        "description" => ($lang == "en") ? "Wether this field is searchable via query-parameter. E.g. \"?query=search string\"" : "Om dette feltet er søkbart via query-parameteret. E.g. «?query=søkestreng»"
                    ),
                    "indexPrimaryKey" => array(
                        "type" => "boolean",
                        "description" => ($lang == "en") ? "Not in use." : "Ikkje i bruk."
                    )
                )
            )
        )
    ),
    "headers" => array(
        "X-Datahotel-Page" => array(
            "description" => $description_page_no,
            "schema" => array(
                "type" => "integer"
            )
        ),
        "X-Datahotel-Total-Pages" => array(
            "description" => $description_pages_no,
            "schema" => array(
                "type" => "integer"
            )
        ),
        "X-Datahotel-Total-Posts" => array(
            "description" => $description_posts_no,
            "schema" => array(
                "type" => "integer"
            )
        ),
        "ETag" => array(
            "description" => (($lang == "en") ? "Works like standard HTTP ETag. " : "Fungerer som standard HTTP ETag. ") . $description_etag_no,
            "schema" => array(
                "type" => "integer"
            )
        )        
    )
);

$tagsObject = array();

$pathsObject = array();

foreach ($hotelDatasets as $hotelDataset) {
    $datasetId = generateDatasetId($hotelDataset);

    /*
    Eksempel:
    [shortName] => tekn
    [name] => Kjøretøy - enkeltgodkjente
    [location] => vegvesen/tekn
    [updated] => 1554101264
    [dataset] => 1
    */
    $meta_raw = getHTTPCached("https://hotell.difi.no/api/json/" . $hotelDataset . "/meta");
    $meta = json_decode($meta_raw["content"], true);
    // print_r($meta);
    // die();

    $tag = $meta["shortName"];
    $tagsObject[] = array(
        "name" => $tag, 
        "description" => $meta["name"], 
        "externalDocs" => array(
            "url" => "https://hotell.difi.no/?dataset=" . $hotelDataset
            // "description" => "Sjå:"
            )
        );

    /*
    Eksempel:
        [name] => NOX-utslipp
        [shortName] => nox_utslipp
        [groupable] => 
        [searchable] => 
        [indexPrimaryKey] => 
        [description] => Oppgis i g/km
    */    
    $fields = getFields($hotelDataset);

    $data_raw = getHTTPCached("https://hotell.difi.no/api/json/" . $hotelDataset);
    $dataSamples = json_decode($data_raw["content"], true);
    $dataSample = $dataSamples["entries"][0];

    // Create schema for single row
    $recordSchemaProperties = array("type" => "object");
    foreach ($fields as $field) {
        $shortName = $field["shortName"];
        $column = array("type" => "string");
        $column["example"] = $dataSample[$shortName];
        $description = "";
        if (strtolower($field["name"]) !== strtolower($field["shortName"])) {
            $description .= $field["name"];
        }
        if (array_key_exists("description", $field)) {
            if (!empty($description)) $description .= ".\n";
            $description .= $field["description"];
        }
        if (!empty($description)) $column["description"] = $description;
        $recordSchemaProperties["properties"][$shortName] = $column;
    }
    $componentsObject["schemas"][$datasetId . "-record"] = $recordSchemaProperties;

    $recordSchema = array(
        "type" => "object",
        "properties" => array(
            "entries" => array(
                "type" => "array",
                "items" => array(
                    "\$ref" => "#/components/schemas/" . $datasetId . "-record"
                )
            ),
            "page" => array(
                "type" => "integer",
                "description" => $description_page_no
            ),
            "pages" => array(
                "type" => "integer",
                "description" => $description_pages_no
            ),
            "posts" => array(
                "type" => "integer",
                "description" => $description_posts_no
            )
        )
    );
    $responseSchemaKey = $datasetId . "-response";
    $componentsObject["schemas"][$responseSchemaKey] = $recordSchema;

    $groupableParameters = array();
    $searchableFields = array();
    foreach ($fields as $field) {
        if ($field["searchable"]) $searchableFields[] = $field["shortName"];
        if ($field["groupable"]) {
            $groupableParameters[] = array(
                "name" => $field["shortName"],
                "in" => "query",
                "description" => ($lang == "en") ? "Filter result by exact match on this field." : "Filtrer søkeresultat på eksakt match på dette feltet.",
                "required" => false,
                "schema" => array(
                    "type" => "string"
                )                    
            );
        }
    }
    $queryParameter = array();
    if (count($searchableFields) !== 0) {
        $queryParameter = array(
            array(
            "name" => "query",
            "in" => "query",
            "description" => (($lang == "en") ? "Search in the dataset. Searches in fields: " : "Søk i datasettet. Gir treff på felt: ") 
                . implode(", ", $searchableFields), // TODO: få med muleghet for stjerneteikn + problem med spesialteikn
            "required" => false,
            "schema" => array(
                "type" => "string"
            )
            )
        );
    }

    $pageParameter = array(
        array(
            "name" => "page",
            "in" => "query",
            "description" => (($lang == "en") ? "Specify page of results to return. Count starts at 1." : "Spesifiser side i resultatet å hente ut. Standardverdi: 1."),
            "required" => false
        ));

    $pathsObject["/api/{format}/$hotelDataset"] = 
        array(          
            "get" => array(
                "tags" => array($tag),
                // "summary" => "Hent ut data her",
                "description" => ($lang == "en") ? "Fetch one page of data. Up to 100 posts pr. page.": "Hent ut ei side (page) med data. Inntil 100 postar pr. side.",
                "operationId" => generateOperationId($hotelDataset, "get", "Page"),
                "parameters" => array_merge(
                    array(
                        array(
                            "name" => "format",
                            "in" => "path",
                            "description" => ($lang == "en") ? "Choose format" : "Velg format",
                            "required" => true,
                            "schema" => array("\$ref" => "#/components/schemas/datahotelFormats")
                        )                                        
                    ),
                    $pageParameter,
                    $queryParameter,
                    $groupableParameters
                ),
                "responses" => array(
                    "200" => array(
                        "description" => ($lang == "en") ? "One page of data. Up to 100 posts." : "Ei side (page) med data. Inntil 100 rader",
                        "content" => array(
                            "application/json" => array(
                                "schema" => array("\$ref" => "#/components/schemas/" . $datasetId . "-response")
                            )
                        ),
                        "headers" => array(
                            "ETag" => array(
                                "\$ref" => "#/components/headers/ETag"
                            ),                            
                            "X-Datahotel-Page" => array(
                                "\$ref" => "#/components/headers/X-Datahotel-Page"
                            ),
                            "X-Datahotel-Total-Pages" => array(
                                "\$ref" => "#/components/headers/X-Datahotel-Total-Pages"
                            ),
                            "X-Datahotel-Total-Posts" => array(
                                "\$ref" => "#/components/headers/X-Datahotel-Total-Posts"
                            )                                                        
                        )
                    )
                )
            )
        );

    $pathsObject["/download/$hotelDataset"] = array(
        "get" => array(
            "summary" => ($lang == "en") ? "Download entire dataset as CSV." : "Last ned heile datasettet som CSV",
            "description" => ($lang == "en") ? "Character-encoding is UTF8. If the file is to be opened in Excel or similar, use parameter ?download. For all other uses, downloading without the ?download-parameter is probably more suitable." : "Teiknsett er UTF8. Dersom fila skal opnast i Excel eller liknande, bruk parameter ?download. For alle andre bruksområde vil nedlasting utan ?download-parameteret passe betre.",
            "tags" => array($tag),
            "operationId" => generateOperationId($hotelDataset, "download"),            
            "parameters" => array(
                array(
                    "name" => "download",
                    "in" => "query",
                    "description" => ($lang == "en") ? "If the parameter is given, you will receive a file with UTF8 _with_ BOM. This is suitable for opening the file in Excel or similar software." : "Dersom parameter er angitt, så får ein fil med UTF8 _med_ BOM i staden for UTF8. Denne varianter egnar seg dersom ein vil opne fila i Excel eller liknande",
                    "required" => false,
                    "schema" => array(
                        "type" => "string"
                    )
                )
            ),
            "responses" => array(
                "200" => array(
                    "description" => ($lang == "en") ? "Download entire dataset as CSV" : "Last ned heile datasettet som CSV",
                    "content" => array(
                        "text/csv" => array(
                            "schema" => array(
                                "type" => "string"
                            )
                        ),
                        "application~/vnd.ms-excel~" => array(
                            "schema" => array(
                                "type" => "string"
                            )
                        )    
                    ),
                    "headers" => array(
                        "ETag" => array(
                            "\$ref" => "#/components/headers/ETag"
                        )
                    )                    
                )
            )
        )
    );

    $pathsObject["/api/{format}/$hotelDataset/meta"] = array(
        "get" => array(
            // "summary" => "Metadata",
            "description" => ($lang == "en") ? "Fetch metadata for dataset" : "Hent ut metadata for datasettet. ",
            "tags" => array($tag),
            "operationId" => generateOperationId($hotelDataset, "get", "Metadata"),
            "parameters" => array(
                array(
                    "name" => "format",
                    "in" => "path",
                    "description" => ($lang == "en") ? "Choose format" : "Velg format",
                    "required" => true,
                    "schema" => array("\$ref" => "#/components/schemas/datahotelMetadataFormats")
                )
            ),
            "responses" => array(
                "200" => array(
                    "description" => "Metadata",
                    "content" => array(
                        "application/json" => array(
                            "example" => json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                            "schema" => array(
                                "\$ref" => "#/components/schemas/datasetMetadata"
                            )
                        ) 
                    ),
                    "headers" => array(
                        "ETag" => array(
                            "\$ref" => "#/components/headers/ETag"
                        )
                    )                    
                )
            )
        )
    );    

    $pathsObject["/api/{format}/$hotelDataset/fields"] = array(
        "get" => array(
            // "summary" => "Metadata",
            "description" => ($lang == "en") ? "Field definitions for dataset." : "Hent ut feltdefinisjonar for datasettet. ",
            "tags" => array($tag),
            "operationId" => generateOperationId($hotelDataset, "get", "Fields"),
            "parameters" => array(
                array(
                    "name" => "format",
                    "in" => "path",
                    "description" => ($lang == "en") ? "Choose format" : "Velg format",
                    "required" => true,
                    "schema" => array("\$ref" => "#/components/schemas/datahotelMetadataFormats")
                )
            ),
            "responses" => array(
                "200" => array(
                    "description" => ($lang == "en") ? "Field definitions" : "Feltdefinisjoner",
                    "content" => array(
                        "application/json" => array(
                            "example" => json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                            "schema" => array(
                                "\$ref" => "#/components/schemas/datasetFields"
                            )
                        ) 
                    ),
                    "headers" => array(
                        "ETag" => array(
                            "\$ref" => "#/components/headers/ETag"
                        )
                    )                    
                )
            )
        )
    );  
}


$openapiRoot = array(
    "openapi" => "3.0.0",
    "info" => $infoObject,
    "servers" => $serversObject,
    "paths" => $pathsObject,
    "components" => $componentsObject,
    "tags" => $tagsObject,
    "externalDocs" => array(
        "description" => ($lang == "en") ? "More documentation for hotell.difi.no (in norwegian)" : "Meir dokumentasjon for hotell.difi.no",
        "url" => "https://hotell.difi.no/api"
    )
);

$json = json_encode($openapiRoot, JSON_PRETTY_PRINT);
print($json);

file_put_contents("specs/$node.json", $json);

?>