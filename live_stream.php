<?php

if(isset($_GET['video'])){
$ffmpeg_log_file = "/tmp/videos/log.".crc32($_GET['video']).".txt";
?>
<style type="text/css">
    body { margin:0;background-color:rgb(23,24,27);color:white;}
	a:link, a:visited{color:white;}
	video{width:100%;}
</style>
<video controls>
<!--
    <source src="<?= $_GET['video'] ?>" type="video/mp4">
    Sorry, your browser doesn't support embedded videos.
-->
</video>
<script>
//document.getElementsByTagName('source')[0].addEventListener('error', function(event) {console.error(event)}, true);
var videoElement = document.getElementsByTagName('video')[0];
videoElement.onerror = function() {
  console.log("Error " + videoElement.error.code + "; details: " + videoElement.error.message, videoElement.error);
  alert("Error " + videoElement.error.code)
}
videoElement.src = "<?= $_GET['video'] ?>";
</script>
<?= file_exists($ffmpeg_log_file)?file_get_contents($ffmpeg_log_file):'' ?>


<?php
	exit;
}


$device = '';
if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== FALSE){
	$device = 'iPad';
}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Macintosh;') !== FALSE){
	$device = 'Mac';
}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone;') !== FALSE){
	$device = 'iPhone';
}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Windows') !== FALSE){
	$device = 'Windows';
}elseif(strpos($_SERVER['HTTP_USER_AGENT'], ' VR') !== FALSE){
	$device = 'Android';//Mabye we should just use VR as a device
}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== FALSE){
	$device = 'Android';
}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'VLC') !== FALSE){
	$device = 'VLC';
}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'CrKey') !== FALSE){
	$device = 'Chromecast';
}
$browser = '';
if(strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== FALSE){
	$browser = 'Firefox';
}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE){
	$browser = 'Chrome';
}elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== FALSE){
	$browser = 'Safari';
}

if(!isset($_GET['f'])){
	exit;
}
$force_hresolution = false;
if(isset($_GET['hres'])){
	$force_hresolution = intval($_GET['hres']);
}

$force_bitrate = false;
if(isset($_GET['bitrate'])){
	$force_bitrate = intval($_GET['bitrate']);

}

$TRY_CUDA = true;

$public_link = rawurldecode($_GET['f']);
if(strpos($public_link, '../') !== FALSE){
	exit;//exit if there is shinanigans
}

$ext = pathinfo($public_link, PATHINFO_EXTENSION);

$full_path = $_SERVER["DOCUMENT_ROOT"].$public_link;
$stat = stat($full_path);
if(!$stat){
	exit;//Exit if it not a real file
}
$public_link = implode("/", array_map("rawurlencode", explode("/", $public_link)));

$stream_history = array();
$stream_history_file = '/tmp/stream_history.json';
if(!file_exists($stream_history_file)){
	file_put_contents($stream_history_file, json_encode($stream_history));
}
$stream_history = json_decode(file_get_contents($stream_history_file), true);

$stream_history[$public_link] = date("Y-m-d H:i:s");
file_put_contents($stream_history_file, json_encode($stream_history));

$log = "file: <a href=\"$public_link\">".substr(strrchr($public_link, "/"), 1)."</a>\n";

//if file is bigger than 5GB we dont convert. We dont have enogh memmory to store such big files
if($stat['size'] > 5000000000){//FIXME: we dont actually need to use vlc if the file is already compatiple with the browser
	$log .= "file is to large sending directly to vlc\n";
	use_vlc();
}

$codec_map = array(
	'ac3' => 'ac-3',
	'eac3' => 'ec-3',
	'aac' => 'mp4a',
	'hevc' => 'hvc1',
	'h265' => 'hvc1',
	'h264' => 'avc1',
);

$ffprobe_dat = shell_exec('ffprobe '.escapeshellarg($full_path).' 2>&1');
list($header, $info) = explode($full_path, $ffprobe_dat);

$useNvenc = false;
$useCuvid = false;
if($TRY_CUDA){
	if(strpos($header, 'cuvid') !== FALSE){
		$useCuvid = true;
	}
	if(strpos($header, 'nvenc') !== FALSE){
		$useNvenc = true;
	}
}

//Extract bitrate and duration
$info_parts = explode('Duration: ', $info);
$lines = explode("\n", $info_parts[1]);
$container_info_raw = explode(', ', $lines[0]);

//Exctract codec
$stream_data = array(
	'ext' => $ext,
	'v_codec' => false,
	'a_codec' => false,
	'duration' => $container_info_raw[0],
	'duration_s' => strtotime('1970-01-01 '.$container_info_raw[0].' UTC'),//s
	'bitrate_kb' => intval(preg_replace('/\D/', '', array_pop($container_info_raw))),//kb/s
	'size_mb' => $stat['size']/1000000,
	'device' => $device,
	'browser' => $browser
);
if(strpos($info, 'h264') !== FALSE){
	$stream_data['v_codec'] = 'h264';
}elseif(strpos($info, 'h265') !== FALSE ||
	strpos($info, 'hevc') !== FALSE){
	$stream_data['v_codec'] = 'h265';
}elseif(strpos($info, 'mpeg4') !== FALSE){
	$stream_data['v_codec'] = 'mpeg4';
}
if(strpos($info, 'aac') !== FALSE){
	$stream_data['a_codec'] = 'aac';
}elseif(strpos($info, 'mp3') !== FALSE){
	$stream_data['a_codec'] = 'mp3';
}elseif(strpos($info, 'eac3') !== FALSE){
	$stream_data['a_codec'] = 'eac3';
}elseif(strpos($info, 'ac3') !== FALSE){
	$stream_data['a_codec'] = 'ac3';
}


//We just want to watch this, down scale to something acceptable and compress with x264
if(isset($_GET['min'])){
	//with smaler bitrates  Nvenc does not do a very good job
	$useNvenc = false;

	//A 480 stream leads to about 380kb/s with x264
	$force_hresolution = 480;
}

$stream_data['force_bitrate'] = $force_bitrate;
$stream_data['force_hresolution'] = $force_hresolution;

//var_dump($info, $stream_data);
//exit;

$acceptable = array(
	'video' => false,
	'audio' => false,
	'container' => false,
);

if($ext == 'mp4'){
	$acceptable['container'] = true;
}

if($stream_data['a_codec']){
	$acceptable['audio'] = true;
}
$client_decoders = array('h264');//everyone can decode h264

if($device != 'iPad' && $device != 'iPad'){//old IOS devices cant decode h265 properly
	$client_decoders[] = 'h265';
}

if(in_array($stream_data['v_codec'], $client_decoders)){
	$acceptable['video'] = true;
}

//We have to reencode if we want to set a new bitrate
if($force_bitrate || $force_hresolution){
	$acceptable['video'] = false;
}

//VR cant play ac3 sound mabye not eac3 sound to
//Android cant play eac3 audio OR cerain android devices cant.... 2022-02 disabling this temporarily
if($device == 'Android' && $stream_data['a_codec'] == 'eac3' && false){
	$acceptable['audio'] = false;
}
//chromecast:
//	$acceptable['video'] = false;

$log .= "<pre>\n".json_encode($stream_data, JSON_PRETTY_PRINT)."</pre>\n";

$logfile = "/tmp/videos/log.".crc32($public_link).".txt";

if($acceptable['container'] && $acceptable['audio'] && $acceptable['video']){
	file_put_contents($logfile, $log);
	header('Location: live_stream.php?video='.rawurlencode($public_link));
	//echo "No need to convert anything\n";
}else{

	//On android or specifically on VR thee is no VLC so we reencode
	if($device == 'Android'){
			start_ffmpeg($full_path, !$acceptable['audio'], !$acceptable['video'], $stream_data);
	}else{
		//replacing the container is fine but everything else is essentially better of beeing decoded by vlc on the client 2021-05-03
		//Basicly remove all reencoding (FIXME)
		if($acceptable['audio'] && $acceptable['video']){
			//echo "Replace the container\n";
			start_ffmpeg($full_path, false, false, $stream_data);
		}else{
			use_vlc();
		}
	}
	/*
	if(!$acceptable['audio'] && !$acceptable['video']){
		//echo "Reencode audio and video\n";
		start_ffmpeg($full_path, true, true, $stream_data);
	}else{
		if(!$acceptable['audio']){
			//echo "Reencode the audio\n";
			start_ffmpeg($full_path, true, false, $stream_data);
		}elseif(!$acceptable['video']){
			//echo "Reencode the video\n";
			start_ffmpeg($full_path, false, true, $stream_data);
		}else{
			//echo "Replace the container\n";
			start_ffmpeg($full_path, false, false, $stream_data);
		}
	}
*/
}
function use_vlc(){
	global $device, $public_link, $full_path;
	if($device == 'iPad' || $device == 'iPhone'){
		echo('<a href="vlc://'.$_SERVER['HTTP_HOST'].$public_link.'">open '.$public_link.' in vlc</a>');
		header('Location: vlc://'.$_SERVER['HTTP_HOST'].$public_link);
	}else{
		$link = 'http://'.$_SERVER['HTTP_HOST'].$public_link;
		$EncodedLinkVideo = ($link);

		$StreamName = pathinfo($full_path, PATHINFO_FILENAME);
		$FileName = $StreamName.".m3u";
		header('Content-Type: audio/x-mpegurl');
		header('Content-Disposition: attachment; filename="'.$FileName.'"');
		echo("#EXTM3U\n\n");
		echo("#EXTINF:-1,$StreamName\n");
		echo($EncodedLinkVideo."\n");
	}
	exit;
}

function start_ffmpeg($file, $reencode_audio, $reencode_video, $stream_data){
	global $useNvenc, $useCuvid, $log, $logfile;
	$filename = pathinfo($file, PATHINFO_FILENAME);
	$outputfile = 'videos/'.$filename.'.m3u8';
	$outputfile = preg_replace('/\s/', '_', $outputfile);

	//Streaming mode is a mode where we try to convert the files as the client is digesting it(the stream ing is a bit iffy on the client side so give it a few minutes to buffer first)
	$stream = '&';
	$output = '-f segment -segment_list_flags cache -segment_list_size 0 -segment_time 60 -segment_list '.escapeshellarg('/tmp/'.$outputfile).' /tmp/videos/file%03d.ts';
	$audio_encode = '-c:a copy';
	if($reencode_audio){
		$audio_encode = '-c:a libfdk_aac';
		//chromecast has probelm with multichanel audio (but why should we ever rencode more than 2 chnnels...)
		$audio_encode .= ' -ac 2';

	}
	$video_encode = '-c:v copy';
	$extra_tag = '';
	if($reencode_video){
		$resize = '';
		if($stream_data['force_hresolution']){
			$resize = '-vf scale='.$stream_data['force_hresolution'].':-1 ';
		}
		$bt = '';
		if($stream_data['force_bitrate']){
			$bt = ' -b:v '.$stream_data['force_bitrate'].'k';
		}
		if($useNvenc){
			$video_encode = $resize.'-c:v h264_nvenc'.$bt.' -pix_fmt yuv420p';//The presets make almost no difference dont use them
		}else{
			$video_encode = $resize.'-c:v libx264'.$bt.' -pix_fmt yuv420p';//use yuv420p sometimes other pixelformats dont work so well(especialy 10bit formats)
		}
	}else{
		$extra_tag = '';
		if($stream_data['v_codec'] == 'h265'){
			//we cant live stream h265 as ffmpeg does not tag the h265 contents with -tag:v hvc1 and it cant be fixed in segmentation formating
			//So we chnage the output format since we are only copying it should go fast anyway
			$extra_tag = '-tag:v hvc1 ';//makes it not work on firefox on andorid
			$log .= "adding h265 tag hvc1 to allow apple devices to stream the file <br>\n";
			//$stream = '';
		}

	}
//chromecast
//		$stream = '';

	//If we are just changing the container we can wait until the entire procces is done it is so fast anyway
	if(!$reencode_video && !$reencode_audio){
		$stream = '';
	}
	if($stream == ''){
		$outputfile = 'videos/'.$filename.'.mp4';
		$output = escapeshellarg('/tmp/'.$outputfile);
	}
	$input_decoder = '';
	if($useCuvid){
		if($stream_data['v_codec'] == 'mpeg4'){
			$input_decoder = '-c:v mpeg4_cuvid';
		}
		if($stream_data['v_codec'] == 'h265'){
			$input_decoder = '-c:v hevc_cuvid';
		}
		if($stream_data['v_codec'] == 'h264'){
			$input_decoder = '-c:v h264_cuvid';
		}
	}

	//var_dump($stream_data, $outputfile);
	//exit;
	if(file_exists('/tmp/'.$outputfile)){
		header("Location: live_stream.php?video=".rawurlencode($outputfile));
		return;
	}

	//Clear old things
	shell_exec('killall ffmpeg');
	deleteDir('/tmp/videos');
	@mkdir('/tmp/videos');

	shell_exec('ffmpeg -y '.$input_decoder.' -i '.escapeshellarg($file).' '.$extra_tag.' '.$video_encode.' '.$audio_encode.' '.$output.' >/tmp/ffmpeg.log 2>&1 '.$stream);

	if($stream != ''){
		//Wait until the conversion has started
		$t = 0;
		while($t<40*5){
			usleep(200000);
			if(file_exists('/tmp/'.$outputfile)){
				break;
			}
			$t++;
		}
	}
	$log .= "converted file: <a href=\"$outputfile\">".substr(strrchr($outputfile, "/"), 1)."</a>\n";
	$logfile = "/tmp/videos/log.".crc32($outputfile).".txt";
	file_put_contents($logfile, $log);
	header("Location: live_stream.php?video=".rawurlencode($outputfile));
}


function deleteDir($dirPath) {
    if (! is_dir($dirPath)) {
	    return;
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}
