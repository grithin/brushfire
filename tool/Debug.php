<?
/// used for basic debuging
/** For people other than me, things don't always go perfectly.  As such, this class is exclusively for you.  Measure things.  Find new and unexpected features.  Explore the error messages*/
class Debug{
	/// measurements on time and memory
	static $measures;
	static $out;
	///provided for convenience to place various user debugging related values
	static $x;
	///allows for the decision to throw or trigger error based on the config
	/**
	@param	error	error string
	@param	throw	whether to throw the error (true) or trigger it (false)
	@param	type	either level of the error or the exception class to use
	*/
	static function error($error,$type=null){
		$type = $type ? $type : E_USER_ERROR;
		trigger_error($error, $type);
	}
	///throws variable class exception
	static function toss($message=null,$type='Exception',$code=0,$previous=null){
		if(!Autoload::loaded($type)){
			eval('class '.$type.' extends Exception{}');
		}
		throw new $type($message,$code,$previous);
	}
	///Take a measure
	/** Allows you to time things and get memory usage
	@param	name	the name of the measure to be printed out with results.  To get the timing between events, the name should be the same.
	*/
	static function measure($name='std'){
		$next = count(self::$measures[$name]);
		self::$measures[$name][$next]['time'] = microtime(true);
		self::$measures[$name][$next]['mem'] = memory_get_usage();
		self::$measures[$name][$next]['peakMem'] = memory_get_peak_usage();
	}
	///get the measurement results (expects on/off on/off intervals)
	/**
	@param	type	the way in which to print out results if any.  options are "html" and "console"
	@return	returns an array with results
	*/
	static function measureResults($summary=false){
		foreach(self::$measures as $name=>$measure){
			$totalTime = 0;
			if($summary){
				while(($instance = current($measure)) && next($measure)){
					$nextInstance = current($measure);
					if($nextInstance){
						$out[$name]['timeChange'] += $nextInstance['time'] - $instance['time'];
						$out[$name]['memoryTotalChange'] += $nextInstance['mem'] - $instance['mem'];
						$peakMemChange = $nextInstance['peakMem'] - $instance['peakMem'];
						if($peakMemChange > $out[$name]['peakMemoryChange']){
							$out[$name]['peakMemoryChange'] = $peakMemChange;
						}
						if($out[$name]['peakMemoryLevel'] < $instance['peakMem']){
							$out[$name]['peakMemoryLevel'] = $instance['peakMem'];
						}
					}
					next($measure);
				}
			}else{
				while(($instance = current($measure)) && next($measure)){
					$nextInstance = current($measure);
					if($nextInstance){
						$currentCount = count($out[$name]);
						$totalTime += $nextInstance['time'] - $instance['time'];
						$out[$name][$currentCount]['timeChange'] = $nextInstance['time'] - $instance['time'];
						$out[$name][$currentCount]['memoryChange'] = $nextInstance['mem'] - $instance['mem'];
						$out[$name][$currentCount]['peakMemoryChange'] = $nextInstance['peakMem'] - $instance['peakMem'];
						$out[$name][$currentCount]['peakMemoryLevel'] = $instance['peakMem'];
					}
					next($measure);
				}
				$out[$name]['total']['time'] = $totalTime;
			}
		}
		return $out;
	}
	///put variable into the log file for review
	/** Sometimes printing out the value of a variable to the screen isn't an option.  As such, this function can be useful.
	@param	var	variable to print out to file
	@param	title	title to use in addition to other context information
	@param	logfile	the log file to write to.  $_ENV['logLocation'] can be changed in the script, but this parameter provides an alternative to changing it
	*/
	static function log($var,$title='',$logfile=null){
		if($logfile){
			$fh = fopen($logfile,'a+');
		}else{
			$fh = fopen($_ENV['logLocation'],'a+');
		}
		
		$bTrace = debug_backtrace();
		$file = self::abbreviateFilePath($bTrace[0]['file']);
		$line = $bTrace[0]['line'];
		fwrite($fh,"+=+=+=+ ".date("Y-m-d H:i:s").' | '.$_ENV['projectName']." | TO FILE | ".$file.':'.$line.' | '.$title." +=+=+=+\n".self::toString($var)."\n");
		fclose($fh);
	}
	///get a line from a file
	/**
	@param	file	file path
	@param	line	line number
	*/
	static function getLine($file,$line){
		if($file){
			$f = file($file);
			$code = substr($f[$line-1],0,-1);
			return preg_replace('@^\s*@','',$code);
		}
	}
	static function handleException($exception){
		self::handleError(E_USER_ERROR,$exception->getMessage(),$exception->getFile(),$exception->getLine(),null,$exception->getTrace(),'EXCEPTION: '.get_class($exception));
	}
	///print a boatload of information to the load so that even your grandma could fix that bug
	/**
	@param	eLevel	error level
	@param	eStr	error string
	@param	eFile	error file
	@param	eLine	error line
	*/
	static function handleError($eLevel,$eStr,$eFile,$eLine,$context=null,$bTrace=null,$type='ERROR'){
		if(ini_get('error_reporting') == 0){# @ Error control operator used
			return;
		}
		
		$code = self::getLine($eFile,$eLine);
		$eFile = self::abbreviateFilePath($eFile);
		$eFile = preg_replace('@'.PR.'@','',$eFile);
		$header = "+=+=+=+ ".date("Y-m-d H:i:s").' | '.$_ENV['projectName']." | $type | ".self::abbreviateFilePath($eFile).":$eLine +=+=+=+\n$eStr\n";
		
		$err= '';
		if($_ENV['errorDetail'] > 0){
			if(!$bTrace){
				$bTrace = debug_backtrace();
			}
			
			//php has some odd backtracing so need various conditions to remove excessive data
			if($bTrace[0]['file'] == '' && $bTrace[0]['class'] == 'Debug'){
				array_shift($bTrace);
			}
			
			//remove undesired stack points, and non-named points stemming from
			foreach($bTrace as $k=>&$v){
				$v['shortName'] = self::abbreviateFilePath($v['file']);
				foreach($_ENV['errorStackExclude'] as $exclusionPattern){
					if(!$v['file']){
						$unnamed++;
					}else{
						if($found = preg_match($exclusionPattern,$v['shortName'])){
							array_splice($bTrace,$k - $unnamed, 1 + $unnamed);
						}
						$unnamed = 0;
					}
				}
			}
			
			foreach($bTrace as $v){
				$err .= "\n".'(-'.$v['line'].'-) '.$v['shortName']."\n";
				$code = self::getLine($v['file'],$v['line']);
				if($v['class']){
					$err .= "\t".'Class: '.$v['class'].$v['type']."\n";
				}
				$err .= "\t".'Function: '.$v['function']."\n";
				if($code){
					$err .= "\t".'Line: '.$code."\n";
				}
				if($v['args'] && $_ENV['errorDetail'] > 1){
					$err .= "\t".'Arguments: '."\n";
					$args = self::toString($v['args']);
					$err .= substr($args,2,-2)."\n";
					/*
					$err .= preg_replace(
							array("@^array \(\n@","@\n\)$@","@\n@"),
							array("\t\t",'',"\n\t\t"),
							$args)."\n";*/
				}
			}
			if($_ENV['errorDetail'] > 2){
				$err.= "\nServer Var:\n:".self::toString($_SERVER);
				$err.= "\nRequest-----\nUri:".$_SERVER['REQUEST_URI']."\nVar:".self::toString($_REQUEST);
				$err.= "\n\nFile includes:\n".self::toString(Files::getIncluded());
			}
			$err.= "\n";
		}
		//identify error
		$errorHash = sha1($err);
		$header = 'Error Id: '.$errorHash."\n".$header;
		$err = $header.$err;
		
		$file = $_ENV['logLocation'];
		if(!file_exists($file) || filesize($file)>Tool::byteSize($_ENV['maxLogSize'])){
			$mode = 'w';
		}else{
			$mode = 'a+';
		}
		$fh = fopen($file,$mode);
		fwrite($fh,$err);
		
		if(!$_ENV['inScript']){
			if($_ENV['errorPage']){
				Config::loadUserFiles($_ENV['errorPage'],'.',null,['error'=>$err,'errorId'=>$errorHash]);
				exit;
			}
			if($_ENV['errorMessage']){
				if(is_array($_ENV['errorMessage'])){
					$message = $_ENV['errorMessage'][rand(0,count($_ENV['errorMessage'])-1)];
				}else{
					$message = $_ENV['errorMessage'];
				}
				preg_replace('@\$errorId@',$errorHash,$message);
				echo $message;
			}
		}
		if($_ENV['displayErrors']){
			self::sendout($err);
		}
		exit;
		
	}
	/// don't use class::__toString method on self::toString
	static $ignoreToString = false;
	///since var_export fails on objects pointing at each other, and var_dump is unreadable
	/**
	@param	objectMaxDepth	objectDepth at which function will no longer parse or show object attributes
	*/
	static function toString($variable,$objectMaxDepth=2,$depth=0,$objectDepth=0){
		if(is_object($variable)){
			if(method_exists($variable,'__toString') && !self::$ignoreToString){
				return (string)$variable;
			}else{
				if($objectDepth < $objectMaxDepth){
					$return = get_class($variable);
					$vars = get_object_vars($variable);
					if($vars){
						$return .= ' '.self::toString($vars,$objectMaxDepth,$depth+1,$objectDepth+1);
					}
					return $return;
				}else{
					return get_class($variable).' !!!Max Depth';
				}
			}
		}elseif(is_array($variable)){
			$prefix = "\n".str_repeat("\t",$depth+1);
			foreach($variable as $k=>$variable){
				$return .= $prefix.var_export($k,1).' : '.self::toString($variable,$objectMaxDepth,$depth+1,$objectDepth);
			}
			return $return ? '['.$return."\n".str_repeat("\t",$depth)."]" : '[]';
		}else{
			return var_export($variable,1);
		}
	}
	static function abbreviateFilePath($path){
		return preg_replace(array('@'.$_ENV['projectFolder'].'@','@'.$_ENV['systemFolder'].'@'),array('project:','system:'),$path);
	}
	///print a variable and kill the script
	/** first cleans the output buffer in case there was one.  Echo in <pre> tag
	@param	var	any type of var that toString prints
	*/
	static function end($var=null){
		$content=ob_get_clean();
		if($var){
			$content .= "\n".self::toString($var);
		}
		self::sendout($content);
		exit;
	}
	///print a variable with file and line context, along with count
	/**
	@param	var	any type of var that print_r prints
	*/
	static $usleepOut = 0;///<usleep each out call
	static function out(){
		self::$out['i']++;
		$trace = debug_backtrace();
		
		foreach($trace as $part){
			if($part['class'] == __CLASS__ && $part['line']){
				$trace = $part;
				break;
			}
		}
		
		$args = func_get_args();
		foreach($args as $var){
			$file = self::abbreviateFilePath($trace['file']);
			self::sendout("[".$file.':'.$trace['line']."] ".self::$out['i'].": ".self::toString($var)."\n");
		}
		if(self::$usleepOut){
			usleep(self::$usleepOut);
		}
	}
	///exists after using self::out on inputs
	static function quit(){
		$args = func_get_args();
		call_user_func_array(array(self,'out'),$args);
		exit;
	}
	///Encapsulates in <pre> if determined script not being run on console (ie, is being run on web)
	static function sendout($output){
		if($_ENV['inScript']){
			echo $output;
		}else{
			echo '<pre>'.$output.'</pre>';
		}
	}
}
