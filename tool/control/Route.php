<?
namespace control; use \Debug;
///Used to handle requests by determining path, then determining controls
/**
Ssee routes.sample.php for route rule information

Route Rules Logic
	All routes are optional
	Routes are discovered one level at a time, and previous routing rules affect the discovery of new routes
	if a matching rule is found, the Route rules loop starts over with the new path (unless option set to not do this)
	
	http://bobery.com/bob/bill/sue:
		control/routes.php
		control/bob/routes.php
		control/bob/bill/routes.php
		control/bob/bill/sue/routes.php
		

Controls Calling Logic:
	All controls are optional.  However, if the Route is still looping tokens (stop it by exiting or emptying $unparsedUrlTokens) and the last token does not match a control, page not found returned
	
	http://bobery.com/bob/bill/sue:
		control/control.php
		control/bob.php || control/bob/control.php
		control/bob/bill.php || control/bob/bill/control.php
		control/bob/bill/sue.php || control/bob/bill/sue/control.php

File Routing
	If, for some reason, Route is given a request that has a urlProjectFileToken or a systemPublicFolder prefix, Route will send that file after determining the path through the Route Rules Logic

@note	if you get confused about what is going on with the rules, you can print out both self::$matchedRules and self::$ruleSets at just about any time
@note	dashes in paths will not work with namespacing.  Dashes in the last token will be handled by turning the name of the corresponding local tool into a lower camel cased name.
*/
class Route{
	static $stopRouting;///<stops more rules from being called; use within rule file
	static $urlTokens = array();///<an array of url path parts; rules can change this array
	static $realUrlTokens = array();///<the original array of url path parts
	static $parsedUrlTokens = array();///<used internally
	static $unparsedUrlTokens = array();///<used internally
	static $matchedRules;///<list of rules that were matched
	static $urlBase;///<the string, untokenised, path of the url.  Use $_SERVER to get actual url
	static $currentToken;///<used internally; serves as the item compared on token compared rules
	///parses url, routes it, then calls off all the control until no more or told to stop
	static function handle($uri){
		self::parseRequest($uri);
		
		//url corresponds to public file directory, provide file
		if(self::$urlTokens[0] == $_ENV['urlProjectFileToken']){
			self::sendFile($_ENV['instancePublicFolder']);
		}elseif(self::$urlTokens[0] == $_ENV['urlSystemFileToken']){
			self::sendFile($_ENV['systemPublicFolder']);
		}
		
		self::routeRequest();
	
//+	load controls and section page{
	
		global $control;
		$control = \Control::init();//we are now in the realm of dynamic pages
		
		//after this following line, self::$urlTokens has no more influence on routing.  Modify self::$unparsedUrlTokens if you want modify control flow
		self::$unparsedUrlTokens = array_merge([''],self::$urlTokens);//blank token loads in control
		
		self::addLocalTool($_ENV['projectFolder'].'tool/local/');
		
		//get the section and page control
		while(self::$unparsedUrlTokens){
			$loaded = false;
			self::$currentToken = array_shift(self::$unparsedUrlTokens);
			if(self::$currentToken){//ignore blank tokens
				self::$parsedUrlTokens[] = self::$currentToken;
			}
			
			//++ load the control {
			$path = $_ENV['controlFolder'].implode('/',self::$parsedUrlTokens);
			//if named file, load, otherwise load generic control in directory
			if(is_file($path.'.php')){
				$loaded = \Files::inc($path.'.php',['control'],self::$regexMatch);
			}elseif(is_file($path.'/control.php')){
				$loaded = \Files::inc($path.'/control.php',['control'],self::$regexMatch);
			}
			//++ }
			
			//not loaded and was last token, page not found
			if($loaded === false && !self::$unparsedUrlTokens){
				if($_ENV['pageNotFound']){
					\Config::loadUserFiles($_ENV['pageNotFound'],'control',array('control'));
					exit;
				}else{
					Debug::toss('Request handler encountered unresolvable token at control level.'."\nCurrent token: ".self::$currentToken."\nTokens parsed".print_r(self::$parsedUrlTokens,true));
				}
			}
		}
//+	}
	}
	///find the most specific tool
	private static function addLocalTool($base){
		$tokens = self::$urlTokens;
		while($tokens){
			if(is_file($base.implode('/',$tokens).'.php')){
				break;
			}
			array_pop($tokens);
			$tokens[] = 'local';
			if(is_file($base.implode('/',$tokens).'.php')){
				break;
			}
			array_pop($tokens);
		}
		if($tokens){
			\Control::addLocalTool($tokens);
		}
	}
	///internal use. initial breaking apart of url
	private static function parseRequest($uri){
		self::$realUrlTokens = explode('?',$uri,2);
		self::tokenise(self::$realUrlTokens[0]);
		
		//urldecode tokens.  Note, this can make some things relying on domain path info for file path info insecure
		foreach(self::$urlTokens as &$token){
			$token = urldecode($token);
		}
		unset($token);
		
		//Potentially, the urlTokens will change according to routes, but the real ones may be referenced
		self::$realUrlTokens = self::$urlTokens;
	}
	/// internal use. tokenises url
	/** splits url path on "/"
	@param	urlDir	str	path part of url string
	*/
	static $urlCaselessBase;///<urlBase, but cases removed
	private static function tokenise($urlDir){
		self::$urlTokens = \Tool::explode('/',$urlDir);
		self::$urlBase = $urlDir;
		self::$urlCaselessBase = strtolower($urlDir);
	}
	static $regexMatch=[];
	///internal use. Parses all current files and rules
	/** adds file and rules to ruleSets and parses all active rules in current file and former files
	@param	file	str	file location string
	*/
	private static function matchRules($path,&$rules){
		foreach($rules as $ruleKey=>&$rule){
			unset($matched);
			if(!isset($rule['flags'])){
				$flags = $rule[2] ? explode(',',$rule[2]) : array();
				$rule['flags'] = array_fill_keys(array_values($flags),true);
			
				//parse flags for determining match string
				if($rule['flags']['regex']){
					$rule['match'] = \Tool::pregDelimit($rule[0]);
					if($rule['flags']['caseless']){
						$rule['match'] .= 'i';
					}
					
				}else{
					if($rule['flags']['caseless']){
						$rule['match'] = strtolower($rule[0]);
					}else{
						$rule['match'] = $rule[0];
					}
				}
			}
			
			if($rule['flags']['caseless']){
				$subject = self::$urlCaselessBase;
			}else{
				$subject = self::$urlBase;
			}
			
			//test match
			if($rule['flags']['regex']){
				if(preg_match($rule['match'],$subject,self::$regexMatch)){
					$matched = true;
				}
			}else{
				if($rule['match'] == $subject){
					$matched = true;
				}
			}
			
			if($matched){
				self::$matchedRules[] = $rule;
				//++ apply replacement logic {
				if($rule['flags']['regex']){
					$replacement = preg_replace($rule['match'],$rule[1],self::$urlBase);
				}else{
					$replacement = $rule[1];
				}
				
				//handle redirects
				if($rule['flags']['302']){
					\Http::redirect($replacement,'head',302);
				}
				if($rule['flags']['303']){
					\Http::redirect($replacement,'head',303);
				}
			
				//remake url with replacement
				self::tokenise($replacement);
				self::$parsedUrlTokens = [];
				self::$unparsedUrlTokens = array_merge([''],self::$urlTokens);
				//++ }
				
				//++ apply parse flag {
				if($rule['flags']['once']){
					unset($rules[$ruleKey]);
				}elseif($rule['flags']['file:last']){
					unset(self::$ruleSets[$path]);
				}elseif($rule['flags']['loop:last']){
					self::$unparsedUrlTokens = [];
				}
				//++ }
				
				return true;
			}
		} unset($rule);

		return false;
	}
	
	static $ruleSets;///<files containing rules that have been included
	
	///internal use. Gets files and then applies rules for routing
	private static function routeRequest(){
		self::$unparsedUrlTokens = array_merge([''],self::$urlTokens);
		
		while(self::$unparsedUrlTokens && !self::$stopRouting){
			self::$currentToken = array_shift(self::$unparsedUrlTokens);
			if(self::$currentToken){
				self::$parsedUrlTokens[] = self::$currentToken;
			}
			
			$path = $_ENV['controlFolder'].implode('/',self::$parsedUrlTokens);
			if(!isset(self::$ruleSets[$path])){
				self::$ruleSets[$path] = (array)\Files::inc($path.'/routes.php',null,null,['rules'])['rules'];
			}
			if(!self::$ruleSets[$path]){
				continue;
			}
			//note, on match, matehRules resets unparsedTokens (having the effect of loopiing matchRules over again)
			self::matchRules($path,self::$ruleSets[$path]);
		}
		
		self::$parsedUrlTokens = [];
	}
	///internal use. Gets a file based on next token in the unparsedUrlTokens variable
	private static function getTokenFile($defaultName,$globalize=null,$extract=null){
		$path = $_ENV['controlFolder'].implode('/',self::$parsedUrlTokens);
		//if path not directory, possibly is file
		if(!is_dir($path)){
			$file = $path.'.php';
		}else{
			$file = $path.'/'.$defaultName.'.php';
		}
		return \Files::inc($file,$globalize,null,$extract);
	}
	///internal use. attempts to find non php file and send it to the browser
	private static function sendFile($base){
		array_shift(self::$urlTokens);
		$filePath = escapeshellcmd(implode('/',self::$urlTokens));
		if($filePath == 'index.php'){
			\Config::loadUserFiles($_ENV['pageNotFound'],'control',array('page'));
		}
		$path = $base.$filePath;
		if($_ENV['downloadParamIndicator']){
			$saveAs = $_GET[$_ENV['downloadParamIndicator']] ? $_GET[$_ENV['downloadParamIndicator']] : $_POST[$_ENV['downloadParamIndicator']];
		}
		\View::sendFile($path,$saveAs);
	}
	static function currentPath(){
		return '/'.implode('/',self::$parsedUrlTokens).'/';
	}
}
