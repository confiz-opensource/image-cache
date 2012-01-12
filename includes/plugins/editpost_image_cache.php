<?php

global $vbulletin;
require_once(dirname(__FILE__) . "/image_lib.php");

$fp = fopen("/tmp/picture_uploading.log", 'a+');
fwrite($fp, date("Y-m-d H:i:s") . "Picture uploading\n");

$host = $vbulletin->options['host_name'];
$num_matches = preg_match_all('/\[img\](.*)\[\/img\]/i', $edit['message'], $image_urls, PREG_PATTERN_ORDER);
fwrite($fp, date("Y-m-d H:i:s") . " Image count: " . $num_matches . "\n");

if ($num_matches > 0) {
    foreach ($image_urls[1] as $image_url) {
        //if url is of the same server then skip it. we have already downloaded it.
        if (strpos($image_url, $host) === false) {

            // initialize the class
            $image = new ImageLib;
            $image->DoCacheImages($image_url, &$edit['message'], $threadinfo['lastpostid'], false, null, $fp);
        }
    }
}

fwrite($fp, "END \n");
fclose($fp);
?>
