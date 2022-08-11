<?php

$scriptVersion = "08.2022";

function highlight_array($array, $name = 'var') {
    highlight_string("<?php\n\$$name =\n" . var_export($array, true) . ";\n?>");
}

function array_push_assoc($array, $key, $value){
	$array[$key] = $value;
	return $array;
}

function array_replace_needle($array, $needlePos, $needleLen, $replacement){
	//$toReturn = 0;
	$a = substr($array, 0, $needlePos);
	$b = substr($array, $needlePos + $needleLen, strlen($array) - $needleLen);
	$toReturn = $a.$replacement.$b;
	//echo "|"."<strong>"."|";
	return ($toReturn);
}

function clean($string) {
	$string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
	return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}

function array_has_dupes($array) {
	return count($array) !== count(array_unique($array));
}

include 'config.php';

date_default_timezone_set($cfgTimeZone);


	$path    = './articles';
	$files = scandir($path);
	$files = array_diff(scandir($path), array('.', '..'));
	//var_dump($files);
	
	$arrayParamList = array("tags", "rand");
	$arrayHtmlReplacePairs = array(
		array("\r\n", "<br>"),
	);
	
	$arrayMdReplaceSingles = array(
		// order here is critical: otherwise "## " will be recognized as " # + '# ' "!
		array("### ", "<h3>", "</h3>"),
		array("## ", "<h2>", "</h2>"),
		array("# ", "<h1>", "</h1>"),
	);

	$arrayMdReplaceTriplets = array(
		array("**", "<strong>", "</strong>"),
		array("__", "<strong>", "</strong>"),
		array("*", "<em>", "</em>"),
		array("_", "<em>", "</em>"),
	);

	$arrayLinkList = array();
	$arrayTagList = array();

	$urlsSoFar = array();
	foreach ($files as $file){
		$pathToArticle = $path."/".$file;
		$articleFileContent = file_get_contents($pathToArticle);
		$articleFileLines = file($pathToArticle);
		$articleHeaderData = array();

		$articleHeaderData["generationDate"] = date($cfgDateFormat);
		$articleHeaderData["generationTime"] = date($cfgTimeFormat);

		for ($line = 0; $line < count($articleFileLines); $line++){
			//
			$thisLine = $articleFileLines[$line];
			if (!array_key_exists("text", $articleHeaderData)){
				$paramNameLen = strpos($thisLine, ": ");
				if ($paramNameLen) {
					$thisLine = str_replace(array("\r", "\n"), "", $thisLine);
					$paramName = mb_substr($thisLine, 0, $paramNameLen);
					$paramValue = mb_substr($thisLine, $paramNameLen + 2, strlen($thisLine) - $paramNameLen);
					//echo $line." = ".$articleFileLines[$line]."<br>";
					//echo $line." = ".$paramValue."|<br>";
					//echo  $line." = ".strlen($thisLine),"<br>";
					//if ($paramName == "tags"){
					if (in_array($paramName, $arrayParamList)){
						$tagsNoSpace = str_replace(" ", "", $paramValue);
						$articleHeaderData[$paramName] = preg_split ("/\,/", $tagsNoSpace); 
					}
					else
						$articleHeaderData[$paramName] = $paramValue;
				}
				else {
					echo "line doesnt contain ': ', thus is not a parameter";
					die();
				}
			}
		}

		$arrayTagList = array_merge($arrayTagList, $articleHeaderData["tags"]);
		$arrayTagList = array_unique($arrayTagList);

		if (array_key_exists("skip", $articleHeaderData)) break;

		if ($articleHeaderData["date"] == "auto") {
			$marker = "date: auto";
			$markerPos = strpos($articleFileContent, $marker);
			if ($markerPos){
				$r = "date: ".$articleHeaderData["generationDate"]." ".$articleHeaderData["generationTime"];
				$articleFileContent = 
					array_replace_needle($articleFileContent, $markerPos, strlen($marker), $r);
				file_put_contents($pathToArticle, $articleFileContent);
			}
		}

		if (!in_array($articleHeaderData["url"], $urlsSoFar))
			array_push($urlsSoFar, $articleHeaderData["url"]);

		else {
			echo "url '".$articleHeaderData["url"]."' already exist! skipping";
			break;
		}
		//highlight_array($urlsSoFar);
	
		$articleHeaderData["url"] = clean($articleHeaderData["url"]);
		// check if header is complete
		$headerKeysNeeded = array("title", "date", "tags", "text");
		foreach ($headerKeysNeeded as $headerKey){
			if (!array_key_exists($headerKey, $articleHeaderData)){
				echo "header data doesnt contain '$headerKey', thus article is empty";
				die();
			}
		}
		
		$articleTextStart = strpos($articleFileContent, "text: ");
		$articleTextRaw = substr($articleFileContent, $articleTextStart + strlen("text: "));
		$articleHeaderData['textRaw'] = $articleTextRaw;
		//highlight_array($articleTextRaw);

		// markdown to html
		$articleTextHtml = $articleTextRaw;
		$articleHeaderData['preMarkdonwn'] = $articleTextRaw;
		$textPointer = 0;

		// ![images](pic.jpg)
		$htmlLinkString = "";
		$linkURLStart = 0;
		
		if (strpos($articleTextHtml, "![") !== false ){
			$linkTitleStart = strpos($articleTextHtml, "![") + 1;
			$linkTitleEnd = strpos($articleTextHtml, "]", $linkTitleStart+1);
			if ($linkTitleEnd !== false){
				$linkTitle = substr($articleTextHtml, $linkTitleStart+1, $linkTitleEnd-$linkTitleStart-1);
				$linkURLStart = strpos($articleTextHtml, "](", $linkTitleEnd) + 1;
				if ($linkURLStart !== false){
					$linkURLEnd = strpos($articleTextHtml, ")", $linkURLStart);
					if ($linkURLEnd !== false){
						$linkURL = substr($articleTextHtml, $linkURLStart+1, $linkURLEnd-$linkURLStart-1);
						/*
						// link is email
						if (filter_var($linkURL, FILTER_VALIDATE_EMAIL) == true) {
							$htmlLinkString = "<a href=mailto:".$linkURL.">".$linkTitle."</a>";	
						}

						// link is broken
						else if (filter_var($linkURL, FILTER_VALIDATE_URL) == false) {
							if (strpos($linkURL, "local:") !== false){
								// this is a link to local article
								$articleURL = substr($linkURL, strpos($linkURL, "local:") + 6, strlen($linkURL) - 6);
								//echo "{".$articleURL."}";
								// make link to article with corresponding name if there is no "http" in URL
								$htmlLinkString = "<a href=".$cfgUrl."html/".$articleURL.".html".">".$linkTitle."</a>";
								
							}
							else $htmlLinkString = $linkTitle;
						}
						
						// just URL
						else $htmlLinkString = "<a href=".$linkURL.">".$linkTitle."</a>";
*/
						$htmlLinkString = "<img src=\"../images/".$linkURL."\" alt=\"".$linkTitle."\">";

						$needle = substr($articleTextHtml, $linkTitleStart - 1, $linkURLEnd-$linkTitleStart+2);

						echo $linkURL;
						
						$articleTextHtml = array_replace_needle($articleTextHtml, $linkTitleStart - 1, strlen($needle), $htmlLinkString);
					}
				}
			}
		}

		/*
		// <this style> of links	
		highlight_array($articleTextHtml);
		while (strpos($articleTextHtml, "<") !== false){
			
			$linkURLStart = strpos($articleTextHtml, "<");
			$linkURLEnd = strpos($articleTextHtml, ">", $linkURLStart + 1);
			
			if ($linkURLStart !== false){
				// check here if any MD tags is inbetween start and end
			
				$linkURL = substr($articleTextHtml, $linkURLStart+1, $linkURLEnd-$linkURLStart-1);
				if ($linkURL != strip_tags($linkURL))
					break;

				echo $linkURL;

				$htmlLinkString = "<a href=".$linkURL.">".$linkURL."</a>";
				
				// isnt URL
				if (filter_var($linkURL, FILTER_VALIDATE_URL) === FALSE) 
					$htmlLinkString = $linkURL;

				if (filter_var($linkURL, FILTER_VALIDATE_EMAIL) === TRUE) 
					// it's email
					$htmlLinkString = "<a href=mailto:".$linkURL."></a>";
				
				

				$needle = substr($articleTextHtml, $linkURLStart, $linkURLEnd - $linkURLStart+1);
				//echo "!!!".$lastPos;
				$articleTextHtml = array_replace_needle($articleTextHtml, $linkURLStart, strlen($needle), $htmlLinkString);
			
				
			}		
		}
		*/

		// [this style](of links)
			while (strpos($articleTextHtml, "[") !== false ){
				$linkTitleStart = strpos($articleTextHtml, "[");
				$linkTitleEnd = strpos($articleTextHtml, "]", $linkTitleStart+1);
				if ($linkTitleEnd !== false){
					$linkTitle = substr($articleTextHtml, $linkTitleStart+1, $linkTitleEnd-$linkTitleStart-1);

					$linkURLStart = strpos($articleTextHtml, "(");
					if ($linkURLStart !== false){
						$linkURLEnd = strpos($articleTextHtml, ")", $linkURLStart+1);
						if ($linkURLEnd !== false){
							$linkURL = substr($articleTextHtml, $linkURLStart+1, $linkURLEnd-$linkURLStart-1);

							// link is email
							if (filter_var($linkURL, FILTER_VALIDATE_EMAIL) == true) {
								$htmlLinkString = "<a href=mailto:".$linkURL.">".$linkTitle."</a>";	
							}

							// link is broken
							else if (filter_var($linkURL, FILTER_VALIDATE_URL) == false) {
								if (strpos($linkURL, "local:") !== false){
									// this is a link to local article
									$articleURL = substr($linkURL, strpos($linkURL, "local:") + 6, strlen($linkURL) - 6);
									//echo "{".$articleURL."}";
									// make link to article with corresponding name if there is no "http" in URL
									$htmlLinkString = "<a href=".$cfgUrl."html/".$articleURL.".html".">".$linkTitle."</a>";
									
								}
								else $htmlLinkString = $linkTitle;
							}
							
							// just URL
							else $htmlLinkString = "<a href=".$linkURL.">".$linkTitle."</a>";

							$needle = substr($articleTextHtml, $linkTitleStart, $linkURLEnd-$linkTitleStart+1);
							$articleTextHtml = array_replace_needle($articleTextHtml, $linkTitleStart, strlen($needle), $htmlLinkString);
						}
					}
				}
			}


		// framing tags to html tags (triplets)
		foreach ($arrayMdReplaceTriplets as $triplet){
			while (strpos($articleTextHtml, $triplet[0]) !== false){
				$tagStart = strpos($articleTextHtml, $triplet[0]);
				$lastStrongPos = 0;
				//echo "tagStart = ".$tagStart;
				$tagEnd = strpos($articleTextHtml, $triplet[0], $tagStart+1);
				if ($tagEnd !== false){
					$articleTextHtml = array_replace_needle($articleTextHtml, $tagStart, strlen($triplet[0]), $triplet[1]);
					$articleTextHtml = array_replace_needle($articleTextHtml, $tagEnd + strlen($triplet[1]) - strlen($triplet[0]), strlen($triplet[0]), $triplet[2]);
				}	
				else {
					// if text is not framed with md tags, remove tag
					$articleTextHtml = array_replace_needle($articleTextHtml, $tagStart, strlen($triplet[0]), "");
				}
			}
		}

		// non-framing tags to html (#, ##, etc.)
		foreach ($arrayMdReplaceSingles as $triplet){
			while (strpos($articleTextHtml, $triplet[0]) !== false ){
				$tagStart = strpos($articleTextHtml, $triplet[0]);
				$tagEnd = strpos($articleTextHtml, "\r\n", $tagStart+1);
				if ($tagEnd !== false){
					$articleTextHtml = array_replace_needle($articleTextHtml, $tagStart, strlen($triplet[0]), $triplet[1]);
					$articleTextHtml = array_replace_needle($articleTextHtml, $tagEnd + strlen($triplet[1]) - strlen($triplet[0]), strlen($triplet[0]), $triplet[2]);
				}	
				else {
					// if text is not framed with md tags, remove tag
					$articleTextHtml = array_replace_needle($articleTextHtml, $tagStart, strlen($triplet[0]), "");
				}
				//$lastStrongPos = $tagEnd;
				//$tagStart = 
			}
		}


	

		foreach ($arrayHtmlReplacePairs as $pair){
			$articleTextHtml = str_replace($pair[0], $pair[1], $articleTextHtml);
		}


		//$articleHeaderData['postMarkdonwn'] = $articleTextHtml;
		// \r\n to <br> and etc
		$articleHeaderData['htmlText'] = $articleTextHtml;

		// create HTML for tag block
		$articleHeaderData['htmlBlockTags'] = "";
		foreach ($articleHeaderData['tags'] as $tag)
			$articleHeaderData['htmlBlockTags'] .= $tag." | ";
		
		// create page title
		$articleHeaderData['htmlPageTitle'] = $cfgSiteTilie.": ".$articleHeaderData['title'];

		// fill HTML generation info
		$articleHeaderData['htmlBlockGeninfo'] = 	"Generated: ".$articleHeaderData['generationTime']." ".$articleHeaderData['generationDate'].
													" (script rev is ".$scriptVersion.")";

		// fill HTML links info
		$articleHeaderData['htmlBlockLinks'] = "";

		// what to replace in template
		$arrTemplateMarkersAndReplacements = array(
			array("***TEXT***", $articleHeaderData['htmlText']),
			array("***TAGS***", $articleHeaderData['htmlBlockTags']),
			array("***PGTITLE***", $articleHeaderData['htmlPageTitle']),
			array("***LINKS***", $articleHeaderData['htmlBlockLinks']),
			array("***GENINFO***", $articleHeaderData['htmlBlockGeninfo']),
		);

		// read template
		$templateContent = file_get_contents("./templates/article.html");
		if ($templateContent){
			foreach ($arrTemplateMarkersAndReplacements as $thisPair){
				$marker = $thisPair[0];
				$r = $thisPair[1];
				$markerPos = strpos($templateContent, $marker);
				
				if ($markerPos){
					$templateContent = 
						array_replace_needle($templateContent, $markerPos, strlen($marker), $r);
				}
			}
		}

		else 
			die("./templates/article.html does not exist!");
	
		$htmlFolder = "./html/";
		$pathToPage = $htmlFolder.$articleHeaderData['url'].".html";
		$articleHeaderData['fullUrl'] = $pathToPage;
		array_push($arrayLinkList, $pathToPage);

		//if (!file_exists($pathToPage)) { 
			$handle = fopen($pathToPage, 'w+'); 
			fwrite($handle, $templateContent); 
			fclose($handle);
		//}
		//highlight_array($articleHeaderData);

		echo "<a href=\"".$pathToPage."\">".$file."</a><br>";
	
		}
	
	highlight_array($arrayLinkList);
	highlight_array($arrayTagList);
	
?>