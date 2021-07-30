<?php

function readRSSto($readRSS, &$addTo, &$rssNames = null) {
    $feeds = @simplexml_load_file(filter_var($readRSS, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW)); //@ supress errors, but dont worry...
    if ($feeds === false) {
        // on error ignore dead link/down page/any error...
        return;
    }
    foreach ($feeds->channel->title as $name) {
        array_push($rssNames, $name);
    }

    foreach ($feeds->channel->item as $item) {
        array_push($addTo, array(
            "title" => $item->title,
            "link" => $item->link,
            "guid" => $item->guid,
            "pubDate" => $item->pubDate,
            "description" => $item->description,
        ));
    }
}

function array_search_partial($arr, $keyword) {
    $arr = is_array($arr) ? $arr : array($arr);
    foreach ($arr as $a) {
        if (strpos((string)$keyword, (string)$a) !== false) {
            return true;
        }
    }
    return false;
}

// check files for existance
$filenames = ['excluded.txt', 'rss_list.txt', 'wanted.txt'];
$allowedToRun = true;
foreach ($filenames as $file) {
    if (!file_exists($file)) {
        echo "The file $file does not exist, rename '$file.example' to '$file'</br>\n";
        $allowedToRun = false;
    }
}

$mergedRSSData = array();
$filteredRSSData = array();
$rssNames = array();

if ($allowedToRun) {
    header("Content-type: text/xml");

    // read wanted file
    $wanted = array();
    $myfile = fopen("wanted.txt", "r") or die("Unable to open file!");
    while (!feof($myfile)) {
        array_push($wanted, trim(fgets($myfile))); //trim removes empty lines
    }
    fclose($myfile);
    $wanted = array_filter($wanted);

    // read excluded file
    $excluded = array();
    $myfile = fopen("excluded.txt", "r") or die("Unable to open file!");
    while (!feof($myfile)) {
        array_push($excluded, trim(fgets($myfile))); //trim removes empty lines
    }
    fclose($myfile);
    $excluded = array_filter($excluded);

    // read list of RSSs
    $urls = fopen("rss_list.txt", "r") or die("Unable to open file!");
    $urls = array_filter(explode("\n", stream_get_contents($urls))); //remove empty lines to prevent errors later

    // add RSS items into given array
    foreach ($urls as $item) {
        readRSSto($item, $mergedRSSData, $rssNames);
    }

    // search in this combined array
    foreach ($mergedRSSData as $item) {
        $name = (string) $item["title"];

        if (array_search_partial($wanted, $name)) { //search if title include string from "wanted" file
            if (!array_search_partial($excluded, $name)) { //search if title doesnt include string from "excluded" file
                array_push($filteredRSSData, $item); // if title is in included and mot in excluded add to second array
            }
        }
    }

    $array = json_decode(json_encode($filteredRSSData), true); //SimpleXMLElement Object to array

    usort($array, function ($a, $b) { // sort array by pubDate
        return strtotime($b["pubDate"][0]) - strtotime($a["pubDate"][0]);
    });

    //header of RSS
    echo "<rss version=\"2.0\"><channel><title>Combined RSS</title><description>Combined RSS feed: " . implode(" | ", $rssNames) . "</description>\n";


    foreach ($array as $item) {
        //item body
        $rssItemBody = null;
        $rssItemBody .= "<item>\n";
        $rssItemBody .= "<title>" . (isset($item["title"][0]) ? $item["title"][0] : "") . "</title>\n";
        $rssItemBody .= "<link>" . (isset($item["link"][0]) ? htmlspecialchars($item["link"][0]) : "") . "</link>\n";
        $rssItemBody .= "<guid>" . (string)implode(";", array_filter((array)$item["guid"][0])) . "</guid>\n";
        $rssItemBody .= "<pubDate>" . (isset($item["pubDate"][0]) ? $item["pubDate"][0] : "") . "</pubDate>\n";
        $rssItemBody .=
            isset($item["description"][0]) ?
            (is_array($item["description"][0]) ? ("<description>" . @implode(";", $item["description"][0]) . "</description>\n") : ("<description>" . $item["description"][0] . "</description>"))
            : "";
        $rssItemBody .= "</item>\n";

        echo $rssItemBody;
    }

    //footer of RSS
    echo "</channel></rss>";
}
