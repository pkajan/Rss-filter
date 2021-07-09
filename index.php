<?php

// check files for existance
$filenames = ['excluded.txt', 'rss_list.txt', 'wanted.txt'];
$allowedToRun = true;
foreach ($filenames as $file) {
    if (!file_exists($file)) {
        echo "The file $file does not exist, rename '$file.example' to '$file'</br>\n";
        $allowedToRun = false;
    }
}

if ($allowedToRun) {

    header("Content-type: text/xml");

    $urls = fopen("rss_list.txt", "r") or die("Unable to open file!");
    $urls = array_filter(explode("\n", stream_get_contents($urls))); //remove empty lines to prevent errors later

    $itemz = $title = $description = $link = "";

    foreach ($urls as $url) {
        $feeds = @simplexml_load_file(filter_var($url, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW)); //@ supress errors, but dont worry...
        if ($feeds === false) {
            // on error ignore dead link/down page/any error...
            continue;
        }

        $wanted = array();
        $myfile = fopen("wanted.txt", "r") or die("Unable to open file!");
        while (!feof($myfile)) {
            array_push($wanted, trim(fgets($myfile))); //trim removes empty lines
        }
        fclose($myfile);
        $wanted = array_filter($wanted);

        $excluded = array();
        $myfile = fopen("excluded.txt", "r") or die("Unable to open file!");
        while (!feof($myfile)) {
            array_push($excluded, trim(fgets($myfile))); //trim removes empty lines
        }
        fclose($myfile);
        $excluded = array_filter($excluded);

        $title .= $feeds->channel->title . " | ";
        $description .= $feeds->channel->description . " | ";
        $link .= $feeds->channel->link . " | ";
        foreach ($feeds->channel->item as $items) {
            $count_name = 0;
            str_replace($wanted, '', $items->title, $count_name); //search wanted
            str_replace($excluded, '', $items->title, $count_exclude); //excluded test
            if ($count_exclude > 0) {
                continue;
            }
            //skip excluded
            if ($count_name > 0) { //if found name in array write output
                $itemz .= "<item>";
                foreach ($items as $key => $value) {
                    $itemz .= "<$key>" . htmlspecialchars($value) . "</$key>\n";
                }
                $itemz .= "</item>\n";
            }
        }
    }

    $rssString = "<rss version=\"2.0\">\n
    <channel>\n
    <title>$title</title>\n
    <description>$description</description>\n
    <link>$link</link>\n
    $itemz
    </channel>\n
    </rss>\n";

    echo $rssString;

}
