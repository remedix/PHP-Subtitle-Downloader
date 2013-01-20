<?
	if ($argv) foreach($argv as $a){
		if (trim($a) == '--debug') define("DEBUG", 1);
		if (trim($a) == '--rescan') define("RESCAN", 1);		
	}
	if (!defined("DEBUG")) define("DEBUG", 0);
	if (!defined("RESCAN")) define("RESCAN", 0);	
	define("HISTORY_FILE",	dirname ( __FILE__ )."/subster-history.txt");
	define("VIDEOS_DIR",	"/volume1/video/");
	define("LANGUAGES",		"eng");
	// Use python hasher since its faster and can handles files larger than 2G
	define("HASHER", 		dirname ( __FILE__ )."/hasher.py");

	$download = array();
	
	function OpenSubtitlesHash($file){
		exec('python '.HASHER.' "'.$file.'"',$res);
		return $res[0];
	}

	
	// Commands to execute once we found a sub file to download
	function downloadSubs($url,$path,$index){
		$id = md5($path.uniqid());
		$cmds = array();
		echo (DEBUG) ? "\n--------------------\n\nDownloading...".basename($path)."\n" : '';
		$cmds[] = 'mkdir -p /tmp/subster/';
		$cmds[] = 'mkdir /tmp/subster/'.$id.'/';
		$cmds[] = 'wget -q -O "/tmp/subster/'.$id.'/'.$id.'.zip" "'.$url.'"';
		$cmds[] = 'cd "/tmp/subster/'.$id.'/"';
		$cmds[] = 'unzip -uo "/tmp/subster/'.$id.'/'.$id.'.zip"';
		//$cmds[] = 'rm -rf "/tmp/subster/'.$id.'/'.$id.'.zip"';		
	   	if (DEBUG) {
			$cmd = implode("\n",$cmds);	   	
	   		echo $cmd."\n\n";
   		  	exec($cmd);	   		
	   	} else {
			$cmd = implode("; ",$cmds);	   	
   		  	exec($cmd);
   		}	
		unset($cmds);   	
		echo (DEBUG) ? "Renaming...\n" : '';
		// Rename the subtitles from whater.srt to originalfilename.srt with counter
   		// Get the srt file
   		$command = '/usr/bin/find "/tmp/subster/'.$id.'/" -type f -iname "*.srt"';
		exec($command,$res);
		if (DEBUG && $res) {
			print_r($res);
		}
   		if ($res) {
   			$cmd = array();
   			$f = basename($path);
   			foreach($res as $r){
   				$cmd[] = 'mv -f "'.$r.'" "'.dirname($path).'/'.$f.'.'.$index.'.srt"';   				
   			}
		   	if (DEBUG) {
		   		$cmds = implode('\n',$cmd);
		   		echo $cmds."\n\n";
	   		  	exec($cmds);
		   	} else {
		   		$cmds = implode('; ',$cmd);
	   		  	exec($cmds);
	   		}
   		}
   		// Ok everything done. Let's remove the tmp directory
   		exec("rm -rf /tmp/subster/".$id."/");
	}
	
	
	// function searchSubsmax($str){
	// 	echo (DEBUG) ? "Trying Subsmax..." : '';	
	// 	$url = "http://subsmax.com/api/10/";
	// 	$str = str_replace(".", "-", $str);
	// 	$str = str_replace(" ", "-", $str);
 //    	if (preg_match("/(.*?)S([0-9]+)E([0-9]+)/is",$str,$title)){
 //    		// Found TV Show
 //    	} else if (preg_match("/(.*?)\-([0-9]{4})/is",$str,$title)){
 //    		// Found Movie    	
 //    	}
 //    	$subs = array();
 //    	if ($xml = @new SimpleXMLElement($url.$title[0].'-english', NULL, TRUE)){                                   
	//     	// Create a download list
	//     	foreach($xml->items->item as $i){
	//     		$p = explode("/", $i->link);
	//     		$size = count($p);
	//     		$id = $p[$size-1];
	//     		unset($p[$size-1]);
	//     		$suburl = trim(implode("/", $p).'/download-subtitle/'.$id);
	//     		$subs[] = $suburl;
	//     	}
	// 	}
	// 	if ($subs && DEBUG) {	
	// 		echo "Found\n";
	// 		echo implode("\n", $subs)."\n";
	// 	}
	// 	if (empty($subs) && DEBUG) echo "Not Found\n";
	// 	return $subs;
	// }
	
	
	// Not used
	// function searchPodnapisi($str)
	// {
	// 	echo (DEBUG) ? "Trying podnapisi..." : '';
	// 	$res = null;
	// 	$url = "http://www.podnapisi.net/en/ppodnapisi/search?sOH=1&sS=downloads&sO=desc&sK=".urlencode($str);
	// 	if ($s = @file_get_contents($url)){
	// 		if (preg_match("/\/en\/(.*?)\-subtitles\-p(.*?)\">/is",$s,$matches)){
	// 			$id = $matches[2];
	// 			$s = file_get_contents("http://www.podnapisi.net/en/ppodnapisi/podnapis?i=".$id);
	// 			preg_match("/\/en\/ppodnapisi\/download\/i\/$id\/k\/(.*?)\" title/is",$s,$link);
	// 			echo (DEBUG) ? "Found ".$link[1]."\n" : '';
	// 			$res = "http://www.podnapisi.net/en/ppodnapisi/download/i/".$id."/k/".$link[1];
	// 		} else {
	// 			echo (DEBUG) ? "Not Found\n" : "";
	// 		}
	// 	}
	// 	return $res;
	// }
	

	
	function searchOpensubtitles($file,$hash,$releaseTitle = false)
	{

		global $download;
		echo (DEBUG) ? "Trying opensubtitles..." : '';
		$path = dirname($file);
		$f = basename($file);
		$subtitles = $results = array();


		// Search by Hash - may return 1 or many results
		$url = "http://www.opensubtitles.org/en/search/sublanguageid-".LANGUAGES."/moviehash-".$hash;
		$h = get_headers($url);		

		if ($h[0]=="HTTP/1.1 301 Moved Permanently"){
			foreach($h as $he){
				if (strstr($he,"Location: http://www.opensubtitles.org/en/subtitles/")){
					preg_match("/en\/subtitles\/(.*?)\//is",$he,$newID);
					echo (DEBUG) ? "Found single match ".$newID[1]."\n" : "";
					$results[] = array("http://dl.opensubtitles.org/en/download/subb/".$newID[1]);
				}
			}
		} else {	
			$results = opensubtitlesGetMultipleMatches($url,$f);
		}	


		// if not found by now, search by title
		if (empty($results)){
			$url = "http://www.opensubtitles.org/en/search2/sublanguageid-".LANGUAGES."/moviename-".$releaseTitle;
			$h = get_headers($url);
			foreach($h as $he){
				if (strstr($he,"Location: http://www.opensubtitles.org/en/")){
					$url = str_replace("Location: ", "", $he);
				}
			}

			if (preg_match("/http:\/\/www\.opensubtitles\.org\/en\/subtitles\/([0-9]+?)\/(.*?)/is", $url,$matches)){
				echo (DEBUG) ? "Found direct match ".$matches[1]."\n" : "";
				$results[] = "http://www.opensubtitles.org/en/subtitleserve/sub/".$matches[1];
			} else if (strstr($url, "/imdbid-")){
				$results = opensubtitlesGetSameMovieMultipleMatches($url);
			}
		}

		return $results;
	}	 


	// Fetches subtitles from the correct/exact title but with multiple matches on subtitles
	function opensubtitlesGetSameMovieMultipleMatches($url) {
		$results = array();
		if ($s = @file_get_contents($url)){
			preg_match_all("/<td id=\"main([0-9]*?)\">/is",$s,$m);
			if ($m[1]){   		
				echo (DEBUG) ? "Found\n" : "";
				foreach($m[1] as $e){
					$results[] = "http://www.opensubtitles.org/en/subtitleserve/sub/".$e;		
				}	
				return $results;				   			
			} else {
				echo (DEBUG) ? "Not Found\n" :"";
			}
		}		
	}
	
	// Fetches subtitles from a fuzzy title but with multiple matches on subtitles.
	function opensubtitlesGetMultipleMatches($url,$f) {
		$score = -1;
		$results = array();
		if ($s = @file_get_contents($url)){
			preg_match_all("/<td class=\"sb_star_(.*?)<\/td>/is",$s,$m);
			if ($m[0]){   		
				$counter = 1;
				foreach($m[0] as $e){
					// Get title
					preg_match("/<a class=\"bnone\"(.*?)\">(.*?)<\/a>/is",$e,$title);
					// Get release name
					preg_match("/<br \/>(.*?)<br \/>/is",$e,$release); 
					// Get unique id
					preg_match("/id=\"main(.*?)\"/is",$e,$ids);
					$id = trim($ids[1]);
					$sub_score = levenshtein($f, str_replace("\n"," ",strip_tags($title[2])));
					if ($sub_score < $score || $score < 0) {
						$score = $sub_score;
					}
					$subtitles[$id] = $sub_score;						
				}
				$results = array();
				foreach($subtitles as $subid => $sub) {
					if ($sub == $score || $sub < $score*1.1) {
						$results[]  = "http://www.opensubtitles.org/en/subtitleserve/sub/".$subid;							
					}
				}	
				if ($results && DEBUG) {
		    		echo "Found\n";
		    		echo implode("\n", $results)."\n";
				}
				return $results;				   			
			}
		}		
	}


	
	// Scans for series at least X days old - If they don't have a sub, then try to find one for them - this should go on cron
	if (RESCAN){
		$q = 'find '.VIDEOS_DIR.' -type f \( \( -iname "*.mkv" -or -iname "*.avi" \) ! -iname "sample-*" ! -iname "eaDir" ! -iname "*sample.mkv" ! -iname "*sample.avi" \) -ctime -5';		
		exec($q,$res);
		foreach($res as $r){
			$find = 'find "'.dirname($r).'" -type f -iname "*.srt"';
			exec($find,$findResults);
			if (!$findResults){
				$hash = OpenSubtitlesHash($r);	
				echo (DEBUG) ? "\n[rescan] Trying ". $hash." - ".basename($r)."\n" : "";
				if ($foundArray = searchOpensubtitles($r,$hash,substr(basename($r),0,-4))) foreach($foundArray as $found) $download[$r][] = $found;
				//if ($foundArray = searchSubsmax(basename($r))) foreach($foundArray as $found) $download[$r][] = $found;				
			}
			unset($findResults);
		}
	} else {
		// lookup for all avi/mkv files that are not samples
		$q = 'find '.VIDEOS_DIR.' -type f \( \( -iname "*.mkv" -or -iname "*.avi" \) ! -iname "eaDir" ! -iname "sample-*" ! -iname "*sample.mkv" ! -iname "*sample.avi" \)';
		exec($q,$res);
		$history = file(HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach($res as $f){
			if (!in_array(basename($f),$history)) {
				$fp = fopen(HISTORY_FILE, 'a');		
				if (DEBUG) {
					echo "Writing on history file\n";		
				} else {
					fwrite($fp, basename($f)."\n");	
				}
				fclose($fp);
				$hash = OpenSubtitlesHash($f);	
				echo (DEBUG) ? "\n[normal] Trying ". $hash." - ".basename($f)."\n" : "";
				if ($foundArray = searchOpensubtitles($f,$hash,substr(basename($f),0,-4))) foreach($foundArray as $found) $download[$f][] = $found;
				//if ($foundArray = searchOpensubtitles($f,$hash)) foreach($foundArray as $found) $download[$f][] = $found;
				//if ($foundArray = searchSubsmax(basename($f))) foreach($foundArray as $found) $download[$f][] = $found;		
				
			}
		}
	}


	foreach(array_filter($download) as $path=>$urls){
		foreach($urls as $index=>$url){
			downloadSubs($url,$path,$index);			
		}
	}

?>
