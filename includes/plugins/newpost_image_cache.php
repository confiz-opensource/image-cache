<?php

global $vbulletin;
require_once(dirname(__FILE__) . "/image_lib.php");

$fp = fopen("/tmp/picture_uploading.log", 'a+');
fwrite($fp, date("Y-m-d H:i:s") . "Picture uploading\n");

$num_matches = preg_match_all('/\[img\](.*)\[\/img\]/i', $post['message'], $image_urls, PREG_PATTERN_ORDER);
fwrite($fp, date("Y-m-d H:i:s") . " Image count: " . $num_matches . "\n");

if ($num_matches > 0) {
    foreach ($image_urls[1] as $image_url) {

        // initialize the class
        $image = new ImageLib;
        $image->DoCacheImages($image_url, &$post['message'], $threadinfo['lastpostid'], false, null, $fp);
    }
}

fwrite($fp, "END \n");
fclose($fp);
?>
