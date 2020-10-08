<?php
/*
**Paramenters**

-api-key=
API key to use, must be provided in config file or as a paramenter

-output-type=[html/json/cli]
The output format to display

Default: html

Available: html, json, cli (command line output)

-list-expired=
Show expired domains in the output

Default: true

Available: true, false

-expiration-window=
Number of days to consider in the expiration window (show domains expiring within n days)

Default: 30
*/

//Defaults
$apiKey = '';
$outputType = 'html';
$listExpired = true;
$expirationWindow = 30;

//Config file: expiring-domains-config.php
if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'expiring-domains-config.php')) {
	include __DIR__  . DIRECTORY_SEPARATOR . 'expiring-domains-config.php';
}

foreach ($argv as $ar) {
	if (preg_match('/^-api-key=/i', $ar)) {
		$apiKeyArg = explode('=', $ar);
		if (count($apiKeyArg) > 1) {
			$apiKeyArg = $apiKeyArg[1];
		} else {
			continue;
		}
		
		if ($apiKeyArg) {
			$apiKey = $apiKeyArg;
			break;
		}
	}
}

foreach ($argv as $ar) {
	if (preg_match('/^-output-type=/i', $ar)) {
		$outputTypeArg = explode('=', $ar);
		if (count($outputTypeArg) > 1) {
			$outputTypeArg = $outputTypeArg[1];
		} else {
			continue;
		}
		
		if (preg_match('/^html/i', $outputTypeArg)) {
			$outputType = 'html';
		} else if (preg_match('/^json/i', $outputTypeArg)) {
			$outputType = 'json';
		} else if (preg_match('/^cli/i', $outputTypeArg)) {
			$outputType = 'cli';
		}
		
		break;
	}
}

foreach ($argv as $ar) {
	if (preg_match('/^-list-expired=/i', $ar)) {
		$listExpiredArg = explode('=', $ar);
		if (count($listExpiredArg) > 1) {
			$listExpiredArg = $listExpiredArg[1];
		} else {
			continue;
		}
		
		if (preg_match('/^true/i', $listExpiredArg)) {
			$listExpired = true;
		} elseif (preg_match('/^false/i', $listExpiredArg)) {
			$listExpired = false;
		}
		
		break;
	}
}

foreach ($argv as $ar) {
	if (preg_match('/^-expiration-window=/i', $ar)) {
		$expirationWindowArg = explode('=', $ar);
		if (count($expirationWindowArg) > 1) {
			$expirationWindowArg = $expirationWindowArg[1];
		} else {
			continue;
		}
		
		if (preg_match('/^[0-9]+$/', $expirationWindowArg)) {
			$expirationWindow = $expirationWindowArg;
			break;
		}
	}
}

if ($apiKey == "") {
	exit('Error: Invalid API key');
}

//Functions

function apiTransaction($call) {
	# Set CURL Options
    $options = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => false,    // don't return headers
        CURLOPT_USERAGENT      => "NameSilo Domain Sync 1.3", // For use with WHMCS 6x
    );
	
	# Initialize CURL
    $ch      = curl_init($call);
	# Import CURL options array
    curl_setopt_array($ch, $options);
	# Execute and store response
    $content = curl_exec( $ch );
	# Process any CURL errors
	if (curl_error ( $ch )) {
		exit ( 'CURL Error: ' . curl_errno ( $ch ) . ' - ' . curl_error ( $ch ) );
	}
	# Log error(s)
    curl_close( $ch );
	# Process XML result
	$xml = new SimpleXMLElement($content);
	
	if ((int)$xml->reply->code !== 300 && (int)$xml->reply->code !== 200) {
		exit('API Error: ' . (string)$xml->reply->detail);
	}
	
	return $xml;
}

function getDomainList($apiCall) {
	$xml = apiTransaction($apiCall);
	
	$domainList = [];
	
	foreach ($xml->reply->domains->domain as $dom) {
		$domainList[] = (string)$dom;
	}
	
	return $domainList;
}

function getDomainExpiration($apiCall) {
	$xml = apiTransaction($apiCall);
	
	//preg_match('/domain=(.*?)(&|$)/', $apiCall, $domain);
	//$domain = $domain[1];
	
	if ((int)$xml->reply->code == 200) {
		return null;
	} else {
		//return array('domain' => $doamin, 'expiration' => (string)$xml->reply->expires);
		return (string)$xml->reply->expires;
	}
}

function outputCli($toExpire, $expired) {
	echo 'Domain' . "\t" . 'Expiration' . "\r\n";
	
	foreach ($toExpire as $dom) {
		echo $dom['domain'] . "\t" . $dom['expiration'] . "\r\n";
	}
	
	if (!is_null($expired)) {
		foreach ($expired as $dom) {
			echo $dom . "\t" . 'Expired' . "\r\n";
		}
	}
}

function outputHtml($toExpire, $expired) {
	$tableHeader = array('Domain', 'Expiration');
	$tableData = [];
	
	foreach ($toExpire as $dom) {
		$tableData[] = array($dom['domain'], $dom['expiration']);
	}
	
	if (!is_null($expired)) {
		foreach ($expired as $dom) {
			$tableData[] = array($dom, 'Expired');
		}
	}
	
	echo '<html>';
	echo '    <head>';
	echo '        <title>Domains about to expire</title>';
	echo '    </head>';
	echo '    <body>';
	echo '        <table>';
	echo '            <tr>';
	foreach ($tableHeader as $thd) {
		echo '                <th>' . $thd . '</th>';
	}
	echo '            </tr>';
	foreach ($tableData as $trd) {
		echo '            <tr>';
			foreach ($trd as $tdd) {
				echo '                <td>' . $tdd . '</td>';
			}
		echo '            </tr>';
	}
	echo '        </table>';
	echo '    </body>';
	echo '</html>';
}

function outputJson($toExpire, $expired) {
	$output = [];
	
	foreach ($toExpire as $dom) {
		$output[] = $dom;
	}
	
	if (!is_null($expired)) {
		foreach ($expired as $dom) {
			$output[] = array('domain' => $dom, 'expiration' => 'expired');
		}
	}
	
	echo json_encode($output);
}

//Processing/Main

$domainListCall = 'https://www.namesilo.com/api/listDomains?version=1&type=xml&key=#key#';
$domainExpirationCall = 'https://www.namesilo.com/api/getDomainInfo?version=1&type=xml&key=#key#&domain=#domain#';

date_default_timezone_set('UTC');

$currentTime = (int)((new Datetime())->format('U'));
$maxExpirationTime = (int)((new Datetime())->add(new DateInterval('P' . $expirationWindow . 'D'))->format('U'));

$toExpire = [];
$expired = [];

$domainList = getDomainList(str_replace('#key#', $apiKey, $domainListCall));

foreach ($domainList as $domain) {
	$domainExp = getDomainExpiration(str_replace('#domain#', $domain, str_replace('#key#', $apiKey, $domainExpirationCall)));
	
	if (is_null($domainExp)) {
		$expired[] = $domain;
	} else {
		$expiration = (int)(DateTime::createFromFormat('Y-m-d', $domainExp)->format('U'));
		
		if ($expiration >= $currentTime && $expiration <= $maxExpirationTime) {
			$toExpire[] = array('domain' => $domain, 'expiration' => $domainExp);
		} elseif ($expiration < $currentTime) {
			$expired[] = $domain;
		}
	}
}

if (!$listExpired) {
	$expired = null;
}

switch ($outputType) {
	case 'html':
		outputHtml($toExpire, $expired);
		break;
	case 'json':
		outputJson($toExpire, $expired);
		break;
	case 'cli':
		outputCli($toExpire, $expired);
		break;
}