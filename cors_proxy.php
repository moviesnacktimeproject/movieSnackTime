<?php
$ok_domains = array(
	'popcorn-time.ga',
	'shows.cf'
);
$get_url = NULL;
if(isset($_GET['url'])){
	$get_url = $_GET['url'];
	$parsed_url = parse_url($get_url);
	if(!in_array($parsed_url['host'], $ok_domains)){
		exit;//exit if non approved url
	}
}else{
exit;
}

$context = array('http' => array());
$context['http']['header'] = "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6.1 Safari/605.1.15\r\n";

if(count($_POST) > 0){
	$context['http']['method'] = 'POST';
	$context['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$context['http']['content'] = http_build_query($_POST);
}
$context = stream_context_create($context);
$output = file_get_contents($get_url, false, $context);

echo($output);
