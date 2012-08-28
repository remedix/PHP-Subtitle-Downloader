<?	
	define("DEBUG",			0);
	define("HISTORY_FILE",	dirname ( __FILE__ )."/subster-history.txt");
	define("VIDEOS_DIR",	"/volume1/video/");
	define("LANGUAGES",		"eng,ell");
	// Use python hasher since its faster and can handles files larger than 2G
	define("HASHER", 		dirname ( __FILE__ )."/hasher.py");

	$download = array();
	
	function OpenSubtitlesHash($file){
		exec('python '.HASHER.' "'.$file.'"',$res);
		return $res[0];
	}
	
	// Commands to execute once we found a sub file to download
	function downloadSubs($url,$path){
		$id = md5($path.uniqid());
		$cmds = array();
		echo (DEBUG) ? "Downloading...\n" : '';
		$cmds[] = 'mkdir /tmp/'.$id.'/';
		$cmds[] = 'wget -q -O "/tmp/'.$id.'/'.$id.'.zip" "'.$url.'"';
		$cmds[] = 'cd "/tmp/'.$id.'/"';
		$cmds[] = 'unzip -uo "/tmp/'.$id.'/'.$id.'.zip"';
		$cmds[] = 'rm -rf "/tmp/'.$id.'/'.$id.'.zip"';		
	   	if (DEBUG) {
			$cmd = implode("\n",$cmds);	   	
	   		echo $cmd."\n\n";
	   	} else {
			$cmd = implode("; ",$cmds);	   	
   		  	exec($cmd);
   		}	
		unset($cmds);   	
		
		echo (DEBUG) ? "Renaming...\n" : '';
		// Rename the subtitles from whater.srt to originalfilename.srt with counter
   		// Get the srt file
   		$command = '/usr/bin/find "/tmp/'.$id.'/" -type f -iname "*.srt"';
		exec($command,$res);
		if (DEBUG && $res) {
			print_r($res);
		}
   		if ($res) {
   			$i = count($res);
   			$cmd = array();
   			$f = basename($path);
   			foreach($res as $r){
   				$cmd[] = 'mv -f "'.$r.'" "'.dirname($path).'/'.$f.'.'.$i--.'.srt"';
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
	
	
	function searchPodnapisi($str)
	{
		echo (DEBUG) ? "Trying podnapisi..." : '';
		$res = null;
		$url = "http://www.podnapisi.net/en/ppodnapisi/search?sK=".$str;
		if ($s = @file_get_contents($url)){
			if (preg_match("/\/en\/(.*?)\-subtitles\-p(.*?)\">/is",$s,$matches)){
				$id = $matches[2];
				$s = file_get_contents("http://www.podnapisi.net/en/ppodnapisi/podnapis?i=".$id);
				preg_match("/\/en\/ppodnapisi\/download\/i\/$id\/k\/(.*?)\" title/is",$s,$link);
				echo (DEBUG) ? "Found ".$link[1]."\n" : '';
				$res = "http://www.podnapisi.net/en/ppodnapisi/download/i/".$id."/k/".$link[1];
			}
		}
		return $res;
	}
	
	
	
	function searchOpensubtitles($file,$hash)
	{
		global $download;
		echo (DEBUG) ? "Trying opensubtitles..." : '';
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
					echo (DEBUG) ? "OS: ".$newID[1]." - Only one match found \n" : "";
					return "http://www.opensubtitles.org/en/subtitleserve/sub/".$newID[1];		}
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
						if ($sub_score < $score || $score < 0) {
							$score = $sub_score;
						}
						$subtitles[$id] = $sub_score;								
					}
					$results = array();
					foreach($subtitles as $subid => $sub) {
		    			if ($sub == $score || $sub < $score*1.1) {
		    				if (DEBUG) {
		    					echo "Found. \n".$subid." - Score: ".$sub."\n";
		    				}
							$download[$file][]  = "http://www.opensubtitles.org/en/subtitleserve/sub/".$subid;
		    			}
		    		}	
		    		return $results;				   			
				}
			}	
		}		
	}	
	
	
	

	
	// Scans for series at least 3 days old - If they don't have a sub, then try to find one for them - this should go on cron
	if (isset($argv[1]) && $argv[1]=='--rescan'){
		$q = 'find '.VIDEOS_DIR.' -type f \( \( -iname "*.mkv" -or -iname "*.avi" \) ! -iname "sample-*" ! -iname "eaDir" ! -iname "*sample.mkv" ! -iname "*sample.avi" \) -ctime -10';
		exec($q,$res);
		foreach($res as $r){
			$find = 'find "'.dirname($r).'" -type f -iname "*.srt"';
			exec($find,$findResults);
			if (!$findResults){
				$hash = OpenSubtitlesHash($r);	
				echo (DEBUG) ? "\n[rescan] Trying ". $hash." - ".basename($r)."\n" : "";
				if ($found = searchOpensubtitles($r,$hash)) $download[$r][] = $found;
				if ($found = searchPodnapisi(basename($r))) $download[$r][] = $found;							
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
				if ($found = searchOpensubtitles($f,$hash)) $download[$f][] = $found;
				if ($found = searchPodnapisi(basename($f))) $download[$f][] = $found;	
			}
		}
	}
	
	foreach(array_filter($download) as $path=>$urls){
		foreach($urls as $url){
			downloadSubs($url,$path);
		}
	}

?>