<?
	define("DEBUG",			0);
	define("HISTORY_FILE",	dirname ( __FILE__ )."/subster-history.txt");
	define("VIDEOS_DIR",	"/volume1/video/");
	define("LANGUAGES",		"eng,ell");
	// Use python hasher since its faster and can handles files larger than 2G
	define("HASHER", 		dirname ( __FILE__ )."/hasher.py");

	function OpenSubtitlesHash($file){
		exec('python '.HASHER.' "'.$file.'"',$res);
		return $res[0];
	}
	
	// Commands to execute once we found a sub file to download
	function downloadSubs($path,$id){
		$cmds = array();
		$cmds[] = 'wget -q -O "'.$path.'/'.$id.'.zip" http://www.opensubtitles.org/en/subtitleserve/sub/'.$id.' ';
		$cmds[] = 'cd "'.$path.'/"';
		$cmds[] = 'unzip -uo "'.$path.'/'.$id.'.zip"';
		$cmds[] = 'rm -rf "'.$path.'/'.$id.'.zip"';		
	   	if (DEBUG) {
			$cmd = implode("\n",$cmds);	   	
	   		echo $cmd."\n\n";
	   	} else {
			$cmd = implode("; ",$cmds);	   	
   		  	exec($cmd);
   		}
		unset($cmds);   		
	}
	
	// Rename the subtitles from whater.srt to originalfilename.srt with counter
	function renamer($path,$f){
   		// Get the srt file
   		$command = 'find "'.$path.'/" -type f -iname "*.srt"';
		exec($command,$res);
		if (DEBUG && $res) {
			print_r($res);
		}
   		if ($res) {
   			$i = count($res);
   			$cmd = array();
   			foreach($res as $r){
   				$cmd[] = 'mv -f "'.$r.'" "'.$path.'/'.$f.'.'.$i--.'.srt"';
   			}
		   	if (DEBUG) {
		   		$cmds = implode('\n',$cmd);
		   		echo $cmds."\n\n";
		   	} else {
		   		$cmds = implode('; ',$cmd);
	   		  	exec($cmds);
	   		}
   		}
	}
	
	
	// search for subs on opensubtitles
	function getSubs($file,$hash){
		$score = -1;
		$path = dirname($file);
		$f = basename($file);
		$subtitles = array();
		$url = "http://www.opensubtitles.org/en/search/sublanguageid-".LANGUAGES."/moviehash-".$hash;
		// first get headers
		$h = get_headers($url);	
		if ($h[0]=="HTTP/1.1 301 Moved Permanently"){
			foreach($h as $he){
				if (strstr($he,"Location: http://www.opensubtitles.org/en/subtitles/")){
					preg_match("/en\/subtitles\/(.*?)\//is",$he,$newID);
					echo (DEBUG) ? $newID[1]." - Only one match found \n" : "";
					downloadSubs($path,$newID[1]);
					renamer($path,$f);
				}
			}
		} else {	
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
						if ($sub_score < $score) {
							$score = $sub_score;
						}
						$subtitles[$id] = $sub_score;					
						
						if (DEBUG){
							$found = $id." - ".str_replace("\n"," ",strip_tags($title[2])).' - '.str_replace("\n"," ",strip_tags($release[1]));
							$found = str_replace(array("\t","  ","   ")," ",$found);
							echo $found."\n";
						}				
					}
					
					foreach($subtitles as $subid => $sub) {
		    			if ($sub == $score || $sub < $score*1.1) {
		    				if (DEBUG) {
		    					echo $subid." - Score: ".$sub." -- Downloading\n";
		    				}
		    				downloadSubs($path, $subid);
		    			}
		    		}					   	
					renamer($path,$f);		
				}
			}	
		}		
	}
	
	
	
	// Scans for series at least 1 day old - If they don't have a sub, then try to find one for them - this should go on cron
	if (isset($argv[1]) && $argv[1]=='--rescan'){
		$q = 'find '.VIDEOS_DIR.' -type f \( \( -iname "*.mkv" -or -iname "*.avi" \) ! -iname "sample-*" ! -iname "eaDir" ! -iname "*sample.mkv" ! -iname "*sample.avi" \) -ctime -1';
		exec($q,$res);
		foreach($res as $r){
			$find = 'find '.dirname($r).' -type f -iname "*.srt"';
			exec($find,$findResults);
			if (!$findResults){
				$hash = OpenSubtitlesHash($r);	
				echo (DEBUG) ? "Retrying ". $hash." - ".basename($r)."\n" : "";
				getSubs($r,$hash);
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
				echo (DEBUG) ? $hash." - ".basename($f)."\n" : "";
				getSubs($f,$hash);
			}
		}
	}
?>
