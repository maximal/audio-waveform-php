#!/usr/bin/php
<?php
/**
 *
 *
 * Place the following code in the file `/usr/share/thumbnailers/waveform.thumbnailer`
 * ```
 * [Thumbnailer Entry]
 * Exec=/path/to/this/thumbnailer.php %i %o %sx%s
 * MimeType=audio/wave;audio/x-wav;audio/mpeg;audio/ogg
 * ```
 *
 * @author MaximAL
 * @since 2016-11-21
 * @date 2016-11-21
 * @time 19:08
 * @link http://maximals.ru
 * @link http://sijeko.ru
 * @link https://github.com/maximal/audio-waveform-php
 * @copyright © MaximAL, Sijeko 2016
 */

namespace maximal\audio;

require_once __DIR__ . '/Waveform.php';

// Default image size
$width = 512;
$height = 512;

// Audio and thumbnail files are required
if (count($argv) < 3) {
	echo 'Error: audio and thumbnail files not specified!', PHP_EOL;
	echo 'Usage: php ', $argv[0],
		'    <audio file>    <thumbnail file>    [<thumbnail size in pixels, default is ',
		$width, '×', $height, '>]',
		PHP_EOL;
	exit(1);
}

$filename = $argv[1];
$thumbnail = $argv[2];

// Parsing the size of waveform image
if (count($argv) > 3) {
	$match = null;
	if (preg_match('/(\d+)[x×](\d+)/ui', $argv[3], $match)) {
		$width = intval($match[1]);
		$height = intval($match[2]);
	} else {
		$width = $height = 0;
	}
	if ($width === 0 || $height === 0) {
		echo 'Error: «', $argv[3], '» size not valid!', PHP_EOL,
			'Use the form of «width×height». For example: 256x256, 512×256, etc.', PHP_EOL;
		exit(2);
	}
}

if (!is_file($filename)) {
	echo 'Error: file «', $filename, '» not found!', PHP_EOL;
	exit(3);
}


$waveform = new Waveform($filename);
// Some settings
//Waveform::$color = [255, 0, 0, 0.5];
//Waveform::$backgroundColor = [0, 0, 0, 0];
if (!$waveform->getWaveform($thumbnail, $width, $height)) {
	exit(4);
}


exit(0);
