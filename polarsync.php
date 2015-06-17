<?php

require_once "dropbox-sdk/Dropbox/autoload.php";
use \Dropbox as dbx;

$local_file_dir = '/home/tcx/';
$cookie_file = '/tmp/cook';
$dropbox_dir = "/Apps/tapiriik/";
$tz_fix_offset = '+01:00'; //Flow is timezone-ignorant

include('config.php');

/* config.php
$accessToken = 'dropbox_app_access_token';

$user_email = 'polar_email';
$user_pass = 'polar_password';

$local_file_dir = '/home/tcx/';
$cookie_file = '/tmp/cook';
$dropbox_dir = "/Apps/tapiriik/";
$tz_fix_offset = '+01:00';
*/


$weekago = time() - 7 * 24 * 60 * 60;

$end_date = date('d.m.Y');
$start_date = date('d.m.Y', $weekago);

$post_fields = 'returnUrl=https%3A%2F%2Fflow.polar.com%2F&email=' . $user_email . '&password=' . $user_pass;


echo 'Checking ' . $start_date . '-' . $end_date . PHP_EOL;

$dbxClient = new dbx\Client($accessToken, "PHP-Example/1.0");

$ch = curl_init();
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/training/getCalendarEvents?start=' . $start_date . '&end=' . $end_date);

$arr = curl_exec($ch); //get activity list

if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) { //auth needed
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/ajaxLogin');

    $arr = curl_exec($ch); //get login page

    curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/login');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

    $arr = curl_exec($ch); //post credentials

    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_URL, 'https://flow.polar.com/training/getCalendarEvents?start=' . $start_date . '&end=' . $end_date);

    $arr = curl_exec($ch); //get activity list

}

$activity_arr = json_decode($arr);

$datefile = $local_file_dir . 'lastfiled.dat'; //to store timestamp of last file we got

$last_file_date = file_get_contents($datefile);

date_default_timezone_set('UTC');
$offset = str_replace(':', '', $tz_fix_offset);
$newtz = new DateTimezone(timezone_name_from_abbr(null, $offset * 36, false));

foreach ($activity_arr as $activity) {
    if ($activity->type == 'EXERCISE') { //don't care about other data
        echo $activity->url . '... ';

        $date = $activity->datetime;
        $date = substr($date, 0, 16);

        if ($date > $last_file_date) { //skip files we already have

            $tcxzipurl = 'https://flow.polar.com' . $activity->url . '/export/tcx/true';

            echo 'fetching ' . $tcxzipurl . "... ";
            curl_setopt($ch, CURLOPT_URL, $tcxzipurl); //fetch TCX
            $tcxzip = curl_exec($ch);
            echo 'done';
            $zipfilename = tempnam('/tmp/', 'polarsync');
            file_put_contents($zipfilename, $tcxzip);

            $zip = new ZipArchive();
            if (!$zip->open($zipfilename)) echo 'ERROR';

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $tcxname = $zip->getNameIndex($i);

                $sporttype = strtr(substr($tcxname, 25), '_', '-');

                $t = date_create_from_format('Y-m-d\TH-i-s.uP', substr($tcxname, 0, 24));
                $t->setTimezone($newtz);
                $tcxname = date_format($t, 'Y-m-d\TH:i') . '_x_' . $sporttype;

                $tcx = $zip->getFromIndex($i);

                $fixedtcx = preg_replace('/\\.(...)Z/', '.\\1' . $tz_fix_offset, $tcx); //correct timezone

                if (preg_match('/<Activity Sport="Running">/', $tcx)) { //upload only Running
                    //    $tcxname = $date . '_x_Running.tcx';
                    $tcxnamewdir = $local_file_dir . $tcxname;

                    file_put_contents($tcxnamewdir, $fixedtcx); // save file locally

                    echo ' saved... Uploading...';

                    $f = fopen($tcxnamewdir, "rb"); //upload to Droopbox
                    $result = $dbxClient->uploadFile($dropbox_dir . $tcxname, dbx\WriteMode::force(), $f);
                    fclose($f);

                    echo ' uploaded... ';

                } else { //other types we just save

                    //  $tcxname = $date . '_x_Other.tcx';
                    $tcxnamewdir = $local_file_dir . $tcxname;

                    file_put_contents($tcxnamewdir, $fixedtcx); //save file locally

                    echo ' saved... ';

                }
            }


            file_put_contents($datefile, $date); // mark timestamp of last file we got

            echo PHP_EOL;

            unlink($zipfilename);

        } else { //skip files we already have
            echo 'skipped' . PHP_EOL;
        }
    }
}

