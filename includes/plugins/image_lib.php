<?php

class ImageLib {

    var $source;
    var $save_to;
    var $set_extension;
    var $quality;

    function download($method = 'curl', $timeout) {
        $info = @GetImageSize($this->source);
        $mime = $info['mime'];
		
        // What sort of image?
        $type = substr(strrchr($mime, '/'), 1);

        switch ($type) {
            case 'jpeg':
                $image_create_func = 'ImageCreateFromJPEG';
                $image_save_func = 'ImageJPEG';
                $new_image_ext = 'jpg';

                // Best Quality: 100
                $quality = isSet($this->quality) ? $this->quality : 100;
                break;

            case 'png':
                $image_create_func = 'ImageCreateFromPNG';
                $image_save_func = 'ImagePNG';
                $new_image_ext = 'png';

                $quality = isSet($this->quality) ? $this->quality : 0;
                break;

            case 'bmp':
                $image_create_func = 'ImageCreateFromBMP';
                $image_save_func = 'ImageBMP';
                $new_image_ext = 'bmp';
                break;

            case 'gif':
                $image_create_func = 'ImageCreateFromGIF';
                $image_save_func = 'ImageGIF';
                $new_image_ext = 'gif';
                break;

            case 'vnd.wap.wbmp':
                $image_create_func = 'ImageCreateFromWBMP';
                $image_save_func = 'ImageWBMP';
                $new_image_ext = 'bmp';
                break;

            case 'xbm':
                $image_create_func = 'ImageCreateFromXBM';
                $image_save_func = 'ImageXBM';
                $new_image_ext = 'xbm';
                break;

            default:
                $image_create_func = 'ImageCreateFromJPEG';
                $image_save_func = 'ImageJPEG';
                $new_image_ext = 'jpg';
        }
		
        if (isSet($this->set_extension)) {
            $ext = strrchr($this->source, ".");
            $strlen = strlen($ext);
            $new_name = basename(substr($this->source, 0, -$strlen)) . '.' . $new_image_ext;
        } else {
            $new_name = basename($this->source);
        }
		
        $save_to = $this->save_to . $new_name;
		
        if ($method == 'curl') {
            $save_image = $this->LoadImageCURL($save_to, $timeout);
        } elseif ($method == 'gd') {
            $img = $image_create_func($this->source);

            if (isSet($quality)) {
                $save_image = $image_save_func($img, $save_to, $quality);
            } else {
                $save_image = $image_save_func($img, $save_to);
            }
        }

        return $save_image;
    }

    function LoadImageCURL($save_to, $timeout) {
        $ch = curl_init($this->source);
        $fp = fopen($save_to, "wb");

        // set URL and other appropriate options
        $options = array(CURLOPT_FILE => $fp,
            CURLOPT_HEADER => 0,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_TIMEOUT => $timeout); // 1 minute timeout (should be enough)

        curl_setopt_array($ch, $options);

        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    //save post id and original url just for reference.
    //we can get forum id, thread id, topic name, user id from post id.
    //So no need to store extra information
    function GetCacheID($post_id, $image_url, $hash_key) {
        global $db;
        global $vbulletin;

        $SQL = "INSERT INTO " . $vbulletin->config['Database']['tableprefix'] . "imagecache (ID,post_id,original_url,hash_value) VALUES (0,'" . $post_id . "','" . $image_url . "','" . $hash_key . "')";
        $db->query_write($SQL);

        return $db->insert_id();
    }

    function UpdateImagePath($image_id, $image_url) {
        global $db;
        global $vbulletin;

        $SQL = "UPDATE " . $vbulletin->config['Database']['tableprefix'] . "imagecache SET image_url = '" . $image_url . "' WHERE id = '" . $image_id . "'";
        $db->query_write($SQL);
    }

    //make directory structure for local file system
    function GetDirStructure($image_id) {
        $image_path = str_pad($image_id, 4, "0", STR_PAD_LEFT);
        $pattern = '/^(\d+)(\d)(\d)(\d)$/';
        $replace = '$4/$3/$2/';
        $image_path = preg_replace($pattern, $replace, $image_path);
        return $image_path;
    }

    //rename downloaded file
    function RenameImage($image_url, $destination_path, $image_path, $image_id) {
        $filename = basename($image_url);
        $info = pathinfo($filename);
        rename($destination_path . $filename, $destination_path . $image_path . $image_id . '.' . $info['extension']);
    }
	
	//escap special chars
	function EscapeChars($image_url)
	{
		//escape special chars
        $sanitized_url = preg_replace('/\//i', '\/', $image_url);
		$sanitized_url = preg_replace('/\+/i', '\+', $sanitized_url);
		$sanitized_url = preg_replace('/\^/i', '\^', $sanitized_url);
		$sanitized_url = preg_replace('/\(/i', '\(', $sanitized_url);
		$sanitized_url = preg_replace('/\)/i', '\)', $sanitized_url);
		$sanitized_url = preg_replace('/\?/i', '\?', $sanitized_url);
		$sanitized_url = preg_replace('/\[/i', '\[', $sanitized_url);
		$sanitized_url = preg_replace('/\]/i', '\]', $sanitized_url);
		$sanitized_url = preg_replace('/\|/i', '\|', $sanitized_url);
		$sanitized_url = preg_replace('/\$/i', '\$', $sanitized_url);
		
		return $sanitized_url;
	}

    //replace post message with server url
    function ReplacePostMessage($image_url, $host, $image_path, $image_id, $message, $destination_path) {
        $despath_arr = split("/", $destination_path);
        $image_cache_dir = $despath_arr[count($despath_arr) - 2];

        $filename = basename($image_url);
        $info = pathinfo($filename);

		//escape special chars
		$sanitized_url = $this->EscapeChars($image_url);
        //$message = preg_replace('/\[img\]' . $sanitized_url . '\[\/img\]/i', '[img]' . $host . $image_cache_dir . '/' . $image_path . $image_id . '.' . $info['extension'] . '[/img]', $message, 1);
		$message = preg_replace('/\[img\]' . $sanitized_url . '\[\/img\]/i', '[img]' . $host . '/' . $image_path . $image_id . '.' . $info['extension'] . '[/img]', $message, 1);

        //update path in db
        //$this->UpdateImagePath($image_id, $host . $image_cache_dir . '/' . $image_path . $image_id . '.' . $info['extension']);

        return $message;
    }

    //remove invalid image url's
    function SkipImageTag($image_url, $message, $host) {
        /* $sanitized_url = preg_replace('/\//i', '\/', $image_url);
          $sanitized_url = preg_replace('/\?/i', '\?', $sanitized_url);
          $default_image = '[img]' . $host . 'images/404/404.jpg[/img]';
          $message = preg_replace('/\[img\]' . $sanitized_url . '\[\/img\]/i', $default_image, $message, 1);
          return $message; */
        return $message;
    }

    //get unique hash for an image file
    function GetImageHash($filepath) {
        $img_attr = getimagesize($filepath);

        //height.width.mime.bits.channel.size
        $str_for_hash = $img_attr[0] . $img_attr[1] . $img_attr['mime'] . $img_attr['bits'] . $img_attr['channels'] . filesize($filepath);
        return md5($str_for_hash);
    }

    //find in db
    function IsHashExists($hash_key) {
        global $db;
        global $vbulletin;

        $SQL = "SELECT * FROM " . $vbulletin->config['Database']['tableprefix'] . "imagecache WHERE hash_value = '" . $hash_key . "'";
        $result = $db->query_first_slave($SQL);
        return ($result['hash_value'] == null) ? (null) : ($result);
    }

    //strip html and bbcode tags
    function stripHtmltags($image_url) {
        $image_url = strip_tags($image_url);
        $pattern = '|[[\/\!]*?[^\[\]]*?]|si';
        return preg_replace($pattern, '', $image_url);
    }

    //watermark with image overlay
    function WithImage($watermark_image_path, $destination_path, $image_url, $options) {

        $filename = basename($image_url);
        $info = pathinfo($filename);
        $attachpath = $destination_path . $filename;
        $wm_b_tmp = imagecreatefrompng($watermark_image_path);
        $im_a = "";

        $extension = "." . $info['extension'];

        if (strtolower($extension) == ".gif") {
            $im_a = imagecreatefromgif($attachpath);
        } else if (strtolower($extension) == ".jpg" || strtolower($extension) == ".jpeg") {
            $im_a = imagecreatefromjpeg($attachpath);
        } else if (strtolower($extension) == ".png") {
            $im_a = imagecreatefrompng($attachpath);
        }

        if ($im_a) {

            //####### resize water mark image according to original image

            $new_width = imagesx($im_a) * 0.1802;
            $new_height = imagesy($im_a) * 0.07385;
            $width = imagesx($wm_b_tmp);
            $height = imagesy($wm_b_tmp);

            $wm_b = imagecreatetruecolor($new_width, $new_height);
            imagealphablending($wm_b, false);
            imagesavealpha($wm_b, true);
            $transparent = imagecolorallocatealpha($wm_b, 255, 255, 255, 127);
            imagefilledrectangle($wm_b, 0, 0, $new_width, $new_height, $transparent);
            imagecopyresampled($wm_b, $wm_b_tmp, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

            //###### END of resizing ##################

            $tempimage = @imagecreatetruecolor(imagesx($im_a), imagesy($im_a));
            imagecopy($tempimage, $im_a, 0, 0, 0, 0, imagesx($im_a), imagesy($im_a));
            $im_a = $tempimage;

            //adjust font size according to image size and location
            switch ($options) {
                case 1:
                    $x = 0;
                    $y = 0;
                    break;
                case 2:
                    $x = imagesx($im_a) / 2 - ($new_width / 2);
                    $y = 0;
                    break;
                case 3:
                    $x = imagesx($im_a) - $new_width - ($new_width * .05);
                    $y = 0;
                    break;
                case 4:
                    $x = 0;
                    $y = imagesy($im_a) / 2 - ($new_height * .05);
                    break;
                case 5:
                    $x = imagesx($im_a) / 2 - ($new_width / 2);
                    $y = imagesy($im_a) / 2 - ($new_height * .05);
                    break;
                case 6:
                    $x = imagesx($im_a) - $new_width - ($new_width * .05);
                    $y = imagesy($im_a) / 2 - ($new_height * .05);
                    break;
                case 7:
                    $x = 0;
                    $y = imagesy($im_a) - $new_height;
                    break;
                case 8:
                    $x = imagesx($im_a) / 2 - ($new_width / 2);
                    $y = imagesy($im_a) - $new_height;
                    break;
                case 9:
                    $x = imagesx($im_a) - $new_width - ($new_width * .05);
                    $y = imagesy($im_a) - $new_height;
                    break;
            }

            if ($wm_b && imagesx($im_a) > imagesx($wm_b)) {
                imagecopy($im_a, $wm_b, $x, $y, 0, 0, imagesx($wm_b), imagesy($wm_b));
            }

            if (strtolower($extension) == ".gif") {
                imagegif($im_a, $attachpath);
            } else if (strtolower($extension) == ".jpg" || strtolower($extension) == ".jpeg") {
                imagejpeg($im_a, $attachpath);
            } else if (strtolower($extension) == ".png") {
                imagepng($im_a, $attachpath);
            }

            if ($wm_b)
                imagedestroy($wm_b);
            imagedestroy($im_a);
        }
    }

    //watermark with text overlay
    function WithText($destination_path, $image_url, $watermarktext, $options) {

        $name = basename($image_url);
        $info = pathinfo($name);
        $extension = "." . $info['extension'];
        $filepath = $destination_path . $name;

        list($width, $height) = getimagesize($filepath);
        $image_p = imagecreatetruecolor($width, $height);

        if (strtolower($extension) == ".gif") {
            $image = imagecreatefromgif($filepath);
        } else if (strtolower($extension) == ".jpg" || strtolower($extension) == ".jpeg") {
            $image = imagecreatefromjpeg($filepath);
        } else if (strtolower($extension) == ".png") {
            $image = imagecreatefrompng($filepath);
        }

        imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width, $height);
        $color = imagecolorallocate($image_p, 100, 255, 0);
        $font = dirname(__FILE__) . '/arial.ttf';
        $font_size = 6 * $width / 200;

        //adjust font size according to image size and location
        switch ($options) {
            case 1:
                $adjust = strlen($watermarktext) / 2.3 * $font_size;
                $x = $font_size * .2;
                $y = $font_size;
                break;
            case 2:
                $adjust = strlen($watermarktext) / 2.3 * $font_size;
                $x = $width / 2 - $adjust * .55;
                $y = $font_size;
                break;
            case 3:
                $adjust = strlen($watermarktext) / 2.3 * $font_size;
                $x = $width - $font_size - $adjust * 1.5;
                $y = $font_size;
                break;
            case 4:
                $adjust = strlen($watermarktext) / 2.3 * $font_size;
                $x = $font_size * .2;
                $y = $height / 2 - $adjust * .1;
                break;
            case 5:
                $adjust = strlen($watermarktext) / 2.3 * $font_size;
                $x = $width / 2 - $adjust * .55;
                $y = $height / 2 - $adjust * .1;
                break;
            case 6:
                $adjust = strlen($watermarktext) / 2.3 * $font_size;
                $x = $width - $font_size - $adjust * 1.5;
                $y = $height / 2 - $adjust * .1;
                break;
            case 7:
                $adjust = strlen($watermarktext) / 2.3 * $font_size;
                $x = $font_size * .2;
                $y = $height - $adjust * .02;
                break;
            case 8:
                $adjust = strlen($watermarktext) / 2.3 * $font_size;
                $x = $width / 2 - $adjust * .55;
                $y = $height - $adjust * .02;
                break;
            case 9:
                $adjust = strlen($watermarktext) / 2.3 * $font_size;
                $x = $width - $font_size - $adjust * 1.5;
                $y = $height - $adjust * .02;
                break;
        }

        imagettftext($image_p, $font_size, 0, $x, $y, $color, $font, $watermarktext);

        if (strtolower($extension) == ".gif") {
            imagegif($image_p, $filepath);
        } else if (strtolower($extension) == ".jpg" || strtolower($extension) == ".jpeg") {
            imagejpeg($image_p, $filepath);
        } else if (strtolower($extension) == ".png") {
            imagepng($image_p, $filepath);
        }

        imagedestroy($image);
        imagedestroy($image_p);
    }

    //watermark the images if is it enable.
    function Watermark($is_enable, $watermark_image_path, $destination_path, $image_url, $watermark_option, $watermarktext, $watermark_place) {
        if ($is_enable == "1") {
            if ($watermark_option == "1") //watermark with image overlay
                $this->WithImage($watermark_image_path, $destination_path, $image_url, $watermark_place);
            else //watermark with text overlay
                $this->WithText($destination_path, $image_url, $watermarktext, $watermark_place);
        }
    }

    /*     * ********************************* Image Resize ************************ */

    var $image;
    var $image_type;

    function load($filename) {
        $image_info = getimagesize($filename);
        $this->image_type = $image_info[2];
        if ($this->image_type == IMAGETYPE_JPEG) {
            $this->image = imagecreatefromjpeg($filename);
        } elseif ($this->image_type == IMAGETYPE_GIF) {
            $this->image = imagecreatefromgif($filename);
        } elseif ($this->image_type == IMAGETYPE_PNG) {
            $this->image = imagecreatefrompng($filename);
        }
    }

    function save($filename, $image_type=IMAGETYPE_JPEG, $compression=75, $permissions=null) {
        if ($image_type == IMAGETYPE_JPEG) {
            imagejpeg($this->image, $filename, $compression);
        } elseif ($image_type == IMAGETYPE_GIF) {
            imagegif($this->image, $filename);
        } elseif ($image_type == IMAGETYPE_PNG) {
            imagepng($this->image, $filename);
        }
        if ($permissions != null) {
            chmod($filename, $permissions);
        }
    }

    function output($image_type=IMAGETYPE_JPEG) {
        if ($image_type == IMAGETYPE_JPEG) {
            imagejpeg($this->image);
        } elseif ($image_type == IMAGETYPE_GIF) {
            imagegif($this->image);
        } elseif ($image_type == IMAGETYPE_PNG) {
            imagepng($this->image);
        }
    }

    function getWidth() {
        return imagesx($this->image);
    }

    function getHeight() {
        return imagesy($this->image);
    }

    function resizeToHeight($height) {
        $ratio = $height / $this->getHeight();
        $width = $this->getWidth() * $ratio;
        $this->resize($width, $height);
    }

    function resizeToWidth($width) {
        $ratio = $width / $this->getWidth();
        $height = $this->getheight() * $ratio;
        $this->resize($width, $height);
    }

    function scale($scale) {
        $width = $this->getWidth() * $scale / 100;
        $height = $this->getheight() * $scale / 100;
        $this->resize($width, $height);
    }

    function resize($width, $height) {

        $do_resize = false;

        if ($this->getWidth() > $width) {
            $ratio = $width / $this->getWidth();
            $height = $this->getheight() * $ratio;
            $do_resize = true;
        } else if ($this->getHeight() > $height) {
            $ratio = $height / $this->getHeight();
            $width = $this->getWidth() * $ratio;
            $do_resize = true;
        }

        if ($do_resize) {
            $new_image = imagecreatetruecolor($width, $height);
            imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
            $this->image = $new_image;
        }
    }

    /*     * ********************************* Image Resize End ************************ */

    function DoImageResizing($destination_path, $image_url, $width, $height, $is_enabled) {
        if ($is_enabled == "1") {
            $name = basename($image_url);
            $filepath = $destination_path . $name;

            //resize image
            $this->load($filepath);
            $this->resize($width, $height);
            $this->save($filepath);
        }
    }

    /*     * **************************** URL sanitization function ********************************************** */

    function DoCacheImages($image_url, &$post_message, $lastpostid, $is_old_url, $postid, $fp) {

        global $db;
        global $vbulletin;

        //strip invalid html or bbcode tags
        $strip_tags = $this->stripHtmltags($image_url);
        if ($strip_tags != image_url) { //some tags are skip so update post message for it
            $post_message = str_replace($image_url, $strip_tags, $post_message);
            $image_url = $strip_tags; //update url
        }
		
        $destination_path = $vbulletin->options['destination_path'];
        $this->source = $image_url;
        $this->save_to = $destination_path;
		
        $get = $this->download('gd', $vbulletin->options['timeout']);

        //image successfully downloaded?
        if ($get) {
            fwrite($fp, date("Y-m-d H:i:s") . " image downloaded \n");

            $hash_key = $this->GetImageHash($destination_path . basename($image_url));
            $result = $this->IsHashExists($hash_key); //check for duplicates
			
			fwrite($fp, date("Y-m-d H:i:s") . " hash key: ".$hash_key." \n");
			
            //file already exists on server. update IMG tag for it.
            if ($result != null) {
                fwrite($fp, date("Y-m-d H:i:s") . " duplicate, replacing with already uploaded image \n");
				fwrite($fp, date("Y-m-d H:i:s") . " ID: " . $result['ID'] . ", hash key: ".$result['hash_value']." \n");

				$info = pathinfo($result['original_url']);
				$despath_arr = split("/", $destination_path);
				$image_cache_dir = $despath_arr[count($despath_arr) - 2];
                //$old_url = $vbulletin->options['host_name'] .$image_cache_dir."/". $this->GetDirStructure($result['ID']) . $result['ID'] . ".".$info['extension'];
				$old_url = $vbulletin->options['host_name'] ."/". $this->GetDirStructure($result['ID']) . $result['ID'] . ".".$info['extension'];

                fwrite($fp, date("Y-m-d H:i:s") . " old url: " . $old_url . " \n");

                $sanitized_url = $this->EscapeChars($image_url);
                $post_message = preg_replace('/\[img\]' . $sanitized_url . '\[\/img\]/i', '[img]' . $old_url . '[/img]', $post_message, 1);
                unlink($destination_path . basename($image_url));

                if ($is_old_url == true) {
                    //Update post data in table
                    fwrite($fp, date("Y-m-d H:i:s") . " post message updated in db \n");
                    $db->query_write("UPDATE " . TABLE_PREFIX . "post set pagetext = '" . $post_message . "' WHERE postid = '" . $postid . "'");
                }
            } else {

                $image_id = $this->GetCacheID($lastpostid + 1, $image_url, $hash_key); // get unique image id
                fwrite($fp, date("Y-m-d H:i:s") . " get last threadinfo for lastpostid \n");

                //create directory structure
                $image_path = $this->GetDirStructure($image_id);

                //check if the directory structure already exist?
                if (is_dir($destination_path . $image_path) == null || is_dir($destination_path . $image_path) == false)
                    mkdir($destination_path . $image_path, 0700, true); //create dir and give access rights

                    fwrite($fp, date("Y-m-d H:i:s") . " directory created: " . $destination_path . $image_path . " \n");

                //resize image
                $this->DoImageResizing($destination_path, $image_url, $vbulletin->options['image_width'], $vbulletin->options['image_height'], $vbulletin->options['is_resizeable']);

                fwrite($fp, date("Y-m-d H:i:s") . " resizing completed \n");

                //watermark image
                $this->Watermark($vbulletin->options['is_watermark'], $vbulletin->options['watermark_path'], $destination_path, $image_url, $vbulletin->options['watermark_type'], $vbulletin->options['watermark_text'], $vbulletin->options['watermark_placement']);

                fwrite($fp, date("Y-m-d H:i:s") . " watermark completed \n");

                //rename the uploaded file with our unique image id
                $this->RenameImage($image_url, $destination_path, $image_path, $image_id);

                fwrite($fp, date("Y-m-d H:i:s") . " uploaded file renamed \n");

                //now image(s) are saved but need to update message body with updated url.
                $post_message = $this->ReplacePostMessage($image_url, $vbulletin->options['host_name'], $image_path, $image_id, $post_message, $destination_path);

                fwrite($fp, date("Y-m-d H:i:s") . " post message updated \n");
            }
        }
        else {
            //image not downloaded or invalid url so simply skip the url and update message
            $post_message = $this->SkipImageTag($image_url, $post_message, $vbulletin->options['host_name']);
            fwrite($fp, date("Y-m-d H:i:s") . " image url: " . $image_url . " \n");
            fwrite($fp, date("Y-m-d H:i:s") . " invalid URLs skiped: " . $post_message . " \n");
        }
    }

    /*     * **************************** URL sanitization function end ********************************************** */
}

?>
