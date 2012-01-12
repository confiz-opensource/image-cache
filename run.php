<?php

set_time_limit(0);//don't timeout the page

//Steps for sanitizer for old URL
//get post table total records
//make baches
//get post text
//sanitize it and update record

require_once('./global.php');
require_once(dirname(__FILE__) . "/includes/plugins/image_lib.php");

$fp = fopen("/tmp/post_logger.log", 'a+');
fwrite($fp, date("Y-m-d H:i:s") . " Starting sanitizer ... \n");
echo "Starting sanitizer ...<br />";

global $db;
global $vbulletin;

define('TABLE_PREFIX', $vbulletin->config['Database']['tableprefix']);

//get total posts
$SQL = "SELECT count(*) as total_post FROM " . TABLE_PREFIX . "post";
$result = $db->query_first_slave($SQL);
$total_posts = $result['total_post'];

fwrite($fp, date("Y-m-d H:i:s") . " Total posts: " . $total_posts . " \n");
echo "Total Posts: " . $total_posts . "<br />";

//make batches
$batch_size = 50;
$batches = intval($total_posts / $batch_size + 1);

fwrite($fp, date("Y-m-d H:i:s") . " Batches: " . $batches . " \n");
echo "Batches: " . $batches . "<br />";

//iterate over batches
for ($i = 0; $i <= $batches; $i++) {

    fwrite($fp, date("Y-m-d H:i:s") . " batch #: " . $i . " \n");
    echo "Batch # " . $i . "<br />";

    //get posts of that batch
    $SQL = "SELECT * FROM " . TABLE_PREFIX . "post order by postid DESC limit " . $batch_size . " offset " . $i * $batch_size;
    $posts = $db->query_read($SQL);

    while ($post = $db->fetch_array($posts)) {

        $num_matches = preg_match_all('/\[img\](.*)\[\/img\]/i', $post['pagetext'], $image_urls, PREG_PATTERN_ORDER);
        fwrite($fp, date("Y-m-d H:i:s") . " matches: " . $num_matches . " \n");

        if ($num_matches > 0) {
            foreach ($image_urls[1] as $image_url) {

                fwrite($fp, date("Y-m-d H:i:s") . " postid: " . $post['postid'] . " \n");
                if (strpos($image_url, $vbulletin->options['host_name']) === false) { //if image already hosted on server then skip it
                    // initialize the class
                    $image = new ImageLib;
                    $image->DoCacheImages($image_url, &$post['pagetext'], $threadinfo['lastpostid'], true, $post['postid'], $fp);

                    //Update post data in table
                    fwrite($fp, date("Y-m-d H:i:s") . " post message updated in db::: " . $post['pagetext'] . " \n");
                    $db->query_write("UPDATE " . TABLE_PREFIX . "post set pagetext = '" . $post['pagetext'] . "' WHERE postid = '" . $post['postid'] . "'");
                }
            }
        }//End of number of matches
    }//End of sanitizer loop
    sleep(10); //to avoid load over server for heavy traffic sites
}

fwrite($fp, date("Y-m-d H:i:s") . " Sanitizer completed ... \n");
fclose($fp);
echo "Sanitizer completed ...<br />";
?>
