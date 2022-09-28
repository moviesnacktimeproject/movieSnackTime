<?php
include('config.php');

function get_torrent_status($infohash){

	$bytes_done = get_rtorrent_stat('d.bytes_done', $infohash);
	$tot_size = get_rtorrent_stat('d.size_bytes', $infohash);

	$ret = array(
		'bytes_done' => $bytes_done,
		'tot_size' => $tot_size,
		'percent' =>  $bytes_done/$tot_size,
		'tot_size_Mb' =>  intval($tot_size/(1024*1024))
	);
	return $ret;
}
//connect to rtorrent and ask for some data using it's XMLRPC system
function get_rtorrent_dat($stat, $infohash = '', $arg2 = ''){
	$infohash = htmlspecialchars($infohash, ENT_XML1, 'UTF-8');
	$arg2 = htmlspecialchars($arg2, ENT_XML1, 'UTF-8');
	$acutal_len = (222 + (87 - 40) - 56) + strlen($stat) + strlen($infohash) + strlen($arg2);
	$dat = str_replace(['222'], [''.$acutal_len], base64_decode('NDIxOkNPTlRFTlRfTEVOR1RIADIyMgBSRVFVRVNUX01FVEhPRABQT1NUAFJFUVVFU1RfVVJJAC9SUEMyAFFVRVJZX1NUUklORwAAQ09OVEVOVF9UWVBFAHRleHQveG1sAERPQ1VNRU5UX1VSSQAvUlBDMgBET0NVTUVOVF9ST09UAC91c3Ivc2hhcmUvbmdpbngvaHRtbABTQ0dJADEAU0VSVkVSX1BST1RPQ09MAEhUVFAvMS4xAFJFUVVFU1RfU0NIRU1FAGh0dHAAUkVNT1RFX0FERFIAMTI3LjAuMC4xAFJFTU9URV9QT1JUADM2NTI4AFNFUlZFUl9QT1JUADgwMDgAU0VSVkVSX05BTUUAbmduaXgtcnRvcnJlbnQASFRUUF9IT1NUAGxvY2FsaG9zdDo4MDA4AEhUVFBfQUNDRVBUACovKgBIVFRQX0NPTlRFTlRfVFlQRQB0ZXh0L3htbABIVFRQX1VTRVJfQUdFTlQAWG1scnBjLWMvMS4zMy4xNCBDdXJsLzcuNTguMABIVFRQX0NPTlRFTlRfTEVOR1RIADIyMgAsPD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4NCjxtZXRob2RDYWxsPg0KPG1ldGhvZE5hbWU+WFhYWFhYWFhYWFhYWFhYWDwvbWV0aG9kTmFtZT4NCjxwYXJhbXM+DQo8cGFyYW0+PHZhbHVlPjxzdHJpbmc+WVlZWVlZWVlZWVlZWVlZWVlZWVlZWVlZWVlZWVlZWVlZWVlZWVlZWTwvc3RyaW5nPjwvdmFsdWU+PC9wYXJhbT48cGFyYW0+PHZhbHVlPjxzdHJpbmc+QkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQjwvc3RyaW5nPjwvdmFsdWU+PC9wYXJhbT4NCjwvcGFyYW1zPg0KPC9tZXRob2RDYWxsPg0K'));
	$dat = str_replace(['XXXXXXXXXXXXXXXX','YYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYY','BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB'], [$stat,$infohash,$arg2], $dat);
	$sock = fsockopen('127.0.0.1', 9151);
	if(!$sock){
		return 0;
	}
	fwrite($sock, $dat);
	$ret = '';
	while (!feof($sock)) {
        $ret .= fgets($sock, 128);
    }
    fclose($sock);
	$parts = explode("\r\n\r\n", $ret);
	return $parts[1];
}

function get_rtorrent_stat($stat, $infohash){
	$ret = get_rtorrent_dat($stat, $infohash);
	$number = explode('i8>', $ret);
	$response_nr = intval($number[1]);
	return $response_nr;
}

function get_rtorrent_strs($stat, $arg1 = '', $index = NULL){

	$hashesr =  get_rtorrent_dat($stat, $arg1);
	$hparts = explode('<string>', $hashesr);
	array_shift($hparts);//shift away crap
	$ret = array();
	foreach($hparts AS $str){
		$h2 = explode('</string>', $str);
		$ret[] = $h2[0];
	}
	if(is_null($index)){
		return $ret;
	}
	return $ret[$index];
}

function get_hash_from_magnet($magnet){
	$query = parse_url($magnet, PHP_URL_QUERY);
	$parms = explode('&', $query);
	$parms2 = explode('&amp;', $query);
	if(count($parms) == count($parms2)){
		$parms = explode('&', htmlspecialchars_decode($query));
	}
	$prms = array();
	$infohash = 'notresolved';
	foreach($parms AS $prm){
		list($ky, $vl) = explode('=', $prm);
		if($ky == 'xt'){
			$infohash = strtoupper(substr($vl, 9));
			if(strlen($infohash) == 32){
				require_once("Base32.php");
				$infohash = strtoupper(bin2hex(Base32\Base32::decode($infohash)));
			}
		}
	}
	return $infohash;
}
function getDirContents($dir, &$results = array()) {
    $files = scandir($dir);

    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        } else if ($value != "." && $value != "..") {
            getDirContents($path, $results);
            $results[] = $path;
        }
    }

    return $results;
}
function get_live_stream_link($rtorrent_base_path){
	global $public_link_downloaded_folder;
	$filename = basename($rtorrent_base_path);
	if(is_file($rtorrent_base_path)){
		return('live_stream.php?f='.$public_link_downloaded_folder.rawurlencode($filename));
	}else{
		$files = array();
		$sortedfiles = array();
		$downloaded_folder_len = strlen($rtorrent_base_path);
		getDirContents($rtorrent_base_path, $files);
		foreach ($files as $file) {
			$sortedfiles[substr($file, $downloaded_folder_len)] = filemtime($file);
		}
		arsort($sortedfiles);
		$sortedfiles = array_keys($sortedfiles);
		return('live_stream.php?f='.$public_link_downloaded_folder.rawurlencode($filename.'/'.$sortedfiles[0]));
	}
}

if(isset($_GET['add_torrent'])){
	//Only allow magnet links to stop command injections in to rtorrent
	$ret = array('body' => '');
	if(substr($_GET['add_torrent'], 0, 8) == 'magnet:?'){
		//we need to extract the infohash as that is what rtorrent uses to identify torrents
		$ret['infohash'] = get_hash_from_magnet($_GET['add_torrent']);

		$infohashes = get_rtorrent_strs('download_list');

		if(!in_array($ret['infohash'], $infohashes)){
			$ret['new_torrent'] = true;
			$ret['body'] .= "<p>adding torrent to rtorrent...</p>";
			get_rtorrent_dat('load.start_verbose', '', $_GET['add_torrent']);
			sleep(4);//Give rtorrent some time to find the torrent
		}else{
			$ret['new_torrent'] = false;
		}

		$ret['name'] = get_rtorrent_strs('d.name', $ret['infohash'], 0);

		$stats = get_torrent_status($ret['infohash']);
		//torrent is done
		if($stats['percent'] == 1){
			$ret['done'] = true;
			$ret['body'] .= "<p>torrent is done, start streaming now</p>";
			$rtorrent_base_path = get_rtorrent_strs('d.base_path', $ret['infohash'], 0);
			$live_stream_link = get_live_stream_link($rtorrent_base_path);
			$ret['body'] .= '<a href="'.$live_stream_link.'">'.$ret['name'].'</a>';

		}else{
			$ret['done'] = false;
			$ret['body'] .= $ret['name']."<br>";
			$ret['body'] .= '
			<div style="width:200px;height:20px;border:1px solid white;">
				<div style="width:'.( 200*$stats['percent']).'px;height:20px;background-color:green;">
			</div>'.$stats['tot_size_Mb'].' Mb<br>';
		}
		echo(json_encode($ret));
	}
	exit;
}
if(isset($_GET['list_torrents'])){
	$infohashes = get_rtorrent_strs('download_list');

	foreach($infohashes AS $hash){
		$names = get_rtorrent_strs('d.name', $hash);
		$name = $names[0];
		echo "<p><a href=\"#link=magnet%3A%3Fxt%3Durn%3Abtih%3A".$hash."\">".$hash." (".$name.")</a></p>";

	}
	exit;
}

//Load and send the HTML
$htm = file_get_contents("static/index.html");
$static_script_start = strpos($htm,'<script name="static_backend"');
$static_script_end = strpos($htm, '000;</script>', $static_script_start)+9;
$server_js = '<script name="server_backend">'.file_get_contents('server_client.js').'</script>';
$server_htm = substr($htm, 0, $static_script_start).$server_js.substr($htm, $static_script_end);
echo($server_htm);
