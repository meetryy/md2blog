<?php

$scriptVersion = "08.2022";

function highlight_array($array, $name = 'var')
{
	highlight_string("<?php\n\$$name =\n" . var_export($array, true) . ";\n?>");
}

function array_push_assoc($array, $key, $value)
{
	$array[$key] = $value;
	return $array;
}

function array_replace_needle($array, $needlePos, $needleLen, $replacement)
{
	//$toReturn = 0;
	$a = substr($array, 0, $needlePos);
	$b = substr($array, $needlePos + $needleLen, strlen($array) - $needleLen);
	$toReturn = $a . $replacement . $b;
	//echo "|"."<strong>"."|";
	return ($toReturn);
}

function clean($string)
{
	$string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
	return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}

function array_has_dupes($array)
{
	return count($array) !== count(array_unique($array));
}

/*
function check_url($url) {
	$headers = @get_headers( $url);
	$headers = (is_array($headers)) ? implode( "\n ", $headers) : $headers;
 
	return (bool)preg_match('#^HTTP/.*\s+[(200|301|302)]+\s#i', $headers);
 }
 */

include 'config.php';
include 'include/parsedown/Parsedown.php';

date_default_timezone_set($cfgTimeZone);

// go trough articles
$path    = '../articles';
$files = scandir($path);
$files = array_diff(scandir($path), array('.', '..'));
//var_dump($files);

// list of parameters to be saved in arrays
$arrayParamList = array("tags", "rand");

//$commonArticlesData = array('tagList', 'linkList');
$commonArticlesData = array(
	"tagList" => array(),	// unique tags in all of articles
	"linkList" => array(),	// links to articles
	"urls" => array(),		// page names
	"titleList" => array(),
);

$allArticlesData = array();

// go trough articles
foreach ($files as $file) {
	$pathToArticle = $path . "/" . $file;
	$articleFileContent = file_get_contents($pathToArticle);
	$articleFileLines = file($pathToArticle);
	$thisArticleData = array();

	$thisArticleData["generationDate"] = date($cfgDateFormat);
	$thisArticleData["generationTime"] = date($cfgTimeFormat);

	for ($line = 0; $line < count($articleFileLines); $line++) {
		//
		$thisLine = $articleFileLines[$line];
		if (!array_key_exists("text", $thisArticleData)) {
			$paramNameLen = strpos($thisLine, ": ");
			if ($paramNameLen) {
				$thisLine = str_replace(array("\r", "\n"), "", $thisLine);
				$paramName = mb_substr($thisLine, 0, $paramNameLen);
				$paramValue = mb_substr($thisLine, $paramNameLen + 2, strlen($thisLine) - $paramNameLen);
				if (in_array($paramName, $arrayParamList)) {
					$tagsNoSpace = str_replace(" ", "", $paramValue);
					$thisArticleData[$paramName] = preg_split("/\,/", $tagsNoSpace);
				} else
					$thisArticleData[$paramName] = $paramValue;
			} else {
				echo "line doesnt contain ': ', thus is not a parameter";
				die();
			}
		}
	}

	$commonArticlesData['tagList'] = array_merge($commonArticlesData['tagList'], $thisArticleData['tags']);
	$commonArticlesData['tagList'] = array_unique($commonArticlesData['tagList']);

	if (array_key_exists("skip", $thisArticleData)) break;

	if ($thisArticleData["date"] == "auto") {
		$marker = "date: auto";
		$markerPos = strpos($articleFileContent, $marker);
		if ($markerPos) {
			$r = "date: " . $thisArticleData["generationDate"] . " " . $thisArticleData["generationTime"];
			$articleFileContent =
				array_replace_needle($articleFileContent, $markerPos, strlen($marker), $r);
			file_put_contents($pathToArticle, $articleFileContent);
		}
	}

	if (!in_array($thisArticleData["url"], $commonArticlesData["urls"]))
		array_push($commonArticlesData["urls"], $thisArticleData["url"]);

	else {
		echo "url '" . $thisArticleData["url"] . "' already exist! skipping";
		break;
	}

	$thisArticleData["url"] = clean($thisArticleData["url"]);
	// check if header is complete
	$headerKeysNeeded = array("title", "date", "tags", "text");
	foreach ($headerKeysNeeded as $headerKey) {
		if (!array_key_exists($headerKey, $thisArticleData)) {
			echo "header data doesnt contain '$headerKey', thus article is empty";
			die();
		}
	}

	$thisArticleData['siteTitle'] = $cfgSiteTitle;

	$textBeginMarker = "text: ";
	$articleTextStart = strpos($articleFileContent, $textBeginMarker);
	$articleTextRaw = substr($articleFileContent, $articleTextStart + strlen($textBeginMarker));
	$thisArticleData['textRaw'] = $articleTextRaw;
	//highlight_array($articleTextRaw);

	// markdown to html
	$articleTextHtml = $articleTextRaw;
	$thisArticleData['preMarkdonwn'] = $articleTextRaw;

	$Parsedown = new Parsedown();
	$Parsedown->setSafeMode(true);
	$articleTextHtml = $Parsedown->text($articleTextRaw);
	$thisArticleData['htmlText'] = $articleTextHtml;

	$htmlFolder = "../html/";
	$pathToPage = $htmlFolder . $thisArticleData['url'] . ".html";
	$thisArticleData['fullUrl'] = $pathToPage;
	array_push($commonArticlesData['linkList'], $pathToPage);

	array_push($commonArticlesData['titleList'], $thisArticleData['title']);

	// push info to array of all articles info
	array_push($allArticlesData, $thisArticleData);

	//highlight_array($thisArticleData['titleList']);
}

// go trough all articles and create HTML blocks to insert to template
foreach ($allArticlesData as $thisArticleData) {

	$thisArticleData['urlList'] = $commonArticlesData['linkList'];
	$thisArticleData['titleList'] = $commonArticlesData['titleList'];

	// create HTML for tag block
	$thisArticleData['htmlBlockTags'] = "";
	$htmlBlocks = array();

	$templateReplacementInfo = array(
		
		'tags' => array(							// htmlBlocks['tags'], thisArticleData['tags']
			'templateMarker' => "***TAGS***",		// look for ***TAGS*** in template
			'lineMarker' => "***THISTAG***",		// look for ***TAGTEXT*** in block line template
			'lineText' => "[(***THISTAG***)]",		// block line template
			'replacementPairs' => array(
				array("tags", "***THISTAG***"),
			),
		),

		'urlList' => array(							
			'templateMarker' => "***URLS***",		
			'lineMarker' => "***THISURL***",		
			'lineText' => "<a href = \"***THISURL***\">***THISTITLE***</a> ",	
			'replacementPairs' => array(
				array("titleList", "***THISTITLE***"),
				array("urlList", "***THISURL***"),
			),
		),

		'htmlText' => array(			
			'templateMarker' => "***TEXT***",	
			'lineText' => "***TEXT***",	
		),

		'title' => array(			
			'templateMarker' => "***PGTITLE***",	
			'lineText' => "***PGTITLE***",		
		),

		'siteTitle' => array(			
			'templateMarker' => "***SITETITLE***",	
			'lineText' => "***SITETITLE***",		
		),
	);

	foreach ($templateReplacementInfo as $key => $replacementData){
		//if($templateReplacementInfo[$key]['isArray'] === true ){
		if(is_array($thisArticleData[$key])){	
			$int = 0;
			foreach ($thisArticleData[$key] as $thisListItem){
				$thisBlockLine  = $templateReplacementInfo[$key]['lineText'];
				foreach ($templateReplacementInfo[$key]['replacementPairs'] as $thisItemPair){
					//var_dump( $thisArticleData[$thisItemPair[0]]);
					$pairKey = $thisItemPair[0];
					$thisBlockLine = str_replace(	$thisItemPair[1], 
													$thisArticleData[$pairKey][$int], 
													$thisBlockLine);
					
					//echo $pairKey."<br>";
					
					echo $key." = ".$pairKey." = ".($thisArticleData[$pairKey][$int]).": ".$thisItemPair[1]." ==> ".$thisArticleData[$thisItemPair[0]][$int]."<br>";
					echo "<br>".$thisBlockLine;
					//echo $thisItemPair[1]." ==> ".$thisArticleData[$thisItemPair[0]][$int];
				}
				@$htmlBlocks[$key] .= $thisBlockLine;
				$int++;
			}

			
			/*
			foreach ($thisArticleData[$key] as $thisListItem){
				$thisBlockLine  = $templateReplacementInfo[$key]['lineText'];
				$thisBlockLine = str_replace($templateReplacementInfo[$key]['lineMarker'], $thisListItem, $thisBlockLine);
				@$htmlBlocks[$key] .= $thisBlockLine;
				//echo $thisBlockLine;
			}
			*/
			
		}

		

		else {

			$thisBlock = $templateReplacementInfo[$key]['lineText'];
			$thisBlock = str_replace($templateReplacementInfo[$key]['templateMarker'], $thisArticleData[$key], $thisBlock);
			$htmlBlocks[$key] = $thisBlock;
		}

		//highlight_array($commonArticlesData['titleList']);
	}

	// read template and replace markers with blocks
	$templateContent = file_get_contents("../templates/article.html");
	if ($templateContent) {
		foreach ($templateReplacementInfo as $key=>$replacementData) {
			$marker = $replacementData['templateMarker'];
			@$r = $htmlBlocks[$key];

			$markerPos = strpos($templateContent, $marker);

			if ($markerPos) {
				$templateContent =
					array_replace_needle($templateContent, $markerPos, strlen($marker), $r);
			}
		}
	} else
		die("../templates/article.html does not exist!");

	//highlight_array($commonArticlesData['linkList']);

	echo $thisArticleData['fullUrl'];
	if (!file_exists($thisArticleData['fullUrl']) || $cfgOverwrite) { 
		$handle = fopen($thisArticleData['fullUrl'], 'w+');
		fwrite($handle, $templateContent);
		fclose($handle);
	}

	echo "<a href=\"" . $pathToPage . "\">" . $file . "</a><br>";

	

}

//highlight_array($allArticlesData);
//highlight_array($commonArticlesData, "commonArticlesData");
