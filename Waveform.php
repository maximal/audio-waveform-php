<?php
/**
 *
 *
 * @author MaximAL
 * @since 2019-02-13 Added `$onePhase` parameters to get only positive waveform data and image
 * @since 2018-10-22 Added `getWaveformData()` method and `$soxCommand` configuration
 * @since 2016-11-21
 * @date 2016-11-21
 * @time 19:08
 * @link http://maximals.ru
 * @link http://sijeko.ru
 * @link https://github.com/maximal/audio-waveform-php
 * @copyright © MaximAL, Sijeko 2016-2019
 */

namespace maximal\audio;

/**
 * Waveform class allows you to get waveform data and images from audio files
 * @package maximal\audio
 */
class Waveform
{
	protected $filename;
	protected $info;
	protected $channels;
	protected $samples;
	protected $sampleRate;
	protected $duration;

	public static $linesPerPixel = 8;
	public static $samplesPerLine = 512;

	// Colors in CSS `rgba(red, green, blue, opacity)` format
	public static $color = [95, 95, 95, 0.5];
	public static $backgroundColor = [245, 245, 245, 1];
	public static $axisColor = [0, 0, 0, 0.1];

	// SoX command: 'sox', '/usr/local/bin/sox' etc
	public static $soxCommand = 'sox';


	public function __construct($filename)
	{
		$this->filename = $filename;
	}

	public function getInfo()
	{
		$out = null;
		$ret = null;
		exec(self::$soxCommand . ' --i ' . escapeshellarg($this->filename) . ' 2>&1', $out, $ret);
		$str = implode('|', $out);

		$match = null;
		if (preg_match('/Channels?\s*\:\s*(\d+)/ui', $str, $match)) {
			$this->channels = intval($match[1]);
		}

		$match = null;
		if (preg_match('/Sample\s*Rate\s*\:\s*(\d+)/ui', $str, $match)) {
			$this->sampleRate = intval($match[1]);
		}

		$match = null;
		if (preg_match('/Duration.*[^\d](\d+)\s*samples?/ui', $str, $match)) {
			$this->samples = intval($match[1]);
		}

		if ($this->samples && $this->sampleRate) {
			$this->duration = 1.0 * $this->samples / $this->sampleRate;
		}

		if ($ret !== 0) {
			throw new \Exception('Failed to get audio info.' . PHP_EOL . 'Error: ' . implode(PHP_EOL, $out) . PHP_EOL);
		}
	}

	public function getSampleRate()
	{
		if (!$this->sampleRate) {
			$this->getInfo();
		}
		return $this->sampleRate;
	}

	public function getChannels()
	{
		if (!$this->channels) {
			$this->getInfo();
		}
		return $this->channels;
	}

	public function getSamples()
	{
		if (!$this->samples) {
			$this->getInfo();
		}
		return $this->samples;
	}

	public function getDuration()
	{
		if (!$this->duration) {
			$this->getInfo();
		}
		return $this->duration;
	}

	/**
	 * Get waveform from the audio file.
	 * @param string $filename Image file name
	 * @param int $width Width of the image file in pixels
	 * @param int $height Height of the image file in pixels
	 * @param bool $onePhase `true` to get positive values only, `false` to get both phases
	 * @return bool Returns `true` on success or `false` on failure.
	 * @throws \Exception
	 */
	public function getWaveform($filename, $width, $height, $onePhase = false)
	{
		// Calculating parameters
		$needChannels = $this->getChannels() > 1 ? 2 : 1;
		$data = $this->getWaveformData($width, $onePhase);
		$lines1 = $data['lines1'];
		$lines2 = $data['lines2'];

		// Creating image
		$img = imagecreatetruecolor($width, $height);
		imagesavealpha($img, true);
		//if (function_exists('imageantialias')) {
		//	imageantialias($img, true);
		//}

		// Colors
		$back = self::rgbaToColor($img, self::$backgroundColor);
		$color = self::rgbaToColor($img, self::$color);
		$axis = self::rgbaToColor($img, self::$axisColor);
		imagefill($img, 0, 0, $back);

		// Center Ys
		if ($onePhase) {
			$center1 = $needChannels === 2 ? $height / 2 - 1: $height - 1;
			$center2 = $needChannels === 2 ? $height - 1 : null;
		} else {
			$center1 = $needChannels === 2 ? ($height / 2 - 1) / 2 : $height / 2;
			$center2 = $needChannels === 2 ? $height - $center1 : null;
		}

		// Drawing channel 1
		for ($i = 0; $i < count($lines1); $i += 2) {
			$x = $i / 2 / self::$linesPerPixel;
			if ($onePhase) {
				$max = max($lines1[$i], $lines1[$i + 1]);
				imageline($img, $x, $center1, $x, $center1 - $max * $center1, $color);
			} else {
				$min = $lines1[$i];
				$max = $lines1[$i + 1];
				imageline($img, $x, $center1 - $min * $center1, $x, $center1 - $max * $center1, $color);
			}
		}
		// Drawing channel 2
		for ($i = 0; $i < count($lines2); $i += 2) {
			$x = $i / 2 / self::$linesPerPixel;
			if ($onePhase) {
				$max = max($lines2[$i], $lines2[$i + 1]);
				imageline($img, $x, $center2, $x, $center2 - $max * $center1, $color);
			} else {
				$min = $lines2[$i];
				$max = $lines2[$i + 1];
				imageline($img, $x, $center2 - $min * $center1, $x, $center2 - $max * $center1, $color);
			}
		}

		// Axis
		imageline($img, 0, $center1, $width - 1, $center1, $axis);
		if ($center2 !== null) {
			imageline($img, 0, $center2, $width - 1, $center2, $axis);
		}

		return imagepng($img, $filename);
	}

	/**
	 * Get waveform data from the audio file.
	 * @param int $width Desired width of the image file in pixels
	 * @param bool $onePhase `true` to get positive values only, `false` to get both phases
	 * @return array
	 * @throws \Exception
	 */
	public function getWaveformData($width, $onePhase = false)
	{
		// Calculating parameters
		$needChannels = $this->getChannels() > 1 ? 2 : 1;
		$samplesPerPixel = self::$samplesPerLine * self::$linesPerPixel;
		$needRate = 1.0 * $width * $samplesPerPixel * $this->getSampleRate() / $this->getSamples();

		//if ($needRate > 4000) {
		//	$needRate = 4000;
		//}

		// Command text
		$command = self::$soxCommand . ' ' . escapeshellarg($this->filename) .
			' -c ' . $needChannels .
			' -r ' . $needRate . ' -e floating-point -t raw -';

		//var_dump($command);

		$outputs = [
			1 => ['pipe', 'w'],  // stdout
			2 => ['pipe', 'w'],  // stderr
		];
		$pipes = null;
		$proc = proc_open($command, $outputs, $pipes);
		if (!$proc) {
			throw new \Exception('Failed to run `sox` command');
		}

		$lines1 = [];
		$lines2 = [];
		while ($chunk = fread($pipes[1], 4 * $needChannels * self::$samplesPerLine)) {
			$data = unpack('f*', $chunk);
			$channel1 = [];
			$channel2 = [];
			foreach ($data as $index => $sample) {
				if ($needChannels === 2 && $index % 2 === 0) {
					$channel2 []= $sample;
				} else {
					$channel1 []= $sample;
				}
			}
			if ($onePhase) {
				// Rectifying to get positive values only
				$lines1 []= abs(min($channel1));
				$lines1 []= abs(max($channel1));
				if ($needChannels === 2) {
					$lines2 []= abs(min($channel2));
					$lines2 []= abs(max($channel2));
				}
			} else {
				// Two phases
				$lines1 []= min($channel1);
				$lines1 []= max($channel1);
				if ($needChannels === 2) {
					$lines2 []= min($channel2);
					$lines2 []= max($channel2);
				}
			}
		}

		$err = stream_get_contents($pipes[2]);
		$ret = proc_close($proc);

		if ($ret !== 0) {
			throw new \Exception('Failed to run `sox` command. Error:' . PHP_EOL . $err);
		}

		return ['lines1' => $lines1, 'lines2' => $lines2];
	}

	public static function rgbaToColor($img, $rgba)
	{
		return imagecolorallocatealpha($img, $rgba[0], $rgba[1], $rgba[2], round((1 - $rgba[3]) * 127));
	}
}
