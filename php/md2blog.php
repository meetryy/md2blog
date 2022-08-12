<?php


$scriptVersion = "11.08.2022";
$scriptGithubLink = "https://github.com/meetryy/md2blog";

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

$isWeb = (http_response_code() !== FALSE);
$eol = PHP_EOL;
if ($isWeb) $eol = "<br>";

include 'config.php';
include 'include/parsedown/Parsedown.php';

date_default_timezone_set($cfgTimeZone);

// go trough articles
$path    = '../articles';
$files = scandir($path);
$files = array_diff(scandir($path), array('.', '..'));
//var_dump($files);

$headerKeysNeeded = array("title", "date", "tags", "text", "template");

//$commonArticlesData = array('tagList', 'linkList');
$commonArticlesData = array(
	"tagList" => array(),	// unique tags in all of articles
	"urlList" => array(),	// links to articles
	"mdUrls" => array(),		// page names
	"titleList" => array(),	// list of all titles
);

$allArticlesData = array();

$stats = array(
	"filesInTotal" => 0,
	"filesOutTotal" => 0,
	"fileErrors" => 0,
	"filesWithErrors" => array(),
	"memoryUsedBytes"=> 0,
	"filesUpdated" => 0,
	"filesSkipped" => 0,
	"templateGenErrors" => 0,
);

$mdCutText = '[cut]::';

// to make this params useful as parts of template
$commonArticlesData['siteTitle'] = $cfgSiteTitle;
$commonArticlesData['siteTwitter'] = $cfgTwitter;
$commonArticlesData['siteEmail'] = $cfgEmail;
$commonArticlesData['siteGithub'] = $cfgGithub;

// go trough articles
foreach ($files as $file) {
	
	$stats["filesInTotal"]++;
	
	$pathToArticle = $path . "/" . $file;
	$articleFileContent = file_get_contents($pathToArticle);
	$articleFileLines = file($pathToArticle);
	$thisArticleData = array();

	$thisArticleData['fileParsedGood'] = true;
	$thisArticleData["generationDate"] = date($cfgDateFormat);
	$thisArticleData["generationTime"] = date($cfgTimeFormat);
	$thisArticleData["genInfo"] = "Page is generated ".$thisArticleData["generationDate"]." / ".$thisArticleData["generationTime"].
									" using <a href=\"".$scriptGithubLink."\">md2blog</a> (script version is ".$scriptVersion.")";

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
				echo $file.": header line doesnt contain ': ', thus is not a parameter";
				$stats["fileErrors"]++;
				array_push($stats["filesWithErrors"], $pathToArticle);
				$thisArticleData['fileParsedGood'] = false;
				break;
			}
		}
	}

	if (array_key_exists("skip", $thisArticleData)){
		$stats["filesSkipped"]++;
		$thisArticleData['fileParsedGood'] = false;
		break;
	}

	$commonArticlesData['tagList'] = array_merge($commonArticlesData['tagList'], $thisArticleData['tags']);
	$commonArticlesData['tagList'] = array_unique($commonArticlesData['tagList']);

	if ($thisArticleData["date"] == "auto") {
		$marker = "date: auto";
		$markerPos = strpos($articleFileContent, $marker);
		if ($markerPos) {
			$r = "date: " . $thisArticleData["generationDate"] . " " . $thisArticleData["generationTime"];
			//$articleFileContent =
			//	array_replace_needle($articleFileContent, $markerPos, strlen($marker), $r);

			$articleFileContent = str_replace($marker, $r, $articleFileContent);
			file_put_contents($pathToArticle, $articleFileContent);
		}
	}

	$thisArticleData["url"] = clean($thisArticleData["url"]);

	if (!in_array($thisArticleData["url"], $commonArticlesData["mdUrls"]))
		array_push($commonArticlesData["mdUrls"], $thisArticleData["url"]);

	else {
		echo $file.": url '" . $thisArticleData["url"] . "' already exist! skipping";
		$stats["fileErrors"]++;
		$stats["filesSkipped"]++;
		array_push($stats["filesWithErrors"], $pathToArticle);
		$thisArticleData['fileParsedGood'] = false;
		break;
	}

	foreach ($headerKeysNeeded as $headerKey) {
		if (!array_key_exists($headerKey, $thisArticleData)) {
			echo $file.": Error! Header data doesnt contain nessesary key '$headerKey', thus article can't be parsed!".$eol;
			$stats["fileErrors"]++;
			array_push($stats["filesWithErrors"], $pathToArticle);
			$thisArticleData['fileParsedGood'] = false;
			break;
		}
	}

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

	// correct <code></code> display and other pairs
	foreach($templatePairReplace as $thisPair){
		$thisArticleData['htmlText'] = str_replace($thisPair[0], $thisPair[1], $thisArticleData['htmlText']);
	}
	
	// find ::[cut] and create ['htmlTextBeforeCut'] for index page
	$cutPos = strpos($thisArticleData['preMarkdonwn'], $mdCutText);
	if ($cutPos !== false){
		$mdBeforeCut = substr($thisArticleData['preMarkdonwn'], 0, $cutPos);
		$articleTextHtmlBeforeCut = $Parsedown->text($mdBeforeCut);
		$thisArticleData['htmlTextBeforeCut'] = $articleTextHtml;
	}
	else {
		echo $file.": Warning! There is no cut! (".$mdCutText.") Article will look bad".$eol;
	}

	// save full 
	$htmlFolder = "../html/";
	
	$thisArticleData['htmlPageName'] = $thisArticleData['url'] . ".html";
	$thisArticleData['htmlPagePath'] = $htmlFolder.$thisArticleData['url'] . ".html";

	array_push($commonArticlesData['urlList'], $thisArticleData['htmlPagePath']);
	array_push($commonArticlesData['titleList'], $thisArticleData['title']);

	// push info to array of all articles info
	array_push($allArticlesData, $thisArticleData);

	//highlight_array($thisArticleData['titleList']);
	//echo "<a href=\"" .$pathToPage. "\">" .$file." =>".$thisArticleData['title']." (".$thisArticleData['fullUrl'].".)"."</a><br>";
}

// go trough all articles and create HTML blocks to insert to template
foreach ($allArticlesData as $thisArticleData) {
	if ($thisArticleData['fileParsedGood'] === true){
		$combinedArray = array_merge($thisArticleData, $commonArticlesData);

		// create HTML for tag block
		$thisArticleData['htmlBlockTags'] = "";
		$htmlBlocks = array();

		// generate htmlBlocks[] with keys from templateReplacementInfo and pairs
		
		foreach ($templateReplacementInfo as $key => $replacementData){
			if (is_array($combinedArray[$key])){			
				$int = 0;
				foreach ($combinedArray[$key] as $thisListItem){
					$thisBlockLine  = $templateReplacementInfo[$key]['lineText'];
					foreach ($templateReplacementInfo[$key]['replacementPairs'] as $thisItemPair){
						// TODO: check count(0) == count(all) $templateReplacementInfo[$key]['replacementPairs']
							$pairKey = $thisItemPair[0];
							if (isset($combinedArray[$pairKey]) === true){
								if (strpos($thisBlockLine, $thisItemPair[1]) !== false){
									while(strpos($thisBlockLine, $thisItemPair[1]) !== false){
									$thisBlockLine = str_replace(	$thisItemPair[1], 
																	$combinedArray[$pairKey][$int], 
																	$thisBlockLine);
									}
								}
								else{
									echo "Line '".$thisItemPair[1]."' is not in line template! ( ".htmlspecialchars($templateReplacementInfo[$key]['lineText'])." ) Skipping...".$eol; 
									//$stats["fileErrors"]++;
									$stats["templateGenErrors"]++;
									//array_push($stats["filesWithErrors"], $pathToArticle);
									break;
								}
					
							}
							else {
								echo $file.": Error parsing template! Key '".$pairKey."' is not in article or common articles data! Skipping...".$eol; 
								echo $file.": Following keys foar article are available: ".implode(', ', array_keys($thisArticleData)).$eol; 
								echo $file.": Following common keys are available: ".implode(', ', array_keys($commonArticlesData)).$eol; 
								//$stats["fileErrors"]++;
								$stats["templateGenErrors"]++;
								//array_push($stats["filesWithErrors"], $pathToArticle);

								break;
							}
						
					}
					@$htmlBlocks[$key] .= $thisBlockLine;
					$int++;
				}			
			}

			else {

				$thisBlock = $templateReplacementInfo[$key]['lineText'];
				$thisBlock = str_replace($templateReplacementInfo[$key]['templateMarker'], $combinedArray[$key], $thisBlock);
				$htmlBlocks[$key] = $thisBlock;
			}
		}

		
		// read template and replace markers with blocks
		$templateContent = file_get_contents("../templates/".$thisArticleData['template'].".html");
		if ($templateContent) {
			foreach ($templateReplacementInfo as $key=>$replacementData) {
				$marker = $replacementData['templateMarker'];
				@$r = $htmlBlocks[$key];

				if (strpos($templateContent, $marker)) {
					while(strpos($templateContent, $marker)){
						$markerPos = strpos($templateContent, $marker);
						//$templateContent =
							//array_replace(, $markerPos, strlen($marker), );
							$templateContent = str_replace($marker, $r, $templateContent);
					}
				}
				else{
					echo $file.": Marker ".$marker." is not in HTML template! Skipping...".$eol; 
					//$stats["fileErrors"]++;
					$stats["templateGenErrors"]++;
					//array_push($stats["filesWithErrors"], $pathToArticle);
					//break;
				}
			}
		} else{
			echo ("../templates/".$thisArticleData['template'].".html does not exist!".$eol);
			break;
		}

		//highlight_array($commonArticlesData['linkList']);

		// echo $thisArticleData['fullUrl'];
		if (!file_exists($thisArticleData['htmlPagePath']) || $cfgOverwrite) { 
			$handle = fopen($thisArticleData['htmlPagePath'], 'w+');
			fwrite($handle, $templateContent);
			fclose($handle);
			$stats["filesUpdated"]++;
			$stats["filesOutTotal"]++;
		}


	}
	else	{
		echo $file.": File is not parsed OK! Skipping...".$eol; 
		$stats["fileErrors"]++;
		$stats["templateGenErrors"]++;
	}

}

//highlight_array($allArticlesData);
$stats["memoryUsedBytes"] = mb_strlen(serialize((array)$allArticlesData), '8bit');

echo $eol."Done! Statistics:".$eol;
echo '<pre>'.var_export($stats, true).'</pre>';
//highlight_array($commonArticlesData, "commonArticlesData");
