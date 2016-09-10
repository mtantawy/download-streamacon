<?php

require_once __DIR__ . '/vendor/autoload.php';

// getting args
$args = getopt('v', ['URL:']);

if (!isset($args['URL'])) {
    die('Argument "URL" is required!'.PHP_EOL);
}
$isVerbose = isset($args['v']);
$url = trim($args['URL']);
// remove base URL if exists, and anything before it, to ensure base url is correct and with HTTPS
$baseUrlPosition = strpos($url, 'streamacon.com');
$url = str_replace('streamacon.com', '', $url);
$url = substr($url, $baseUrlPosition, strlen($url) - $baseUrlPosition);

$baseUrl = 'https://streamacon.com/';

// get page with talks
$response = getPage($baseUrl.$url);
if (false === $response) {
    die('URL: ' . $baseUrl . $url . ' responded in something other than 200 OK, check URL');
}

// Create a DOM object
$html = new simple_html_dom();
// Load HTML from a string
$html->load($response);

$pageTitle = $html->find('title', 0)->innerText();
$conTitle = sanitizeTitle(str_replace('Watch videos from ', '', $pageTitle));

// extract url of all the talks
$talks = [];
foreach ($html->find('div[class=col-md-4 text-center]') as $element) {
    $talks[] = $element->children(2)->find('a', 0)->href;
}

if ($isVerbose) {
    echo 'Found ' . count($talks) . ' talks!'.PHP_EOL;
}

// extract vimeo links
$vimeoLinks = [];
$i = 1; // counting found iframes
foreach ($talks as $talkUrl) {
    $response = getPage($baseUrl.$talkUrl);
    if (false === $response) {
        if ($isVerbose) {
            echo 'Talk whose URL is: ' . $baseUrl . $url . ' responded in something other than 200 OK, ignored' . PHP_EOL;
        }
        continue;
    }

    // Create a DOM object
    $html = new simple_html_dom();
    // Load HTML from a string
    $html->load($response);

    // checking is_object to make sure it found the iframe
    if (!is_object($iframe = $html->find('iframe', 0))) {
        if ($isVerbose) {
            echo 'Talk whose URL is: ' . $baseUrl . $url . ' had no iframe, ignored' . PHP_EOL;
        }
        continue;
    }

    if ($isVerbose) {
        echo '#' . $i++ . ' - Found Vimeo iframe for talk whose URL is: ' . $baseUrl . $talkUrl.PHP_EOL;
    }

    $vimeoLinks[$baseUrl.$talkUrl] = $iframe->src;
}

// extract video urls from vimeo
$videoUrls = [];
$i = 1; // counting found video urls
foreach ($vimeoLinks as $referer => $vimeoLink) {
    $curl = curl_init($vimeoLink);
    // need to manualy add "referer" header to make vimeo return correct response as if embedded
    curl_setopt($curl, CURLOPT_REFERER, $referer);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    // required as well to make vimeo respond correctly
    curl_setopt($curl, CURLOPT_USERAGENT, "curl");


    // Create a DOM object
    $html = new simple_html_dom();
    // Load HTML from a string
    $html->load(curl_exec($curl));

    // the script tag where the JS lies and within it the required JSON
    $javascript = $html->find('script', 0)->innerText();

    // position of t= because we want the following JSON
    $startPosition = strpos($javascript, 't=') + 2; // to get position of following "{"
    $endPosition = strpos($javascript, ';'); // to get position of closing "}"
    $JSON = substr($javascript, $startPosition, ($endPosition-$startPosition));

    // get video title then convert it to filesystem-safe string
    $title = sanitizeTitle(json_decode($JSON, true)['video']['title']);

    $qualities = json_decode($JSON, true)['request']['files']['progressive'];

    // sort by quality, highest first
    usort($qualities, function ($a, $b) {
        return $a['quality'] < $b['quality'];
    });

    if ($isVerbose) {
        echo '#' . $i++ . ' - Found Vimeo video at quality: ' . $qualities[0]['quality'] . ' for talk: ' . $title.PHP_EOL;
    }

    $videoUrls[$title] = $qualities[0]['url']; // first quality is always the highest
}

// check path
if (!is_dir('talks/' . $conTitle)) {
    mkdir('talks/' . $conTitle, 0755, true);
}

// download (or resume downloading of videos)
// using `wget` as it supports download resume, and provides nice progress bar
foreach ($videoUrls as $title => $url) {
    $command = "wget -c '$url' -O 'talks/$conTitle/$title.mp4'";
    exec($command);
}

function sanitizeTitle($title)
{
    // inspired from "sanitize_file_name" of WordPress, but modified to leave space and to not deal with Extensions

    $special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", chr(0));
    $title = str_replace($special_chars, '', $title);
    $title = str_replace(array( '%20', '+' ), '-', $title);
    $title = preg_replace('/[\r\n\t-]+/', '-', $title);
    $title = trim($title, '.-_');

    return $title;
}

// basically, http://stackoverflow.com/a/408416/5658508
function getPage($url)
{
    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

    /* Get the HTML or whatever is linked in $url. */
    $response = curl_exec($handle);

    /* Check for anything other than 200 OK. */
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    if ($httpCode != 200) {
        return false;
    }

    curl_close($handle);

    return $response;
}
