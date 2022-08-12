<?php
	$cfgSiteTitle = "blog";
	$cfgUrl = "http://localhost:81/static-gen/";
	$cfgBaseUrl = "";
	$cfgTwitter = "";
	$cfgEmail = "";
	$cfgGithub = "";
	$cfgColors = array(
		"Peter" => "35", 
		"Ben" => "37", 
		"Joe" => "43"
	);
	$cfgTimeZone = "Europe/Moscow";
	$cfgDateFormat = "d.m.Y";
	$cfgTimeFormat = "H:i:s";
	$cfgOverwrite = true;

	$templateReplacementInfo = array(
		'tags' => array(															// htmlBlocks['tags'], thisArticleData['tags']
			'templateMarker' => "***TAGS***",										// look for ***TAGS*** in template
			'lineMarker' => "***THISTAG***",										// look for ***TAGTEXT*** in block line template
			'lineText' => '<a href="tags/***THISTAG***.html" class="w3-button w3-small w3-padding-small">***THISTAG***</a>',		// block line template
			// <button class="w3-button w3-small w3-padding-small">stm32</button>
			'replacementPairs' => array(
				array("tags", "***THISTAG***"),
			),
		),

		'urlList' => array(							
			'templateMarker' => "***URLS***",		
			'lineMarker' => "***THISURL***",		
			'lineText' => "<a href = \"***THISURL***\">***THISTITLE***</a>",	
			'replacementPairs' => array(
				array("titleList", "***THISTITLE***"),
				array("urlList", "***THISURL***"),
				array("peep", "***SHEESH***"),	// debug junk
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

		'genInfo' => array(			
			'templateMarker' => "***GENINFO***",	
			'lineText' => "***GENINFO***",		
		),
	);

	$templatePairReplace = array(
		array("<code>", '<div class="w3-panel w3-pale-yellow w3-border w3-border-yellow"><div class="w3-code notranslate">'),
		array("</code>", "</div></div>"),
	);

	
	// list of parameters to be saved in arrays drom .md files
	$arrayParamList = array("tags", "rand");


?>