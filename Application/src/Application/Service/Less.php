<?php
namespace Application\Service;


/**
 * Class for parsing and compiling less files into css
 *
 * @package Less
 * @subpackage parser
 *
 */
class Less_Parser {


	/**
	 * Default parser options
	 */
	public static $default_options = array(
		'compress'				=> false,			// option - whether to compress
		'strictUnits'			=> false,			// whether units need to evaluate correctly
		'strictMath'			=> false,			// whether math has to be within parenthesis
		'relativeUrls'			=> true,			// option - whether to adjust URL's to be relative
		'urlArgs'				=> array(),			// whether to add args into url tokens
		'numPrecision'			=> 8,

		'import_dirs'			=> array(),
		'import_callback'		=> null,
		'cache_dir'				=> null,
		'cache_method'			=> 'php', 			// false, 'serialize', 'php', 'var_export', 'callback';
		'cache_callback_get'	=> null,
		'cache_callback_set'	=> null,

		'sourceMap'				=> false,			// whether to output a source map
		'sourceMapBasepath'		=> null,
		'sourceMapWriteTo'		=> null,
		'sourceMapURL'			=> null,

		'plugins'				=> array(),

	);

	public static $options = array();


	private $input;					// Less input string
	private $input_len;				// input string length
	private $pos;					// current index in `input`
	private $saveStack = array();	// holds state for backtracking
	private $furthest;

	/**
	 * @var Less_Environment
	 */
	private $env;

	private $rules = array();

	private static $imports = array();

	public static $has_extends = false;

	public static $next_id = 0;

	/**
	 * Filename to contents of all parsed the files
	 *
	 * @var array
	 */
	public static $contentsMap = array();


	/**
	 * @param Less_Environment|array|null $env
	 */
	public function __construct( $env = null ){

		// Top parser on an import tree must be sure there is one "env"
		// which will then be passed around by reference.
		if( $env instanceof Less_Environment ){
			$this->env = $env;
		}else{
			$this->SetOptions(Less_Parser::$default_options);
			$this->Reset( $env );
		}

	}


	/**
	 * Reset the parser state completely
	 *
	 */
	public function Reset( $options = null ){
		$this->rules = array();
		self::$imports = array();
		self::$has_extends = false;
		self::$imports = array();
		self::$contentsMap = array();

		$this->env = new Less_Environment($options);
		$this->env->Init();

		//set new options
		if( is_array($options) ){
			$this->SetOptions(Less_Parser::$default_options);
			$this->SetOptions($options);
		}
	}

	/**
	 * Set one or more compiler options
	 *  options: import_dirs, cache_dir, cache_method
	 *
	 */
	public function SetOptions( $options ){
		foreach($options as $option => $value){
			$this->SetOption($option,$value);
		}
	}

	/**
	 * Set one compiler option
	 *
	 */
	public function SetOption($option,$value){

		switch($option){

			case 'import_dirs':
				$this->SetImportDirs($value);
			return;

			case 'cache_dir':
				if( is_string($value) ){
					LessCache::SetCacheDir($value);
					LessCache::CheckCacheDir();
				}
			return;
		}

		Less_Parser::$options[$option] = $value;
	}

	/**
	 * Registers a new custom function
	 *
	 * @param  string   $name     function name
	 * @param  callable $callback callback
	 */
	public function registerFunction($name, $callback) {
		$this->env->functions[$name] = $callback;
	}

	/**
	 * Removed an already registered function
	 *
	 * @param  string $name function name
	 */
	public function unregisterFunction($name) {
		if( isset($this->env->functions[$name]) )
			unset($this->env->functions[$name]);
	}


	/**
	 * Get the current css buffer
	 *
	 * @return string
	 */
	public function getCss(){

		$precision = ini_get('precision');
		@ini_set('precision',16);
		$locale = setlocale(LC_NUMERIC, 0);
		setlocale(LC_NUMERIC, "C");


 		$root = new Less_Tree_Ruleset(array(), $this->rules );
		$root->root = true;
		$root->firstRoot = true;


		$this->PreVisitors($root);

		self::$has_extends = false;
		$evaldRoot = $root->compile($this->env);



		$this->PostVisitors($evaldRoot);

		if( Less_Parser::$options['sourceMap'] ){
			$generator = new Less_SourceMap_Generator($evaldRoot, Less_Parser::$contentsMap, Less_Parser::$options );
			// will also save file
			// FIXME: should happen somewhere else?
			$css = $generator->generateCSS();
		}else{
			$css = $evaldRoot->toCSS();
		}

		if( Less_Parser::$options['compress'] ){
			$css = preg_replace('/(^(\s)+)|((\s)+$)/', '', $css);
		}

		//reset php settings
		@ini_set('precision',$precision);
		setlocale(LC_NUMERIC, $locale);

		return $css;
	}

	/**
	 * Run pre-compile visitors
	 *
	 */
	private function PreVisitors($root){

		if( Less_Parser::$options['plugins'] ){
			foreach(Less_Parser::$options['plugins'] as $plugin){
				if( !empty($plugin->isPreEvalVisitor) ){
					$plugin->run($root);
				}
			}
		}
	}


	/**
	 * Run post-compile visitors
	 *
	 */
	private function PostVisitors($evaldRoot){

		$visitors = array();
		$visitors[] = new Less_Visitor_joinSelector();
		if( self::$has_extends ){
			$visitors[] = new Less_Visitor_processExtends();
		}
		$visitors[] = new Less_Visitor_toCSS();


		if( Less_Parser::$options['plugins'] ){
			foreach(Less_Parser::$options['plugins'] as $plugin){
				if( property_exists($plugin,'isPreEvalVisitor') && $plugin->isPreEvalVisitor ){
					continue;
				}

				if( property_exists($plugin,'isPreVisitor') && $plugin->isPreVisitor ){
					array_unshift( $visitors, $plugin);
				}else{
					$visitors[] = $plugin;
				}
			}
		}


		for($i = 0; $i < count($visitors); $i++ ){
			$visitors[$i]->run($evaldRoot);
		}

	}


	/**
	 * Parse a Less string into css
	 *
	 * @param string $str The string to convert
	 * @param string $uri_root The url of the file
	 * @return Less_Tree_Ruleset|Less_Parser
	 */
	public function parse( $str, $file_uri = null ){

		if( !$file_uri ){
			$uri_root = '';
			$filename = 'anonymous-file-'.Less_Parser::$next_id++.'.less';
		}else{
			$file_uri = self::WinPath($file_uri);
			$filename = basename($file_uri);
			$uri_root = dirname($file_uri);
		}

		$previousFileInfo = $this->env->currentFileInfo;
		$uri_root = self::WinPath($uri_root);
		$this->SetFileInfo($filename, $uri_root);

		$this->input = $str;
		$this->_parse();

		if( $previousFileInfo ){
			$this->env->currentFileInfo = $previousFileInfo;
		}

		return $this;
	}


	/**
	 * Parse a Less string from a given file
	 *
	 * @throws Less_Exception_Parser
	 * @param string $filename The file to parse
	 * @param string $uri_root The url of the file
	 * @param bool $returnRoot Indicates whether the return value should be a css string a root node
	 * @return Less_Tree_Ruleset|Less_Parser
	 */
	public function parseFile( $filename, $uri_root = '', $returnRoot = false){

		if( !file_exists($filename) ){
			$this->Error(sprintf('File `%s` not found.', $filename));
		}


		// fix uri_root?
		// Instead of The mixture of file path for the first argument and directory path for the second argument has bee
		if( !$returnRoot && !empty($uri_root) && basename($uri_root) == basename($filename) ){
			$uri_root = dirname($uri_root);
		}


		$previousFileInfo = $this->env->currentFileInfo;
		$filename = self::WinPath($filename);
		$uri_root = self::WinPath($uri_root);
		$this->SetFileInfo($filename, $uri_root);

		self::AddParsedFile($filename);

		if( $returnRoot ){
			$rules = $this->GetRules( $filename );
			$return = new Less_Tree_Ruleset(array(), $rules );
		}else{
			$this->_parse( $filename );
			$return = $this;
		}

		if( $previousFileInfo ){
			$this->env->currentFileInfo = $previousFileInfo;
		}

		return $return;
	}


	/**
	 * Allows a user to set variables values
	 * @param array $vars
	 * @return Less_Parser
	 */
	public function ModifyVars( $vars ){

		$this->input = Less_Parser::serializeVars( $vars );
		$this->_parse();

		return $this;
	}


	/**
	 * @param string $filename
	 */
	public function SetFileInfo( $filename, $uri_root = ''){

		$filename = Less_Environment::normalizePath($filename);
		$dirname = preg_replace('/[^\/\\\\]*$/','',$filename);

		if( !empty($uri_root) ){
			$uri_root = rtrim($uri_root,'/').'/';
		}

		$currentFileInfo = array();

		//entry info
		if( isset($this->env->currentFileInfo) ){
			$currentFileInfo['entryPath'] = $this->env->currentFileInfo['entryPath'];
			$currentFileInfo['entryUri'] = $this->env->currentFileInfo['entryUri'];
			$currentFileInfo['rootpath'] = $this->env->currentFileInfo['rootpath'];

		}else{
			$currentFileInfo['entryPath'] = $dirname;
			$currentFileInfo['entryUri'] = $uri_root;
			$currentFileInfo['rootpath'] = $dirname;
		}

		$currentFileInfo['currentDirectory'] = $dirname;
		$currentFileInfo['currentUri'] = $uri_root.basename($filename);
		$currentFileInfo['filename'] = $filename;
		$currentFileInfo['uri_root'] = $uri_root;


		//inherit reference
		if( isset($this->env->currentFileInfo['reference']) && $this->env->currentFileInfo['reference'] ){
			$currentFileInfo['reference'] = true;
		}

		$this->env->currentFileInfo = $currentFileInfo;
	}


	/**
	 * @deprecated 1.5.1.2
	 *
	 */
	public function SetCacheDir( $dir ){

		if( !file_exists($dir) ){
			if( mkdir($dir) ){
				return true;
			}
			throw new \Less_Exception_Parser('Less.php cache directory couldn\'t be created: '.$dir);

		}elseif( !is_dir($dir) ){
			throw new \Less_Exception_Parser('Less.php cache directory doesn\'t exist: '.$dir);

		}elseif( !is_writable($dir) ){
			throw new \Less_Exception_Parser('Less.php cache directory isn\'t writable: '.$dir);

		}else{
			$dir = self::WinPath($dir);
			LessCache::$cache_dir = rtrim($dir,'/').'/';
			return true;
		}
	}


	/**
	 * Set a list of directories or callbacks the parser should use for determining import paths
	 *
	 * @param array $dirs
	 */
	public function SetImportDirs( $dirs ){
		Less_Parser::$options['import_dirs'] = array();

		foreach($dirs as $path => $uri_root){

			$path = self::WinPath($path);
			if( !empty($path) ){
				$path = rtrim($path,'/').'/';
			}

			if ( !is_callable($uri_root) ){
				$uri_root = self::WinPath($uri_root);
				if( !empty($uri_root) ){
					$uri_root = rtrim($uri_root,'/').'/';
				}
			}

			Less_Parser::$options['import_dirs'][$path] = $uri_root;
		}
	}

	/**
	 * @param string $file_path
	 */
	private function _parse( $file_path = null ){
		$this->rules = array_merge($this->rules, $this->GetRules( $file_path ));
	}


	/**
	 * Return the results of parsePrimary for $file_path
	 * Use cache and save cached results if possible
	 *
	 * @param string|null $file_path
	 */
	private function GetRules( $file_path ){

		$this->SetInput($file_path);

		$cache_file = $this->CacheFile( $file_path );
		if( $cache_file ){
			if( Less_Parser::$options['cache_method'] == 'callback' ){
				if( is_callable(Less_Parser::$options['cache_callback_get']) ){
					$cache = call_user_func_array(
						Less_Parser::$options['cache_callback_get'],
						array($this, $file_path, $cache_file)
					);

					if( $cache ){
						$this->UnsetInput();
						return $cache;
					}
				}

			}elseif( file_exists($cache_file) ){
				switch(Less_Parser::$options['cache_method']){

					// Using serialize
					// Faster but uses more memory
					case 'serialize':
						$cache = unserialize(file_get_contents($cache_file));
						if( $cache ){
							touch($cache_file);
							$this->UnsetInput();
							return $cache;
						}
					break;


					// Using generated php code
					case 'var_export':
					case 'php':
					$this->UnsetInput();
					return include($cache_file);
				}
			}
		}

		$rules = $this->parsePrimary();

		if( $this->pos < $this->input_len ){
			throw new \Less_Exception_Chunk($this->input, null, $this->furthest, $this->env->currentFileInfo);
		}

		$this->UnsetInput();


		//save the cache
		if( $cache_file ){
			if( Less_Parser::$options['cache_method'] == 'callback' ){
				if( is_callable(Less_Parser::$options['cache_callback_set']) ){
					call_user_func_array(
						Less_Parser::$options['cache_callback_set'],
						array($this, $file_path, $cache_file, $rules)
					);
				}

			}else{
				//msg('write cache file');
				switch(Less_Parser::$options['cache_method']){
					case 'serialize':
						file_put_contents( $cache_file, serialize($rules) );
					break;
					case 'php':
						file_put_contents( $cache_file, '<?php return '.self::ArgString($rules).'; ?>' );
					break;
					case 'var_export':
						//Requires __set_state()
						file_put_contents( $cache_file, '<?php return '.var_export($rules,true).'; ?>' );
					break;
				}

				LessCache::CleanCache();
			}
		}

		return $rules;
	}


	/**
	 * Set up the input buffer
	 *
	 */
	public function SetInput( $file_path ){

		if( $file_path ){
			$this->input = file_get_contents( $file_path );
		}

		$this->pos = $this->furthest = 0;

		// Remove potential UTF Byte Order Mark
		$this->input = preg_replace('/\\G\xEF\xBB\xBF/', '', $this->input);
		$this->input_len = strlen($this->input);


		if( Less_Parser::$options['sourceMap'] && $this->env->currentFileInfo ){
			$uri = $this->env->currentFileInfo['currentUri'];
			Less_Parser::$contentsMap[$uri] = $this->input;
		}

	}


	/**
	 * Free up some memory
	 *
	 */
	public function UnsetInput(){
		unset($this->input, $this->pos, $this->input_len, $this->furthest);
		$this->saveStack = array();
	}


	public function CacheFile( $file_path ){

		if( $file_path && $this->CacheEnabled() ){

			$env = get_object_vars($this->env);
			unset($env['frames']);

			$parts = array();
			$parts[] = $file_path;
			$parts[] = filesize( $file_path );
			$parts[] = filemtime( $file_path );
			$parts[] = $env;
			$parts[] = Less_Version::cache_version;
			$parts[] = Less_Parser::$options['cache_method'];
			return LessCache::$cache_dir.'lessphp_'.base_convert( sha1(json_encode($parts) ), 16, 36).'.lesscache';
		}
	}


	static function AddParsedFile($file){
		self::$imports[] = $file;
	}

	static function AllParsedFiles(){
		return self::$imports;
	}

	/**
	 * @param string $file
	 */
	static function FileParsed($file){
		return in_array($file,self::$imports);
	}


	function save() {
		$this->saveStack[] = $this->pos;
	}

	private function restore() {
		$this->pos = array_pop($this->saveStack);
	}

	private function forget(){
		array_pop($this->saveStack);
	}


	private function isWhitespace($offset = 0) {
		return preg_match('/\s/',$this->input[ $this->pos + $offset]);
	}

	/**
	 * Parse from a token, regexp or string, and move forward if match
	 *
	 * @param array $toks
	 * @return array
	 */
	private function match($toks){

		// The match is confirmed, add the match length to `this::pos`,
		// and consume any extra white-space characters (' ' || '\n')
		// which come after that. The reason for this is that LeSS's
		// grammar is mostly white-space insensitive.
		//

		foreach($toks as $tok){

			$char = $tok[0];

			if( $char === '/' ){
				$match = $this->MatchReg($tok);

				if( $match ){
					return count($match) === 1 ? $match[0] : $match;
				}

			}elseif( $char === '#' ){
				$match = $this->MatchChar($tok[1]);

			}else{
				// Non-terminal, match using a function call
				$match = $this->$tok();

			}

			if( $match ){
				return $match;
			}
		}
	}

	/**
	 * @param string[] $toks
	 *
	 * @return string
	 */
	private function MatchFuncs($toks){

		if( $this->pos < $this->input_len ){
			foreach($toks as $tok){
				$match = $this->$tok();
				if( $match ){
					return $match;
				}
			}
		}

	}

	// Match a single character in the input,
	private function MatchChar($tok){
		if( ($this->pos < $this->input_len) && ($this->input[$this->pos] === $tok) ){
			$this->skipWhitespace(1);
			return $tok;
		}
	}

	// Match a regexp from the current start point
	private function MatchReg($tok){

		if( preg_match($tok, $this->input, $match, 0, $this->pos) ){
			$this->skipWhitespace(strlen($match[0]));
			return $match;
		}
	}


	/**
	 * Same as match(), but don't change the state of the parser,
	 * just return the match.
	 *
	 * @param string $tok
	 * @return integer
	 */
	public function PeekReg($tok){
		return preg_match($tok, $this->input, $match, 0, $this->pos);
	}

	/**
	 * @param string $tok
	 */
	public function PeekChar($tok){
		//return ($this->input[$this->pos] === $tok );
		return ($this->pos < $this->input_len) && ($this->input[$this->pos] === $tok );
	}


	/**
	 * @param integer $length
	 */
	public function skipWhitespace($length){

		$this->pos += $length;

		for(; $this->pos < $this->input_len; $this->pos++ ){
			$c = $this->input[$this->pos];

			if( ($c !== "\n") && ($c !== "\r") && ($c !== "\t") && ($c !== ' ') ){
				break;
			}
		}
	}


	/**
	 * @param string $tok
	 * @param string|null $msg
	 */
	public function expect($tok, $msg = NULL) {
		$result = $this->match( array($tok) );
		if (!$result) {
			$this->Error( $msg	? "Expected '" . $tok . "' got '" . $this->input[$this->pos] . "'" : $msg );
		} else {
			return $result;
		}
	}

	/**
	 * @param string $tok
	 */
	public function expectChar($tok, $msg = null ){
		$result = $this->MatchChar($tok);
		if( !$result ){
			$this->Error( $msg ? "Expected '" . $tok . "' got '" . $this->input[$this->pos] . "'" : $msg );
		}else{
			return $result;
		}
	}

	//
	// Here in, the parsing rules/functions
	//
	// The basic structure of the syntax tree generated is as follows:
	//
	//   Ruleset ->  Rule -> Value -> Expression -> Entity
	//
	// Here's some LESS code:
	//
	//	.class {
	//	  color: #fff;
	//	  border: 1px solid #000;
	//	  width: @w + 4px;
	//	  > .child {...}
	//	}
	//
	// And here's what the parse tree might look like:
	//
	//	 Ruleset (Selector '.class', [
	//		 Rule ("color",  Value ([Expression [Color #fff]]))
	//		 Rule ("border", Value ([Expression [Dimension 1px][Keyword "solid"][Color #000]]))
	//		 Rule ("width",  Value ([Expression [Operation "+" [Variable "@w"][Dimension 4px]]]))
	//		 Ruleset (Selector [Element '>', '.child'], [...])
	//	 ])
	//
	//  In general, most rules will try to parse a token with the `$()` function, and if the return
	//  value is truly, will return a new node, of the relevant type. Sometimes, we need to check
	//  first, before parsing, that's when we use `peek()`.
	//

	//
	// The `primary` rule is the *entry* and *exit* point of the parser.
	// The rules here can appear at any level of the parse tree.
	//
	// The recursive nature of the grammar is an interplay between the `block`
	// rule, which represents `{ ... }`, the `ruleset` rule, and this `primary` rule,
	// as represented by this simplified grammar:
	//
	//	 primary  →  (ruleset | rule)+
	//	 ruleset  →  selector+ block
	//	 block	→  '{' primary '}'
	//
	// Only at one point is the primary rule not called from the
	// block rule: at the root level.
	//
	private function parsePrimary(){
		$root = array();

		while( true ){

			if( $this->pos >= $this->input_len ){
				break;
			}

			$node = $this->parseExtend(true);
			if( $node ){
				$root = array_merge($root,$node);
				continue;
			}

			//$node = $this->MatchFuncs( array( 'parseMixinDefinition', 'parseRule', 'parseRuleset', 'parseMixinCall', 'parseComment', 'parseDirective'));
			$node = $this->MatchFuncs( array( 'parseMixinDefinition', 'parseNameValue', 'parseRule', 'parseRuleset', 'parseMixinCall', 'parseComment', 'parseRulesetCall', 'parseDirective'));

			if( $node ){
				$root[] = $node;
			}elseif( !$this->MatchReg('/\\G[\s\n;]+/') ){
				break;
			}

            if( $this->PeekChar('}') ){
				break;
			}
		}

		return $root;
	}



	// We create a Comment node for CSS comments `/* */`,
	// but keep the LeSS comments `//` silent, by just skipping
	// over them.
	private function parseComment(){

		if( $this->input[$this->pos] !== '/' ){
			return;
		}

		if( $this->input[$this->pos+1] === '/' ){
			$match = $this->MatchReg('/\\G\/\/.*/');
			return $this->NewObj4('Less_Tree_Comment',array($match[0], true, $this->pos, $this->env->currentFileInfo));
		}

		//$comment = $this->MatchReg('/\\G\/\*(?:[^*]|\*+[^\/*])*\*+\/\n?/');
		$comment = $this->MatchReg('/\\G\/\*(?s).*?\*+\/\n?/');//not the same as less.js to prevent fatal errors
		if( $comment ){
			return $this->NewObj4('Less_Tree_Comment',array($comment[0], false, $this->pos, $this->env->currentFileInfo));
		}
	}

	private function parseComments(){
		$comments = array();

		while( $this->pos < $this->input_len ){
			$comment = $this->parseComment();
			if( !$comment ){
				break;
			}

			$comments[] = $comment;
		}

		return $comments;
	}



	//
	// A string, which supports escaping " and '
	//
	//	 "milky way" 'he\'s the one!'
	//
	private function parseEntitiesQuoted() {
		$j = $this->pos;
		$e = false;
		$index = $this->pos;

		if( $this->input[$this->pos] === '~' ){
			$j++;
			$e = true; // Escaped strings
		}

		if( $this->input[$j] != '"' && $this->input[$j] !== "'" ){
			return;
		}

		if ($e) {
			$this->MatchChar('~');
		}

                // Fix for #124: match escaped newlines
                //$str = $this->MatchReg('/\\G"((?:[^"\\\\\r\n]|\\\\.)*)"|\'((?:[^\'\\\\\r\n]|\\\\.)*)\'/');
		$str = $this->MatchReg('/\\G"((?:[^"\\\\\r\n]|\\\\.|\\\\\r\n|\\\\[\n\r\f])*)"|\'((?:[^\'\\\\\r\n]|\\\\.|\\\\\r\n|\\\\[\n\r\f])*)\'/');

		if( $str ){
			$result = $str[0][0] == '"' ? $str[1] : $str[2];
			return $this->NewObj5('Less_Tree_Quoted',array($str[0], $result, $e, $index, $this->env->currentFileInfo) );
		}
		return;
	}


	//
	// A catch-all word, such as:
	//
	//	 black border-collapse
	//
	private function parseEntitiesKeyword(){

		//$k = $this->MatchReg('/\\G[_A-Za-z-][_A-Za-z0-9-]*/');
		$k = $this->MatchReg('/\\G%|\\G[_A-Za-z-][_A-Za-z0-9-]*/');
		if( $k ){
			$k = $k[0];
			$color = $this->fromKeyword($k);
			if( $color ){
				return $color;
			}
			return $this->NewObj1('Less_Tree_Keyword',$k);
		}
	}

	// duplicate of Less_Tree_Color::FromKeyword
	private function FromKeyword( $keyword ){
		$keyword = strtolower($keyword);

		if( Less_Colors::hasOwnProperty($keyword) ){
			// detect named color
			return $this->NewObj1('Less_Tree_Color',substr(Less_Colors::color($keyword), 1));
		}

		if( $keyword === 'transparent' ){
			return $this->NewObj3('Less_Tree_Color', array( array(0, 0, 0), 0, true));
		}
	}

	//
	// A function call
	//
	//	 rgb(255, 0, 255)
	//
	// We also try to catch IE's `alpha()`, but let the `alpha` parser
	// deal with the details.
	//
	// The arguments are parsed with the `entities.arguments` parser.
	//
	private function parseEntitiesCall(){
		$index = $this->pos;

		if( !preg_match('/\\G([\w-]+|%|progid:[\w\.]+)\(/', $this->input, $name,0,$this->pos) ){
			return;
		}
		$name = $name[1];
		$nameLC = strtolower($name);

		if ($nameLC === 'url') {
			return null;
		}

		$this->pos += strlen($name);

		if( $nameLC === 'alpha' ){
			$alpha_ret = $this->parseAlpha();
			if( $alpha_ret ){
				return $alpha_ret;
			}
		}

		$this->MatchChar('('); // Parse the '(' and consume whitespace.

		$args = $this->parseEntitiesArguments();

		if( !$this->MatchChar(')') ){
			return;
		}

		if ($name) {
			return $this->NewObj4('Less_Tree_Call',array($name, $args, $index, $this->env->currentFileInfo) );
		}
	}

	/**
	 * Parse a list of arguments
	 *
	 * @return array
	 */
	private function parseEntitiesArguments(){

		$args = array();
		while( true ){
			$arg = $this->MatchFuncs( array('parseEntitiesAssignment','parseExpression') );
			if( !$arg ){
				break;
			}

			$args[] = $arg;
			if( !$this->MatchChar(',') ){
				break;
			}
		}
		return $args;
	}

	private function parseEntitiesLiteral(){
		return $this->MatchFuncs( array('parseEntitiesDimension','parseEntitiesColor','parseEntitiesQuoted','parseUnicodeDescriptor') );
	}

	// Assignments are argument entities for calls.
	// They are present in ie filter properties as shown below.
	//
	//	 filter: progid:DXImageTransform.Microsoft.Alpha( *opacity=50* )
	//
	private function parseEntitiesAssignment() {

		$key = $this->MatchReg('/\\G\w+(?=\s?=)/');
		if( !$key ){
			return;
		}

		if( !$this->MatchChar('=') ){
			return;
		}

		$value = $this->parseEntity();
		if( $value ){
			return $this->NewObj2('Less_Tree_Assignment',array($key[0], $value));
		}
	}

	//
	// Parse url() tokens
	//
	// We use a specific rule for urls, because they don't really behave like
	// standard function calls. The difference is that the argument doesn't have
	// to be enclosed within a string, so it can't be parsed as an Expression.
	//
	private function parseEntitiesUrl(){


		if( $this->input[$this->pos] !== 'u' || !$this->matchReg('/\\Gurl\(/') ){
			return;
		}

		$value = $this->match( array('parseEntitiesQuoted','parseEntitiesVariable','/\\Gdata\:.*?[^\)]+/','/\\G(?:(?:\\\\[\(\)\'"])|[^\(\)\'"])+/') );
		if( !$value ){
			$value = '';
		}


		$this->expectChar(')');


		if( isset($value->value) || $value instanceof Less_Tree_Variable ){
			return $this->NewObj2('Less_Tree_Url',array($value, $this->env->currentFileInfo));
		}

		return $this->NewObj2('Less_Tree_Url', array( $this->NewObj1('Less_Tree_Anonymous',$value), $this->env->currentFileInfo) );
	}


	//
	// A Variable entity, such as `@fink`, in
	//
	//	 width: @fink + 2px
	//
	// We use a different parser for variable definitions,
	// see `parsers.variable`.
	//
	private function parseEntitiesVariable(){
		$index = $this->pos;
		if ($this->PeekChar('@') && ($name = $this->MatchReg('/\\G@@?[\w-]+/'))) {
			return $this->NewObj3('Less_Tree_Variable', array( $name[0], $index, $this->env->currentFileInfo));
		}
	}


	// A variable entity useing the protective {} e.g. @{var}
	private function parseEntitiesVariableCurly() {
		$index = $this->pos;

		if( $this->input_len > ($this->pos+1) && $this->input[$this->pos] === '@' && ($curly = $this->MatchReg('/\\G@\{([\w-]+)\}/')) ){
			return $this->NewObj3('Less_Tree_Variable',array('@'.$curly[1], $index, $this->env->currentFileInfo));
		}
	}

	//
	// A Hexadecimal color
	//
	//	 #4F3C2F
	//
	// `rgb` and `hsl` colors are parsed through the `entities.call` parser.
	//
	private function parseEntitiesColor(){
		if ($this->PeekChar('#') && ($rgb = $this->MatchReg('/\\G#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})/'))) {
			return $this->NewObj1('Less_Tree_Color',$rgb[1]);
		}
	}

	//
	// A Dimension, that is, a number and a unit
	//
	//	 0.5em 95%
	//
	private function parseEntitiesDimension(){

		$c = @ord($this->input[$this->pos]);

		//Is the first char of the dimension 0-9, '.', '+' or '-'
		if (($c > 57 || $c < 43) || $c === 47 || $c == 44){
			return;
		}

		$value = $this->MatchReg('/\\G([+-]?\d*\.?\d+)(%|[a-z]+)?/');
		if( $value ){

			if( isset($value[2]) ){
				return $this->NewObj2('Less_Tree_Dimension', array($value[1],$value[2]));
			}
			return $this->NewObj1('Less_Tree_Dimension',$value[1]);
		}
	}


	//
	// A unicode descriptor, as is used in unicode-range
	//
	// U+0?? or U+00A1-00A9
	//
	function parseUnicodeDescriptor() {
		$ud = $this->MatchReg('/\\G(U\+[0-9a-fA-F?]+)(\-[0-9a-fA-F?]+)?/');
		if( $ud ){
			return $this->NewObj1('Less_Tree_UnicodeDescriptor', $ud[0]);
		}
	}


	//
	// JavaScript code to be evaluated
	//
	//	 `window.location.href`
	//
	private function parseEntitiesJavascript(){
		$e = false;
		$j = $this->pos;
		if( $this->input[$j] === '~' ){
			$j++;
			$e = true;
		}
		if( $this->input[$j] !== '`' ){
			return;
		}
		if( $e ){
			$this->MatchChar('~');
		}
		$str = $this->MatchReg('/\\G`([^`]*)`/');
		if( $str ){
			return $this->NewObj3('Less_Tree_Javascript', array($str[1], $this->pos, $e));
		}
	}


	//
	// The variable part of a variable definition. Used in the `rule` parser
	//
	//	 @fink:
	//
	private function parseVariable(){
		if ($this->PeekChar('@') && ($name = $this->MatchReg('/\\G(@[\w-]+)\s*:/'))) {
			return $name[1];
		}
	}


	//
	// The variable part of a variable definition. Used in the `rule` parser
	//
	// @fink();
	//
	private function parseRulesetCall(){

		if( $this->input[$this->pos] === '@' && ($name = $this->MatchReg('/\\G(@[\w-]+)\s*\(\s*\)\s*;/')) ){
			return $this->NewObj1('Less_Tree_RulesetCall', $name[1] );
		}
	}


	//
	// extend syntax - used to extend selectors
	//
	function parseExtend($isRule = false){

		$index = $this->pos;
		$extendList = array();


		if( !$this->MatchReg( $isRule ? '/\\G&:extend\(/' : '/\\G:extend\(/' ) ){ return; }

		do{
			$option = null;
			$elements = array();
			while( true ){
				$option = $this->MatchReg('/\\G(all)(?=\s*(\)|,))/');
				if( $option ){ break; }
				$e = $this->parseElement();
				if( !$e ){ break; }
				$elements[] = $e;
			}

			if( $option ){
				$option = $option[1];
			}

			$extendList[] = $this->NewObj3('Less_Tree_Extend', array( $this->NewObj1('Less_Tree_Selector',$elements), $option, $index ));

		}while( $this->MatchChar(",") );

		$this->expect('/\\G\)/');

		if( $isRule ){
			$this->expect('/\\G;/');
		}

		return $extendList;
	}


	//
	// A Mixin call, with an optional argument list
	//
	//	 #mixins > .square(#fff);
	//	 .rounded(4px, black);
	//	 .button;
	//
	// The `while` loop is there because mixins can be
	// namespaced, but we only support the child and descendant
	// selector for now.
	//
	private function parseMixinCall(){

		$char = $this->input[$this->pos];
		if( $char !== '.' && $char !== '#' ){
			return;
		}

		$index = $this->pos;
		$this->save(); // stop us absorbing part of an invalid selector

		$elements = $this->parseMixinCallElements();

		if( $elements ){

			if( $this->MatchChar('(') ){
				$returned = $this->parseMixinArgs(true);
				$args = $returned['args'];
				$this->expectChar(')');
			}else{
				$args = array();
			}

			$important = $this->parseImportant();

			if( $this->parseEnd() ){
				$this->forget();
				return $this->NewObj5('Less_Tree_Mixin_Call', array( $elements, $args, $index, $this->env->currentFileInfo, $important));
			}
		}

		$this->restore();
	}


	private function parseMixinCallElements(){
		$elements = array();
		$c = null;

		while( true ){
			$elemIndex = $this->pos;
			$e = $this->MatchReg('/\\G[#.](?:[\w-]|\\\\(?:[A-Fa-f0-9]{1,6} ?|[^A-Fa-f0-9]))+/');
			if( !$e ){
				break;
			}
			$elements[] = $this->NewObj4('Less_Tree_Element', array($c, $e[0], $elemIndex, $this->env->currentFileInfo));
			$c = $this->MatchChar('>');
		}

		return $elements;
	}



	/**
	 * @param boolean $isCall
	 */
	private function parseMixinArgs( $isCall ){
		$expressions = array();
		$argsSemiColon = array();
		$isSemiColonSeperated = null;
		$argsComma = array();
		$expressionContainsNamed = null;
		$name = null;
		$returner = array('args'=>array(), 'variadic'=> false);

		$this->save();

		while( true ){
			if( $isCall ){
				$arg = $this->MatchFuncs( array( 'parseDetachedRuleset','parseExpression' ) );
			} else {
				$this->parseComments();
				if( $this->input[ $this->pos ] === '.' && $this->MatchReg('/\\G\.{3}/') ){
					$returner['variadic'] = true;
					if( $this->MatchChar(";") && !$isSemiColonSeperated ){
						$isSemiColonSeperated = true;
					}

					if( $isSemiColonSeperated ){
						$argsSemiColon[] = array('variadic'=>true);
					}else{
						$argsComma[] = array('variadic'=>true);
					}
					break;
				}
				$arg = $this->MatchFuncs( array('parseEntitiesVariable','parseEntitiesLiteral','parseEntitiesKeyword') );
			}

			if( !$arg ){
				break;
			}


			$nameLoop = null;
			if( $arg instanceof Less_Tree_Expression ){
				$arg->throwAwayComments();
			}
			$value = $arg;
			$val = null;

			if( $isCall ){
				// Variable
				if( property_exists($arg,'value') && count($arg->value) == 1 ){
					$val = $arg->value[0];
				}
			} else {
				$val = $arg;
			}


			if( $val instanceof Less_Tree_Variable ){

				if( $this->MatchChar(':') ){
					if( $expressions ){
						if( $isSemiColonSeperated ){
							$this->Error('Cannot mix ; and , as delimiter types');
						}
						$expressionContainsNamed = true;
					}

					// we do not support setting a ruleset as a default variable - it doesn't make sense
					// However if we do want to add it, there is nothing blocking it, just don't error
					// and remove isCall dependency below
					$value = null;
					if( $isCall ){
						$value = $this->parseDetachedRuleset();
					}
					if( !$value ){
						$value = $this->parseExpression();
					}

					if( !$value ){
						if( $isCall ){
							$this->Error('could not understand value for named argument');
						} else {
							$this->restore();
							$returner['args'] = array();
							return $returner;
						}
					}

					$nameLoop = ($name = $val->name);
				}elseif( !$isCall && $this->MatchReg('/\\G\.{3}/') ){
					$returner['variadic'] = true;
					if( $this->MatchChar(";") && !$isSemiColonSeperated ){
						$isSemiColonSeperated = true;
					}
					if( $isSemiColonSeperated ){
						$argsSemiColon[] = array('name'=> $arg->name, 'variadic' => true);
					}else{
						$argsComma[] = array('name'=> $arg->name, 'variadic' => true);
					}
					break;
				}elseif( !$isCall ){
					$name = $nameLoop = $val->name;
					$value = null;
				}
			}

			if( $value ){
				$expressions[] = $value;
			}

			$argsComma[] = array('name'=>$nameLoop, 'value'=>$value );

			if( $this->MatchChar(',') ){
				continue;
			}

			if( $this->MatchChar(';') || $isSemiColonSeperated ){

				if( $expressionContainsNamed ){
					$this->Error('Cannot mix ; and , as delimiter types');
				}

				$isSemiColonSeperated = true;

				if( count($expressions) > 1 ){
					$value = $this->NewObj1('Less_Tree_Value', $expressions);
				}
				$argsSemiColon[] = array('name'=>$name, 'value'=>$value );

				$name = null;
				$expressions = array();
				$expressionContainsNamed = false;
			}
		}

		$this->forget();
		$returner['args'] = ($isSemiColonSeperated ? $argsSemiColon : $argsComma);
		return $returner;
	}



	//
	// A Mixin definition, with a list of parameters
	//
	//	 .rounded (@radius: 2px, @color) {
	//		...
	//	 }
	//
	// Until we have a finer grained state-machine, we have to
	// do a look-ahead, to make sure we don't have a mixin call.
	// See the `rule` function for more information.
	//
	// We start by matching `.rounded (`, and then proceed on to
	// the argument list, which has optional default values.
	// We store the parameters in `params`, with a `value` key,
	// if there is a value, such as in the case of `@radius`.
	//
	// Once we've got our params list, and a closing `)`, we parse
	// the `{...}` block.
	//
	private function parseMixinDefinition(){
		$cond = null;

		$char = $this->input[$this->pos];
		if( ($char !== '.' && $char !== '#') || ($char === '{' && $this->PeekReg('/\\G[^{]*\}/')) ){
			return;
		}

		$this->save();

		$match = $this->MatchReg('/\\G([#.](?:[\w-]|\\\(?:[A-Fa-f0-9]{1,6} ?|[^A-Fa-f0-9]))+)\s*\(/');
		if( $match ){
			$name = $match[1];

			$argInfo = $this->parseMixinArgs( false );
			$params = $argInfo['args'];
			$variadic = $argInfo['variadic'];


			// .mixincall("@{a}");
			// looks a bit like a mixin definition..
			// also
			// .mixincall(@a: {rule: set;});
			// so we have to be nice and restore
			if( !$this->MatchChar(')') ){
				$this->furthest = $this->pos;
				$this->restore();
				return;
			}


			$this->parseComments();

			if ($this->MatchReg('/\\Gwhen/')) { // Guard
				$cond = $this->expect('parseConditions', 'Expected conditions');
			}

			$ruleset = $this->parseBlock();

			if( is_array($ruleset) ){
				$this->forget();
				return $this->NewObj5('Less_Tree_Mixin_Definition', array( $name, $params, $ruleset, $cond, $variadic));
			}

			$this->restore();
		}else{
			$this->forget();
		}
	}

	//
	// Entities are the smallest recognized token,
	// and can be found inside a rule's value.
	//
	private function parseEntity(){

		return $this->MatchFuncs( array('parseEntitiesLiteral','parseEntitiesVariable','parseEntitiesUrl','parseEntitiesCall','parseEntitiesKeyword','parseEntitiesJavascript','parseComment') );
	}

	//
	// A Rule terminator. Note that we use `peek()` to check for '}',
	// because the `block` rule will be expecting it, but we still need to make sure
	// it's there, if ';' was ommitted.
	//
	private function parseEnd(){
		return $this->MatchChar(';') || $this->PeekChar('}');
	}

	//
	// IE's alpha function
	//
	//	 alpha(opacity=88)
	//
	private function parseAlpha(){

		if ( ! $this->MatchReg('/\\G\(opacity=/i')) {
			return;
		}

		$value = $this->MatchReg('/\\G[0-9]+/');
		if( $value ){
			$value = $value[0];
		}else{
			$value = $this->parseEntitiesVariable();
			if( !$value ){
				return;
			}
		}

		$this->expectChar(')');
		return $this->NewObj1('Less_Tree_Alpha',$value);
	}


	//
	// A Selector Element
	//
	//	 div
	//	 + h1
	//	 #socks
	//	 input[type="text"]
	//
	// Elements are the building blocks for Selectors,
	// they are made out of a `Combinator` (see combinator rule),
	// and an element name, such as a tag a class, or `*`.
	//
	private function parseElement(){
		$c = $this->parseCombinator();
		$index = $this->pos;

		$e = $this->match( array('/\\G(?:\d+\.\d+|\d+)%/', '/\\G(?:[.#]?|:*)(?:[\w-]|[^\x00-\x9f]|\\\\(?:[A-Fa-f0-9]{1,6} ?|[^A-Fa-f0-9]))+/',
			'#*', '#&', 'parseAttribute', '/\\G\([^()@]+\)/', '/\\G[\.#](?=@)/', 'parseEntitiesVariableCurly') );

		if( is_null($e) ){
			$this->save();
			if( $this->MatchChar('(') ){
				if( ($v = $this->parseSelector()) && $this->MatchChar(')') ){
					$e = $this->NewObj1('Less_Tree_Paren',$v);
					$this->forget();
				}else{
					$this->restore();
				}
			}else{
				$this->forget();
			}
		}

		if( !is_null($e) ){
			return $this->NewObj4('Less_Tree_Element',array( $c, $e, $index, $this->env->currentFileInfo));
		}
	}

	//
	// Combinators combine elements together, in a Selector.
	//
	// Because our parser isn't white-space sensitive, special care
	// has to be taken, when parsing the descendant combinator, ` `,
	// as it's an empty space. We have to check the previous character
	// in the input, to see if it's a ` ` character.
	//
	private function parseCombinator(){
		if( $this->pos < $this->input_len ){
			$c = $this->input[$this->pos];
			if ($c === '>' || $c === '+' || $c === '~' || $c === '|' || $c === '^' ){

				$this->pos++;
				if( $this->input[$this->pos] === '^' ){
					$c = '^^';
					$this->pos++;
				}

				$this->skipWhitespace(0);

				return $c;
			}

			if( $this->pos > 0 && $this->isWhitespace(-1) ){
				return ' ';
			}
		}
	}

	//
	// A CSS selector (see selector below)
	// with less extensions e.g. the ability to extend and guard
	//
	private function parseLessSelector(){
		return $this->parseSelector(true);
	}

	//
	// A CSS Selector
	//
	//	 .class > div + h1
	//	 li a:hover
	//
	// Selectors are made out of one or more Elements, see above.
	//
	private function parseSelector( $isLess = false ){
		$elements = array();
		$extendList = array();
		$condition = null;
		$when = false;
		$extend = false;
		$e = null;
		$c = null;
		$index = $this->pos;

		while( ($isLess && ($extend = $this->parseExtend())) || ($isLess && ($when = $this->MatchReg('/\\Gwhen/') )) || ($e = $this->parseElement()) ){
			if( $when ){
				$condition = $this->expect('parseConditions', 'expected condition');
			}elseif( $condition ){
				//error("CSS guard can only be used at the end of selector");
			}elseif( $extend ){
				$extendList = array_merge($extendList,$extend);
			}else{
				//if( count($extendList) ){
					//error("Extend can only be used at the end of selector");
				//}
				if( $this->pos < $this->input_len ){
					$c = $this->input[ $this->pos ];
				}
				$elements[] = $e;
				$e = null;
			}

			if( $c === '{' || $c === '}' || $c === ';' || $c === ',' || $c === ')') { break; }
		}

		if( $elements ){
			return $this->NewObj5('Less_Tree_Selector',array($elements, $extendList, $condition, $index, $this->env->currentFileInfo));
		}
		if( $extendList ) {
			$this->Error('Extend must be used to extend a selector, it cannot be used on its own');
		}
	}

	private function parseTag(){
		return ( $tag = $this->MatchReg('/\\G[A-Za-z][A-Za-z-]*[0-9]?/') ) ? $tag : $this->MatchChar('*');
	}

	private function parseAttribute(){

		$val = null;

		if( !$this->MatchChar('[') ){
			return;
		}

		$key = $this->parseEntitiesVariableCurly();
		if( !$key ){
			$key = $this->expect('/\\G(?:[_A-Za-z0-9-\*]*\|)?(?:[_A-Za-z0-9-]|\\\\.)+/');
		}

		$op = $this->MatchReg('/\\G[|~*$^]?=/');
		if( $op ){
			$val = $this->match( array('parseEntitiesQuoted','/\\G[0-9]+%/','/\\G[\w-]+/','parseEntitiesVariableCurly') );
		}

		$this->expectChar(']');

		return $this->NewObj3('Less_Tree_Attribute',array( $key, $op[0], $val));
	}

	//
	// The `block` rule is used by `ruleset` and `mixin.definition`.
	// It's a wrapper around the `primary` rule, with added `{}`.
	//
	private function parseBlock(){
		if( $this->MatchChar('{') ){
			$content = $this->parsePrimary();
			if( $this->MatchChar('}') ){
				return $content;
			}
		}
	}

	private function parseBlockRuleset(){
		$block = $this->parseBlock();

		if( $block ){
			$block = $this->NewObj2('Less_Tree_Ruleset',array( null, $block));
		}

		return $block;
	}

	private function parseDetachedRuleset(){
		$blockRuleset = $this->parseBlockRuleset();
		if( $blockRuleset ){
			return $this->NewObj1('Less_Tree_DetachedRuleset',$blockRuleset);
		}
	}

	//
	// div, .class, body > p {...}
	//
	private function parseRuleset(){
		$selectors = array();

		$this->save();

		while( true ){
			$s = $this->parseLessSelector();
			if( !$s ){
				break;
			}
			$selectors[] = $s;
			$this->parseComments();

			if( $s->condition && count($selectors) > 1 ){
				$this->Error('Guards are only currently allowed on a single selector.');
			}

			if( !$this->MatchChar(',') ){
				break;
			}
			if( $s->condition ){
				$this->Error('Guards are only currently allowed on a single selector.');
			}
			$this->parseComments();
		}


		if( $selectors ){
			$rules = $this->parseBlock();
			if( is_array($rules) ){
				$this->forget();
				return $this->NewObj2('Less_Tree_Ruleset',array( $selectors, $rules)); //Less_Environment::$strictImports
			}
		}

		// Backtrack
		$this->furthest = $this->pos;
		$this->restore();
	}

	/**
	 * Custom less.php parse function for finding simple name-value css pairs
	 * ex: width:100px;
	 *
	 */
	private function parseNameValue(){

		$index = $this->pos;
		$this->save();


		//$match = $this->MatchReg('/\\G([a-zA-Z\-]+)\s*:\s*((?:\'")?[a-zA-Z0-9\-% \.,!]+?(?:\'")?)\s*([;}])/');
		$match = $this->MatchReg('/\\G([a-zA-Z\-]+)\s*:\s*([\'"]?[#a-zA-Z0-9\-%\.,]+?[\'"]?) *(! *important)?\s*([;}])/');
		if( $match ){

			if( $match[4] == '}' ){
				$this->pos = $index + strlen($match[0])-1;
			}

			if( $match[3] ){
				$match[2] .= ' !important';
			}

			return $this->NewObj4('Less_Tree_NameValue',array( $match[1], $match[2], $index, $this->env->currentFileInfo));
		}

		$this->restore();
	}


	private function parseRule( $tryAnonymous = null ){

		$merge = false;
		$startOfRule = $this->pos;

		$c = $this->input[$this->pos];
		if( $c === '.' || $c === '#' || $c === '&' ){
			return;
		}

		$this->save();
		$name = $this->MatchFuncs( array('parseVariable','parseRuleProperty'));

		if( $name ){

			$isVariable = is_string($name);

			$value = null;
			if( $isVariable ){
				$value = $this->parseDetachedRuleset();
			}

			$important = null;
			if( !$value ){

				// prefer to try to parse first if its a variable or we are compressing
				// but always fallback on the other one
				//if( !$tryAnonymous && is_string($name) && $name[0] === '@' ){
				if( !$tryAnonymous && (Less_Parser::$options['compress'] || $isVariable) ){
					$value = $this->MatchFuncs( array('parseValue','parseAnonymousValue'));
				}else{
					$value = $this->MatchFuncs( array('parseAnonymousValue','parseValue'));
				}

				$important = $this->parseImportant();

				// a name returned by this.ruleProperty() is always an array of the form:
				// [string-1, ..., string-n, ""] or [string-1, ..., string-n, "+"]
				// where each item is a tree.Keyword or tree.Variable
				if( !$isVariable && is_array($name) ){
					$nm = array_pop($name);
					if( $nm->value ){
						$merge = $nm->value;
					}
				}
			}


			if( $value && $this->parseEnd() ){
				$this->forget();
				return $this->NewObj6('Less_Tree_Rule',array( $name, $value, $important, $merge, $startOfRule, $this->env->currentFileInfo));
			}else{
				$this->furthest = $this->pos;
				$this->restore();
				if( $value && !$tryAnonymous ){
					return $this->parseRule(true);
				}
			}
		}else{
			$this->forget();
		}
	}

	function parseAnonymousValue(){

		if( preg_match('/\\G([^@+\/\'"*`(;{}-]*);/',$this->input, $match, 0, $this->pos) ){
			$this->pos += strlen($match[1]);
			return $this->NewObj1('Less_Tree_Anonymous',$match[1]);
		}
	}

	//
	// An @import directive
	//
	//	 @import "lib";
	//
	// Depending on our environment, importing is done differently:
	// In the browser, it's an XHR request, in Node, it would be a
	// file-system operation. The function used for importing is
	// stored in `import`, which we pass to the Import constructor.
	//
	private function parseImport(){

		$this->save();

		$dir = $this->MatchReg('/\\G@import?\s+/');

		if( $dir ){
			$options = $this->parseImportOptions();
			$path = $this->MatchFuncs( array('parseEntitiesQuoted','parseEntitiesUrl'));

			if( $path ){
				$features = $this->parseMediaFeatures();
				if( $this->MatchChar(';') ){
					if( $features ){
						$features = $this->NewObj1('Less_Tree_Value',$features);
					}

					$this->forget();
					return $this->NewObj5('Less_Tree_Import',array( $path, $features, $options, $this->pos, $this->env->currentFileInfo));
				}
			}
		}

		$this->restore();
	}

	private function parseImportOptions(){

		$options = array();

		// list of options, surrounded by parens
		if( !$this->MatchChar('(') ){
			return $options;
		}
		do{
			$optionName = $this->parseImportOption();
			if( $optionName ){
				$value = true;
				switch( $optionName ){
					case "css":
						$optionName = "less";
						$value = false;
					break;
					case "once":
						$optionName = "multiple";
						$value = false;
					break;
				}
				$options[$optionName] = $value;
				if( !$this->MatchChar(',') ){ break; }
			}
		}while( $optionName );
		$this->expectChar(')');
		return $options;
	}

	private function parseImportOption(){
		$opt = $this->MatchReg('/\\G(less|css|multiple|once|inline|reference)/');
		if( $opt ){
			return $opt[1];
		}
	}

	private function parseMediaFeature() {
		$nodes = array();

		do{
			$e = $this->MatchFuncs(array('parseEntitiesKeyword','parseEntitiesVariable'));
			if( $e ){
				$nodes[] = $e;
			} elseif ($this->MatchChar('(')) {
				$p = $this->parseProperty();
				$e = $this->parseValue();
				if ($this->MatchChar(')')) {
					if ($p && $e) {
						$r = $this->NewObj7('Less_Tree_Rule', array( $p, $e, null, null, $this->pos, $this->env->currentFileInfo, true));
						$nodes[] = $this->NewObj1('Less_Tree_Paren',$r);
					} elseif ($e) {
						$nodes[] = $this->NewObj1('Less_Tree_Paren',$e);
					} else {
						return null;
					}
				} else
					return null;
			}
		} while ($e);

		if ($nodes) {
			return $this->NewObj1('Less_Tree_Expression',$nodes);
		}
	}

	private function parseMediaFeatures() {
		$features = array();

		do{
			$e = $this->parseMediaFeature();
			if( $e ){
				$features[] = $e;
				if (!$this->MatchChar(',')) break;
			}else{
				$e = $this->parseEntitiesVariable();
				if( $e ){
					$features[] = $e;
					if (!$this->MatchChar(',')) break;
				}
			}
		} while ($e);

		return $features ? $features : null;
	}

	private function parseMedia() {
		if( $this->MatchReg('/\\G@media/') ){
			$features = $this->parseMediaFeatures();
			$rules = $this->parseBlock();

			if( is_array($rules) ){
				return $this->NewObj4('Less_Tree_Media',array( $rules, $features, $this->pos, $this->env->currentFileInfo));
			}
		}
	}


	//
	// A CSS Directive
	//
	// @charset "utf-8";
	//
	private function parseDirective(){

		if( !$this->PeekChar('@') ){
			return;
		}

		$rules = null;
		$index = $this->pos;
		$hasBlock = true;
		$hasIdentifier = false;
		$hasExpression = false;
		$hasUnknown = false;


		$value = $this->MatchFuncs(array('parseImport','parseMedia'));
		if( $value ){
			return $value;
		}

		$this->save();

		$name = $this->MatchReg('/\\G@[a-z-]+/');

		if( !$name ) return;
		$name = $name[0];


		$nonVendorSpecificName = $name;
		$pos = strpos($name,'-', 2);
		if( $name[1] == '-' && $pos > 0 ){
			$nonVendorSpecificName = "@" . substr($name, $pos + 1);
		}


		switch( $nonVendorSpecificName ){
			/*
			case "@font-face":
			case "@viewport":
			case "@top-left":
			case "@top-left-corner":
			case "@top-center":
			case "@top-right":
			case "@top-right-corner":
			case "@bottom-left":
			case "@bottom-left-corner":
			case "@bottom-center":
			case "@bottom-right":
			case "@bottom-right-corner":
			case "@left-top":
			case "@left-middle":
			case "@left-bottom":
			case "@right-top":
			case "@right-middle":
			case "@right-bottom":
			hasBlock = true;
			break;
			*/
			case "@charset":
				$hasIdentifier = true;
				$hasBlock = false;
				break;
			case "@namespace":
				$hasExpression = true;
				$hasBlock = false;
				break;
			case "@keyframes":
				$hasIdentifier = true;
				break;
			case "@host":
			case "@page":
			case "@document":
			case "@supports":
				$hasUnknown = true;
				break;
		}

		if( $hasIdentifier ){
			$value = $this->parseEntity();
			if( !$value ){
				$this->error("expected " . $name . " identifier");
			}
		} else if( $hasExpression ){
			$value = $this->parseExpression();
			if( !$value ){
				$this->error("expected " . $name. " expression");
			}
		} else if ($hasUnknown) {

			$value = $this->MatchReg('/\\G[^{;]+/');
			if( $value ){
				$value = $this->NewObj1('Less_Tree_Anonymous',trim($value[0]));
			}
		}

		if( $hasBlock ){
			$rules = $this->parseBlockRuleset();
		}

		if( $rules || (!$hasBlock && $value && $this->MatchChar(';'))) {
			$this->forget();
			return $this->NewObj5('Less_Tree_Directive',array($name, $value, $rules, $index, $this->env->currentFileInfo));
		}

		$this->restore();
	}


	//
	// A Value is a comma-delimited list of Expressions
	//
	//	 font-family: Baskerville, Georgia, serif;
	//
	// In a Rule, a Value represents everything after the `:`,
	// and before the `;`.
	//
	private function parseValue(){
		$expressions = array();

		do{
			$e = $this->parseExpression();
			if( $e ){
				$expressions[] = $e;
				if (! $this->MatchChar(',')) {
					break;
				}
			}
		}while($e);

		if( $expressions ){
			return $this->NewObj1('Less_Tree_Value',$expressions);
		}
	}

	private function parseImportant (){
		if( $this->PeekChar('!') && $this->MatchReg('/\\G! *important/') ){
			return ' !important';
		}
	}

	private function parseSub (){

		if( $this->MatchChar('(') ){
			$a = $this->parseAddition();
			if( $a ){
				$this->expectChar(')');
				return $this->NewObj2('Less_Tree_Expression',array( array($a), true) ); //instead of $e->parens = true so the value is cached
			}
		}
	}


	/**
	 * Parses multiplication operation
	 *
	 * @return Less_Tree_Operation|null
	 */
	function parseMultiplication(){

		$return = $m = $this->parseOperand();
		if( $return ){
			while( true ){

				$isSpaced = $this->isWhitespace( -1 );

				if( $this->PeekReg('/\\G\/[*\/]/') ){
					break;
				}

				$op = $this->MatchChar('/');
				if( !$op ){
					$op = $this->MatchChar('*');
					if( !$op ){
						break;
					}
				}

				$a = $this->parseOperand();

				if(!$a) { break; }

				$m->parensInOp = true;
				$a->parensInOp = true;
				$return = $this->NewObj3('Less_Tree_Operation',array( $op, array( $return, $a ), $isSpaced) );
			}
		}
		return $return;

	}


	/**
	 * Parses an addition operation
	 *
	 * @return Less_Tree_Operation|null
	 */
	private function parseAddition (){

		$return = $m = $this->parseMultiplication();
		if( $return ){
			while( true ){

				$isSpaced = $this->isWhitespace( -1 );

				$op = $this->MatchReg('/\\G[-+]\s+/');
				if( $op ){
					$op = $op[0];
				}else{
					if( !$isSpaced ){
						$op = $this->match(array('#+','#-'));
					}
					if( !$op ){
						break;
					}
				}

				$a = $this->parseMultiplication();
				if( !$a ){
					break;
				}

				$m->parensInOp = true;
				$a->parensInOp = true;
				$return = $this->NewObj3('Less_Tree_Operation',array($op, array($return, $a), $isSpaced));
			}
		}

		return $return;
	}


	/**
	 * Parses the conditions
	 *
	 * @return Less_Tree_Condition|null
	 */
	private function parseConditions() {
		$index = $this->pos;
		$return = $a = $this->parseCondition();
		if( $a ){
			while( true ){
				if( !$this->PeekReg('/\\G,\s*(not\s*)?\(/') ||  !$this->MatchChar(',') ){
					break;
				}
				$b = $this->parseCondition();
				if( !$b ){
					break;
				}

				$return = $this->NewObj4('Less_Tree_Condition',array('or', $return, $b, $index));
			}
			return $return;
		}
	}

	private function parseCondition() {
		$index = $this->pos;
		$negate = false;
		$c = null;

		if ($this->MatchReg('/\\Gnot/')) $negate = true;
		$this->expectChar('(');
		$a = $this->MatchFuncs(array('parseAddition','parseEntitiesKeyword','parseEntitiesQuoted'));

		if( $a ){
			$op = $this->MatchReg('/\\G(?:>=|<=|=<|[<=>])/');
			if( $op ){
				$b = $this->MatchFuncs(array('parseAddition','parseEntitiesKeyword','parseEntitiesQuoted'));
				if( $b ){
					$c = $this->NewObj5('Less_Tree_Condition',array($op[0], $a, $b, $index, $negate));
				} else {
					$this->Error('Unexpected expression');
				}
			} else {
				$k = $this->NewObj1('Less_Tree_Keyword','true');
				$c = $this->NewObj5('Less_Tree_Condition',array('=', $a, $k, $index, $negate));
			}
			$this->expectChar(')');
			return $this->MatchReg('/\\Gand/') ? $this->NewObj3('Less_Tree_Condition',array('and', $c, $this->parseCondition())) : $c;
		}
	}

	/**
	 * An operand is anything that can be part of an operation,
	 * such as a Color, or a Variable
	 *
	 */
	private function parseOperand (){

		$negate = false;
		$offset = $this->pos+1;
		if( $offset >= $this->input_len ){
			return;
		}
		$char = $this->input[$offset];
		if( $char === '@' || $char === '(' ){
			$negate = $this->MatchChar('-');
		}

		$o = $this->MatchFuncs(array('parseSub','parseEntitiesDimension','parseEntitiesColor','parseEntitiesVariable','parseEntitiesCall'));

		if( $negate ){
			$o->parensInOp = true;
			$o = $this->NewObj1('Less_Tree_Negative',$o);
		}

		return $o;
	}


	/**
	 * Expressions either represent mathematical operations,
	 * or white-space delimited Entities.
	 *
	 *	 1px solid black
	 *	 @var * 2
	 *
	 * @return Less_Tree_Expression|null
	 */
	private function parseExpression (){
		$entities = array();

		do{
			$e = $this->MatchFuncs(array('parseAddition','parseEntity'));
			if( $e ){
				$entities[] = $e;
				// operations do not allow keyword "/" dimension (e.g. small/20px) so we support that here
				if( !$this->PeekReg('/\\G\/[\/*]/') ){
					$delim = $this->MatchChar('/');
					if( $delim ){
						$entities[] = $this->NewObj1('Less_Tree_Anonymous',$delim);
					}
				}
			}
		}while($e);

		if( $entities ){
			return $this->NewObj1('Less_Tree_Expression',$entities);
		}
	}


	/**
	 * Parse a property
	 * eg: 'min-width', 'orientation', etc
	 *
	 * @return string
	 */
	private function parseProperty (){
		$name = $this->MatchReg('/\\G(\*?-?[_a-zA-Z0-9-]+)\s*:/');
		if( $name ){
			return $name[1];
		}
	}


	/**
	 * Parse a rule property
	 * eg: 'color', 'width', 'height', etc
	 *
	 * @return string
	 */
	private function parseRuleProperty(){
		$offset = $this->pos;
		$name = array();
		$index = array();
		$length = 0;


		$this->rulePropertyMatch('/\\G(\*?)/', $offset, $length, $index, $name );
		while( $this->rulePropertyMatch('/\\G((?:[\w-]+)|(?:@\{[\w-]+\}))/', $offset, $length, $index, $name )); // !

		if( (count($name) > 1) && $this->rulePropertyMatch('/\\G\s*((?:\+_|\+)?)\s*:/', $offset, $length, $index, $name) ){
			// at last, we have the complete match now. move forward,
			// convert name particles to tree objects and return:
			$this->skipWhitespace($length);

			if( $name[0] === '' ){
				array_shift($name);
				array_shift($index);
			}
			foreach($name as $k => $s ){
				if( !$s || $s[0] !== '@' ){
					$name[$k] = $this->NewObj1('Less_Tree_Keyword',$s);
				}else{
					$name[$k] = $this->NewObj3('Less_Tree_Variable',array('@' . substr($s,2,-1), $index[$k], $this->env->currentFileInfo));
				}
			}
			return $name;
		}


	}

	private function rulePropertyMatch( $re, &$offset, &$length,  &$index, &$name ){
		preg_match($re, $this->input, $a, 0, $offset);
		if( $a ){
			$index[] = $this->pos + $length;
			$length += strlen($a[0]);
			$offset += strlen($a[0]);
			$name[] = $a[1];
			return true;
		}
	}

	public static function serializeVars( $vars ){
		$s = '';

		foreach($vars as $name => $value){
			$s .= (($name[0] === '@') ? '' : '@') . $name .': '. $value . ((substr($value,-1) === ';') ? '' : ';');
		}

		return $s;
	}


	/**
	 * Some versions of php have trouble with method_exists($a,$b) if $a is not an object
	 *
	 * @param string $b
	 */
	public static function is_method($a,$b){
		return is_object($a) && method_exists($a,$b);
	}


	/**
	 * Round numbers similarly to javascript
	 * eg: 1.499999 to 1 instead of 2
	 *
	 */
	public static function round($i, $precision = 0){

		$precision = pow(10,$precision);
		$i = $i*$precision;

		$ceil = ceil($i);
		$floor = floor($i);
		if( ($ceil - $i) <= ($i - $floor) ){
			return $ceil/$precision;
		}else{
			return $floor/$precision;
		}
	}


	/**
	 * Create Less_Tree_* objects and optionally generate a cache string
	 *
	 * @return mixed
	 */
	public function NewObj0($class){
		$obj = new $class();
		if( $this->CacheEnabled() ){
			$obj->cache_string = ' new '.$class.'()';
		}
		return $obj;
	}

	public function NewObj1($class, $arg){
		$obj = new $class( $arg );
		if( $this->CacheEnabled() ){
			$obj->cache_string = ' new '.$class.'('.Less_Parser::ArgString($arg).')';
		}
		return $obj;
	}

	public function NewObj2($class, $args){
		$obj = new $class( $args[0], $args[1] );
		if( $this->CacheEnabled() ){
			$this->ObjCache( $obj, $class, $args);
		}
		return $obj;
	}

	public function NewObj3($class, $args){
		$obj = new $class( $args[0], $args[1], $args[2] );
		if( $this->CacheEnabled() ){
			$this->ObjCache( $obj, $class, $args);
		}
		return $obj;
	}

	public function NewObj4($class, $args){
		$obj = new $class( $args[0], $args[1], $args[2], $args[3] );
		if( $this->CacheEnabled() ){
			$this->ObjCache( $obj, $class, $args);
		}
		return $obj;
	}

	public function NewObj5($class, $args){
		$obj = new $class( $args[0], $args[1], $args[2], $args[3], $args[4] );
		if( $this->CacheEnabled() ){
			$this->ObjCache( $obj, $class, $args);
		}
		return $obj;
	}

	public function NewObj6($class, $args){
		$obj = new $class( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5] );
		if( $this->CacheEnabled() ){
			$this->ObjCache( $obj, $class, $args);
		}
		return $obj;
	}

	public function NewObj7($class, $args){
		$obj = new $class( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6] );
		if( $this->CacheEnabled() ){
			$this->ObjCache( $obj, $class, $args);
		}
		return $obj;
	}

	//caching
	public function ObjCache($obj, $class, $args=array()){
		$obj->cache_string = ' new '.$class.'('. self::ArgCache($args).')';
	}

	public function ArgCache($args){
		return implode(',',array_map( array('Less_Parser','ArgString'),$args));
	}


	/**
	 * Convert an argument to a string for use in the parser cache
	 *
	 * @return string
	 */
	public static function ArgString($arg){

		$type = gettype($arg);

		if( $type === 'object'){
			$string = $arg->cache_string;
			unset($arg->cache_string);
			return $string;

		}elseif( $type === 'array' ){
			$string = ' Array(';
			foreach($arg as $k => $a){
				$string .= var_export($k,true).' => '.self::ArgString($a).',';
			}
			return $string . ')';
		}

		return var_export($arg,true);
	}

	public function Error($msg){
		throw new \Less_Exception_Parser($msg, null, $this->furthest, $this->env->currentFileInfo);
	}

	public static function WinPath($path){
		return str_replace('\\', '/', $path);
	}

	public function CacheEnabled(){
		return (Less_Parser::$options['cache_method'] && (LessCache::$cache_dir || (Less_Parser::$options['cache_method'] == 'callback')));
	}

}


 

/**
 * Utility for css colors
 *
 * @package Less
 * @subpackage color
 */
class Less_Colors {

	public static $colors = array(
			'aliceblue'=>'#f0f8ff',
			'antiquewhite'=>'#faebd7',
			'aqua'=>'#00ffff',
			'aquamarine'=>'#7fffd4',
			'azure'=>'#f0ffff',
			'beige'=>'#f5f5dc',
			'bisque'=>'#ffe4c4',
			'black'=>'#000000',
			'blanchedalmond'=>'#ffebcd',
			'blue'=>'#0000ff',
			'blueviolet'=>'#8a2be2',
			'brown'=>'#a52a2a',
			'burlywood'=>'#deb887',
			'cadetblue'=>'#5f9ea0',
			'chartreuse'=>'#7fff00',
			'chocolate'=>'#d2691e',
			'coral'=>'#ff7f50',
			'cornflowerblue'=>'#6495ed',
			'cornsilk'=>'#fff8dc',
			'crimson'=>'#dc143c',
			'cyan'=>'#00ffff',
			'darkblue'=>'#00008b',
			'darkcyan'=>'#008b8b',
			'darkgoldenrod'=>'#b8860b',
			'darkgray'=>'#a9a9a9',
			'darkgrey'=>'#a9a9a9',
			'darkgreen'=>'#006400',
			'darkkhaki'=>'#bdb76b',
			'darkmagenta'=>'#8b008b',
			'darkolivegreen'=>'#556b2f',
			'darkorange'=>'#ff8c00',
			'darkorchid'=>'#9932cc',
			'darkred'=>'#8b0000',
			'darksalmon'=>'#e9967a',
			'darkseagreen'=>'#8fbc8f',
			'darkslateblue'=>'#483d8b',
			'darkslategray'=>'#2f4f4f',
			'darkslategrey'=>'#2f4f4f',
			'darkturquoise'=>'#00ced1',
			'darkviolet'=>'#9400d3',
			'deeppink'=>'#ff1493',
			'deepskyblue'=>'#00bfff',
			'dimgray'=>'#696969',
			'dimgrey'=>'#696969',
			'dodgerblue'=>'#1e90ff',
			'firebrick'=>'#b22222',
			'floralwhite'=>'#fffaf0',
			'forestgreen'=>'#228b22',
			'fuchsia'=>'#ff00ff',
			'gainsboro'=>'#dcdcdc',
			'ghostwhite'=>'#f8f8ff',
			'gold'=>'#ffd700',
			'goldenrod'=>'#daa520',
			'gray'=>'#808080',
			'grey'=>'#808080',
			'green'=>'#008000',
			'greenyellow'=>'#adff2f',
			'honeydew'=>'#f0fff0',
			'hotpink'=>'#ff69b4',
			'indianred'=>'#cd5c5c',
			'indigo'=>'#4b0082',
			'ivory'=>'#fffff0',
			'khaki'=>'#f0e68c',
			'lavender'=>'#e6e6fa',
			'lavenderblush'=>'#fff0f5',
			'lawngreen'=>'#7cfc00',
			'lemonchiffon'=>'#fffacd',
			'lightblue'=>'#add8e6',
			'lightcoral'=>'#f08080',
			'lightcyan'=>'#e0ffff',
			'lightgoldenrodyellow'=>'#fafad2',
			'lightgray'=>'#d3d3d3',
			'lightgrey'=>'#d3d3d3',
			'lightgreen'=>'#90ee90',
			'lightpink'=>'#ffb6c1',
			'lightsalmon'=>'#ffa07a',
			'lightseagreen'=>'#20b2aa',
			'lightskyblue'=>'#87cefa',
			'lightslategray'=>'#778899',
			'lightslategrey'=>'#778899',
			'lightsteelblue'=>'#b0c4de',
			'lightyellow'=>'#ffffe0',
			'lime'=>'#00ff00',
			'limegreen'=>'#32cd32',
			'linen'=>'#faf0e6',
			'magenta'=>'#ff00ff',
			'maroon'=>'#800000',
			'mediumaquamarine'=>'#66cdaa',
			'mediumblue'=>'#0000cd',
			'mediumorchid'=>'#ba55d3',
			'mediumpurple'=>'#9370d8',
			'mediumseagreen'=>'#3cb371',
			'mediumslateblue'=>'#7b68ee',
			'mediumspringgreen'=>'#00fa9a',
			'mediumturquoise'=>'#48d1cc',
			'mediumvioletred'=>'#c71585',
			'midnightblue'=>'#191970',
			'mintcream'=>'#f5fffa',
			'mistyrose'=>'#ffe4e1',
			'moccasin'=>'#ffe4b5',
			'navajowhite'=>'#ffdead',
			'navy'=>'#000080',
			'oldlace'=>'#fdf5e6',
			'olive'=>'#808000',
			'olivedrab'=>'#6b8e23',
			'orange'=>'#ffa500',
			'orangered'=>'#ff4500',
			'orchid'=>'#da70d6',
			'palegoldenrod'=>'#eee8aa',
			'palegreen'=>'#98fb98',
			'paleturquoise'=>'#afeeee',
			'palevioletred'=>'#d87093',
			'papayawhip'=>'#ffefd5',
			'peachpuff'=>'#ffdab9',
			'peru'=>'#cd853f',
			'pink'=>'#ffc0cb',
			'plum'=>'#dda0dd',
			'powderblue'=>'#b0e0e6',
			'purple'=>'#800080',
			'red'=>'#ff0000',
			'rosybrown'=>'#bc8f8f',
			'royalblue'=>'#4169e1',
			'saddlebrown'=>'#8b4513',
			'salmon'=>'#fa8072',
			'sandybrown'=>'#f4a460',
			'seagreen'=>'#2e8b57',
			'seashell'=>'#fff5ee',
			'sienna'=>'#a0522d',
			'silver'=>'#c0c0c0',
			'skyblue'=>'#87ceeb',
			'slateblue'=>'#6a5acd',
			'slategray'=>'#708090',
			'slategrey'=>'#708090',
			'snow'=>'#fffafa',
			'springgreen'=>'#00ff7f',
			'steelblue'=>'#4682b4',
			'tan'=>'#d2b48c',
			'teal'=>'#008080',
			'thistle'=>'#d8bfd8',
			'tomato'=>'#ff6347',
			'turquoise'=>'#40e0d0',
			'violet'=>'#ee82ee',
			'wheat'=>'#f5deb3',
			'white'=>'#ffffff',
			'whitesmoke'=>'#f5f5f5',
			'yellow'=>'#ffff00',
			'yellowgreen'=>'#9acd32'
		);

	public static function hasOwnProperty($color) {
		return isset(self::$colors[$color]);
	}


	public static function color($color) {
		return self::$colors[$color];
	}

}
 


/**
 * Environment
 *
 * @package Less
 * @subpackage environment
 */
class Less_Environment{

	//public $paths = array();				// option - unmodified - paths to search for imports on
	//public static $files = array();		// list of files that have been imported, used for import-once
	//public $rootpath;						// option - rootpath to append to URL's
	//public static $strictImports = null;	// option -
	//public $insecure;						// option - whether to allow imports from insecure ssl hosts
	//public $processImports;				// option - whether to process imports. if false then imports will not be imported
	//public $javascriptEnabled;			// option - whether JavaScript is enabled. if undefined, defaults to true
	//public $useFileCache;					// browser only - whether to use the per file session cache
	public $currentFileInfo;				// information about the current file - for error reporting and importing and making urls relative etc.

	public $importMultiple = false; 		// whether we are currently importing multiple copies


	/**
	 * @var array
	 */
	public $frames = array();

	/**
	 * @var array
	 */
	public $mediaBlocks = array();

	/**
	 * @var array
	 */
	public $mediaPath = array();

	public static $parensStack = 0;

	public static $tabLevel = 0;

	public static $lastRule = false;

	public static $_outputMap;

	public static $mixin_stack = 0;

	/**
	 * @var array
	 */
	public $functions = array();


	public function Init(){

		self::$parensStack = 0;
		self::$tabLevel = 0;
		self::$lastRule = false;
		self::$mixin_stack = 0;

		if( Less_Parser::$options['compress'] ){

			Less_Environment::$_outputMap = array(
				','	=> ',',
				': ' => ':',
				''  => '',
				' ' => ' ',
				':' => ' :',
				'+' => '+',
				'~' => '~',
				'>' => '>',
				'|' => '|',
		        '^' => '^',
		        '^^' => '^^'
			);

		}else{

			Less_Environment::$_outputMap = array(
				','	=> ', ',
				': ' => ': ',
				''  => '',
				' ' => ' ',
				':' => ' :',
				'+' => ' + ',
				'~' => ' ~ ',
				'>' => ' > ',
				'|' => '|',
		        '^' => ' ^ ',
		        '^^' => ' ^^ '
			);

		}
	}


	public function copyEvalEnv($frames = array() ){
		$new_env = new Less_Environment();
		$new_env->frames = $frames;
		return $new_env;
	}


	public static function isMathOn(){
		return !Less_Parser::$options['strictMath'] || Less_Environment::$parensStack;
	}

	public static function isPathRelative($path){
		return !preg_match('/^(?:[a-z-]+:|\/)/',$path);
	}


	/**
	 * Canonicalize a path by resolving references to '/./', '/../'
	 * Does not remove leading "../"
	 * @param string path or url
	 * @return string Canonicalized path
	 *
	 */
	public static function normalizePath($path){

		$segments = explode('/',$path);
		$segments = array_reverse($segments);

		$path = array();
		$path_len = 0;

		while( $segments ){
			$segment = array_pop($segments);
			switch( $segment ) {

				case '.':
				break;

				case '..':
					if( !$path_len || ( $path[$path_len-1] === '..') ){
						$path[] = $segment;
						$path_len++;
					}else{
						array_pop($path);
						$path_len--;
					}
				break;

				default:
					$path[] = $segment;
					$path_len++;
				break;
			}
		}

		return implode('/',$path);
	}


	public function unshiftFrame($frame){
		array_unshift($this->frames, $frame);
	}

	public function shiftFrame(){
		return array_shift($this->frames);
	}

}
 

/**
 * Builtin functions
 *
 * @package Less
 * @subpackage function
 * @see http://lesscss.org/functions/
 */
class Less_Functions{

	public $env;
	public $currentFileInfo;

	function __construct($env, $currentFileInfo = null ){
		$this->env = $env;
		$this->currentFileInfo = $currentFileInfo;
	}


	/**
	 * @param string $op
	 */
    public static function operate( $op, $a, $b ){
		switch ($op) {
			case '+': return $a + $b;
			case '-': return $a - $b;
			case '*': return $a * $b;
			case '/': return $a / $b;
		}
	}

	public static function clamp($val, $max = 1){
		return min( max($val, 0), $max);
	}

	public static function fround( $value ){

		if( $value === 0 ){
			return $value;
		}

		if( Less_Parser::$options['numPrecision'] ){
			$p = pow(10, Less_Parser::$options['numPrecision']);
			return round( $value * $p) / $p;
		}
		return $value;
	}

    public static function number($n){

		if ($n instanceof Less_Tree_Dimension) {
			return floatval( $n->unit->is('%') ? $n->value / 100 : $n->value);
		} else if (is_numeric($n)) {
			return $n;
		} else {
			throw new \Less_Exception_Compiler("color functions take numbers as parameters");
		}
	}

    public static function scaled($n, $size = 255 ){
		if( $n instanceof Less_Tree_Dimension && $n->unit->is('%') ){
			return (float)$n->value * $size / 100;
		} else {
			return Less_Functions::number($n);
		}
	}

	public function rgb ($r, $g, $b){
		return $this->rgba($r, $g, $b, 1.0);
	}

	public function rgba($r, $g, $b, $a){
		$rgb = array($r, $g, $b);
		$rgb = array_map(array('Less_Functions','scaled'),$rgb);

		$a = self::number($a);
		return new Less_Tree_Color($rgb, $a);
	}

	public function hsl($h, $s, $l){
		return $this->hsla($h, $s, $l, 1.0);
	}

	public function hsla($h, $s, $l, $a){

		$h = fmod(self::number($h), 360) / 360; // Classic % operator will change float to int
		$s = self::clamp(self::number($s));
		$l = self::clamp(self::number($l));
		$a = self::clamp(self::number($a));

		$m2 = $l <= 0.5 ? $l * ($s + 1) : $l + $s - $l * $s;

		$m1 = $l * 2 - $m2;

		return $this->rgba( self::hsla_hue($h + 1/3, $m1, $m2) * 255,
							self::hsla_hue($h, $m1, $m2) * 255,
							self::hsla_hue($h - 1/3, $m1, $m2) * 255,
							$a);
	}

	/**
	 * @param double $h
	 */
	public function hsla_hue($h, $m1, $m2){
		$h = $h < 0 ? $h + 1 : ($h > 1 ? $h - 1 : $h);
		if	  ($h * 6 < 1) return $m1 + ($m2 - $m1) * $h * 6;
		else if ($h * 2 < 1) return $m2;
		else if ($h * 3 < 2) return $m1 + ($m2 - $m1) * (2/3 - $h) * 6;
		else				 return $m1;
	}

	public function hsv($h, $s, $v) {
		return $this->hsva($h, $s, $v, 1.0);
	}

	/**
	 * @param double $a
	 */
	public function hsva($h, $s, $v, $a) {
		$h = ((Less_Functions::number($h) % 360) / 360 ) * 360;
		$s = Less_Functions::number($s);
		$v = Less_Functions::number($v);
		$a = Less_Functions::number($a);

		$i = floor(($h / 60) % 6);
		$f = ($h / 60) - $i;

		$vs = array( $v,
				  $v * (1 - $s),
				  $v * (1 - $f * $s),
				  $v * (1 - (1 - $f) * $s));

		$perm = array(array(0, 3, 1),
					array(2, 0, 1),
					array(1, 0, 3),
					array(1, 2, 0),
					array(3, 1, 0),
					array(0, 1, 2));

		return $this->rgba($vs[$perm[$i][0]] * 255,
						 $vs[$perm[$i][1]] * 255,
						 $vs[$perm[$i][2]] * 255,
						 $a);
	}

	public function hue($color){
		$c = $color->toHSL();
		return new Less_Tree_Dimension(Less_Parser::round($c['h']));
	}

	public function saturation($color){
		$c = $color->toHSL();
		return new Less_Tree_Dimension(Less_Parser::round($c['s'] * 100), '%');
	}

	public function lightness($color){
		$c = $color->toHSL();
		return new Less_Tree_Dimension(Less_Parser::round($c['l'] * 100), '%');
	}

	public function hsvhue( $color ){
		$hsv = $color->toHSV();
		return new Less_Tree_Dimension( Less_Parser::round($hsv['h']) );
	}


	public function hsvsaturation( $color ){
		$hsv = $color->toHSV();
		return new Less_Tree_Dimension( Less_Parser::round($hsv['s'] * 100), '%' );
	}

	public function hsvvalue( $color ){
		$hsv = $color->toHSV();
		return new Less_Tree_Dimension( Less_Parser::round($hsv['v'] * 100), '%' );
	}

	public function red($color) {
		return new Less_Tree_Dimension( $color->rgb[0] );
	}

	public function green($color) {
		return new Less_Tree_Dimension( $color->rgb[1] );
	}

	public function blue($color) {
		return new Less_Tree_Dimension( $color->rgb[2] );
	}

	public function alpha($color){
		$c = $color->toHSL();
		return new Less_Tree_Dimension($c['a']);
	}

	public function luma ($color) {
		return new Less_Tree_Dimension(Less_Parser::round( $color->luma() * $color->alpha * 100), '%');
	}

	public function luminance( $color ){
		$luminance =
			(0.2126 * $color->rgb[0] / 255)
		  + (0.7152 * $color->rgb[1] / 255)
		  + (0.0722 * $color->rgb[2] / 255);

		return new Less_Tree_Dimension(Less_Parser::round( $luminance * $color->alpha * 100), '%');
	}

	public function saturate($color, $amount = null){
		// filter: saturate(3.2);
		// should be kept as is, so check for color
		if( !property_exists($color,'rgb') ){
			return null;
		}
		$hsl = $color->toHSL();

		$hsl['s'] += $amount->value / 100;
		$hsl['s'] = self::clamp($hsl['s']);

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	/**
	 * @param Less_Tree_Dimension $amount
	 */
	public function desaturate($color, $amount){
		$hsl = $color->toHSL();

		$hsl['s'] -= $amount->value / 100;
		$hsl['s'] = self::clamp($hsl['s']);

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}



	public function lighten($color, $amount){
		$hsl = $color->toHSL();

		$hsl['l'] += $amount->value / 100;
		$hsl['l'] = self::clamp($hsl['l']);

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	public function darken($color, $amount){

		if( $color instanceof Less_Tree_Color ){
			$hsl = $color->toHSL();
			$hsl['l'] -= $amount->value / 100;
			$hsl['l'] = self::clamp($hsl['l']);

			return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
		}

		Less_Functions::Expected('color',$color);
	}

	public function fadein($color, $amount){
		$hsl = $color->toHSL();
		$hsl['a'] += $amount->value / 100;
		$hsl['a'] = self::clamp($hsl['a']);
		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	public function fadeout($color, $amount){
		$hsl = $color->toHSL();
		$hsl['a'] -= $amount->value / 100;
		$hsl['a'] = self::clamp($hsl['a']);
		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	public function fade($color, $amount){
		$hsl = $color->toHSL();

		$hsl['a'] = $amount->value / 100;
		$hsl['a'] = self::clamp($hsl['a']);
		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}



	public function spin($color, $amount){
		$hsl = $color->toHSL();
		$hue = fmod($hsl['h'] + $amount->value, 360);

		$hsl['h'] = $hue < 0 ? 360 + $hue : $hue;

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	//
	// Copyright (c) 2006-2009 Hampton Catlin, Nathan Weizenbaum, and Chris Eppstein
	// http://sass-lang.com
	//

	/**
	 * @param Less_Tree_Color $color1
	 */
	public function mix($color1, $color2, $weight = null){
		if (!$weight) {
			$weight = new Less_Tree_Dimension('50', '%');
		}

		$p = $weight->value / 100.0;
		$w = $p * 2 - 1;
		$hsl1 = $color1->toHSL();
		$hsl2 = $color2->toHSL();
		$a = $hsl1['a'] - $hsl2['a'];

		$w1 = (((($w * $a) == -1) ? $w : ($w + $a) / (1 + $w * $a)) + 1) / 2;
		$w2 = 1 - $w1;

		$rgb = array($color1->rgb[0] * $w1 + $color2->rgb[0] * $w2,
					 $color1->rgb[1] * $w1 + $color2->rgb[1] * $w2,
					 $color1->rgb[2] * $w1 + $color2->rgb[2] * $w2);

		$alpha = $color1->alpha * $p + $color2->alpha * (1 - $p);

		return new Less_Tree_Color($rgb, $alpha);
	}

	public function greyscale($color){
		return $this->desaturate($color, new Less_Tree_Dimension(100));
	}


	public function contrast( $color, $dark = null, $light = null, $threshold = null){
		// filter: contrast(3.2);
		// should be kept as is, so check for color
		if( !property_exists($color,'rgb') ){
			return null;
		}
		if( !$light ){
			$light = $this->rgba(255, 255, 255, 1.0);
		}
		if( !$dark ){
			$dark = $this->rgba(0, 0, 0, 1.0);
		}
		//Figure out which is actually light and dark!
		if( $dark->luma() > $light->luma() ){
			$t = $light;
			$light = $dark;
			$dark = $t;
		}
		if( !$threshold ){
			$threshold = 0.43;
		} else {
			$threshold = Less_Functions::number($threshold);
		}

		if( $color->luma() < $threshold ){
			return $light;
		} else {
			return $dark;
		}
	}

	public function e ($str){
		if( is_string($str) ){
			return new Less_Tree_Anonymous($str);
		}
		return new Less_Tree_Anonymous($str instanceof Less_Tree_JavaScript ? $str->expression : $str->value);
	}

	public function escape ($str){

		$revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'",'%3F'=>'?','%26'=>'&','%2C'=>',','%2F'=>'/','%40'=>'@','%2B'=>'+','%24'=>'$');

		return new Less_Tree_Anonymous(strtr(rawurlencode($str->value), $revert));
	}


	/**
	 * todo: This function will need some additional work to make it work the same as less.js
	 *
	 */
	public function replace( $string, $pattern, $replacement, $flags = null ){
		$result = $string->value;

		$expr = '/'.str_replace('/','\\/',$pattern->value).'/';
		if( $flags && $flags->value){
			$expr .= self::replace_flags($flags->value);
		}

		$result = preg_replace($expr,$replacement->value,$result);


		if( property_exists($string,'quote') ){
			return new Less_Tree_Quoted( $string->quote, $result, $string->escaped);
		}
		return new Less_Tree_Quoted( '', $result );
	}

	public static function replace_flags($flags){
		$flags = str_split($flags,1);
		$new_flags = '';

		foreach($flags as $flag){
			switch($flag){
				case 'e':
				case 'g':
				break;

				default:
				$new_flags .= $flag;
				break;
			}
		}

		return $new_flags;
	}

	public function _percent(){
		$string = func_get_arg(0);

		$args = func_get_args();
		array_shift($args);
		$result = $string->value;

		foreach($args as $arg){
			if( preg_match('/%[sda]/i',$result, $token) ){
				$token = $token[0];
				$value = stristr($token, 's') ? $arg->value : $arg->toCSS();
				$value = preg_match('/[A-Z]$/', $token) ? urlencode($value) : $value;
				$result = preg_replace('/%[sda]/i',$value, $result, 1);
			}
		}
		$result = str_replace('%%', '%', $result);

		return new Less_Tree_Quoted( $string->quote , $result, $string->escaped);
	}

    public function unit( $val, $unit = null) {
		if( !($val instanceof Less_Tree_Dimension) ){
			throw new \Less_Exception_Compiler('The first argument to unit must be a number' . ($val instanceof Less_Tree_Operation ? '. Have you forgotten parenthesis?' : '.') );
		}

		if( $unit ){
			if( $unit instanceof Less_Tree_Keyword ){
				$unit = $unit->value;
			} else {
				$unit = $unit->toCSS();
			}
		} else {
			$unit = "";
		}
		return new Less_Tree_Dimension($val->value, $unit );
    }

	public function convert($val, $unit){
		return $val->convertTo($unit->value);
	}

	public function round($n, $f = false) {

		$fraction = 0;
		if( $f !== false ){
			$fraction = $f->value;
		}

		return $this->_math('Less_Parser::round',null, $n, $fraction);
	}

	public function pi(){
		return new Less_Tree_Dimension(M_PI);
	}

	public function mod($a, $b) {
		return new Less_Tree_Dimension( $a->value % $b->value, $a->unit);
	}



	public function pow($x, $y) {
		if( is_numeric($x) && is_numeric($y) ){
			$x = new Less_Tree_Dimension($x);
			$y = new Less_Tree_Dimension($y);
		}elseif( !($x instanceof Less_Tree_Dimension) || !($y instanceof Less_Tree_Dimension) ){
			throw new \Less_Exception_Compiler('Arguments must be numbers');
		}

		return new Less_Tree_Dimension( pow($x->value, $y->value), $x->unit );
	}

	// var mathFunctions = [{name:"ce ...
	public function ceil( $n ){		return $this->_math('ceil', null, $n); }
	public function floor( $n ){	return $this->_math('floor', null, $n); }
	public function sqrt( $n ){		return $this->_math('sqrt', null, $n); }
	public function abs( $n ){		return $this->_math('abs', null, $n); }

	public function tan( $n ){		return $this->_math('tan', '', $n);	}
	public function sin( $n ){		return $this->_math('sin', '', $n);	}
	public function cos( $n ){		return $this->_math('cos', '', $n);	}

	public function atan( $n ){		return $this->_math('atan', 'rad', $n);	}
	public function asin( $n ){		return $this->_math('asin', 'rad', $n);	}
	public function acos( $n ){		return $this->_math('acos', 'rad', $n);	}

	private function _math() {
		$args = func_get_args();
		$fn = array_shift($args);
		$unit = array_shift($args);

		if ($args[0] instanceof Less_Tree_Dimension) {

			if( $unit === null ){
				$unit = $args[0]->unit;
			}else{
				$args[0] = $args[0]->unify();
			}
			$args[0] = (float)$args[0]->value;
			return new Less_Tree_Dimension( call_user_func_array($fn, $args), $unit);
		} else if (is_numeric($args[0])) {
			return call_user_func_array($fn,$args);
		} else {
			throw new \Less_Exception_Compiler("math functions take numbers as parameters");
		}
	}

	/**
	 * @param boolean $isMin
	 */
	private function _minmax( $isMin, $args ){

		$arg_count = count($args);

		if( $arg_count < 1 ){
			throw new \Less_Exception_Compiler( 'one or more arguments required');
		}

		$j = null;
		$unitClone = null;
		$unitStatic = null;


		$order = array();	// elems only contains original argument values.
		$values = array();	// key is the unit.toString() for unified tree.Dimension values,
							// value is the index into the order array.


		for( $i = 0; $i < $arg_count; $i++ ){
			$current = $args[$i];
			if( !($current instanceof Less_Tree_Dimension) ){
				if( is_array($args[$i]->value) ){
					$args[] = $args[$i]->value;
				}
				continue;
			}

			if( $current->unit->toString() === '' && !$unitClone ){
				$temp = new Less_Tree_Dimension($current->value, $unitClone);
				$currentUnified = $temp->unify();
			}else{
				$currentUnified = $current->unify();
			}

			if( $currentUnified->unit->toString() === "" && !$unitStatic ){
				$unit = $unitStatic;
			}else{
				$unit = $currentUnified->unit->toString();
			}

			if( $unit !== '' && !$unitStatic || $unit !== '' && $order[0]->unify()->unit->toString() === "" ){
				$unitStatic = $unit;
			}

			if( $unit != '' && !$unitClone ){
				$unitClone = $current->unit->toString();
			}

			if( isset($values['']) && $unit !== '' && $unit === $unitStatic ){
				$j = $values[''];
			}elseif( isset($values[$unit]) ){
				$j = $values[$unit];
			}else{

				if( $unitStatic && $unit !== $unitStatic ){
					throw new \Less_Exception_Compiler( 'incompatible types');
				}
				$values[$unit] = count($order);
				$order[] = $current;
				continue;
			}


			if( $order[$j]->unit->toString() === "" && $unitClone ){
				$temp = new Less_Tree_Dimension( $order[$j]->value, $unitClone);
				$referenceUnified = $temp->unifiy();
			}else{
				$referenceUnified = $order[$j]->unify();
			}
			if( ($isMin && $currentUnified->value < $referenceUnified->value) || (!$isMin && $currentUnified->value > $referenceUnified->value) ){
				$order[$j] = $current;
			}
		}

		if( count($order) == 1 ){
			return $order[0];
		}
		$args = array();
		foreach($order as $a){
			$args[] = $a->toCSS($this->env);
		}
		return new Less_Tree_Anonymous( ($isMin?'min(':'max(') . implode(Less_Environment::$_outputMap[','],$args).')');
	}

	public function min(){
		$args = func_get_args();
		return $this->_minmax( true, $args );
	}

	public function max(){
		$args = func_get_args();
		return $this->_minmax( false, $args );
	}

	public function getunit($n){
		return new Less_Tree_Anonymous($n->unit);
	}

	public function argb($color) {
		return new Less_Tree_Anonymous($color->toARGB());
	}

	public function percentage($n) {
		return new Less_Tree_Dimension($n->value * 100, '%');
	}

	public function color($n) {

		if( $n instanceof Less_Tree_Quoted ){
			$colorCandidate = $n->value;
			$returnColor = Less_Tree_Color::fromKeyword($colorCandidate);
			if( $returnColor ){
				return $returnColor;
			}
			if( preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})/',$colorCandidate) ){
				return new Less_Tree_Color(substr($colorCandidate, 1));
			}
			throw new \Less_Exception_Compiler("argument must be a color keyword or 3/6 digit hex e.g. #FFF");
		} else {
			throw new \Less_Exception_Compiler("argument must be a string");
		}
	}


	public function iscolor($n) {
		return $this->_isa($n, 'Less_Tree_Color');
	}

	public function isnumber($n) {
		return $this->_isa($n, 'Less_Tree_Dimension');
	}

	public function isstring($n) {
		return $this->_isa($n, 'Less_Tree_Quoted');
	}

	public function iskeyword($n) {
		return $this->_isa($n, 'Less_Tree_Keyword');
	}

	public function isurl($n) {
		return $this->_isa($n, 'Less_Tree_Url');
	}

	public function ispixel($n) {
		return $this->isunit($n, 'px');
	}

	public function ispercentage($n) {
		return $this->isunit($n, '%');
	}

	public function isem($n) {
		return $this->isunit($n, 'em');
	}

	/**
	 * @param string $unit
	 */
	public function isunit( $n, $unit ){
		return ($n instanceof Less_Tree_Dimension) && $n->unit->is( ( property_exists($unit,'value') ? $unit->value : $unit) ) ? new Less_Tree_Keyword('true') : new Less_Tree_Keyword('false');
	}

	/**
	 * @param string $type
	 */
	private function _isa($n, $type) {
		return is_a($n, $type) ? new Less_Tree_Keyword('true') : new Less_Tree_Keyword('false');
	}

	public function tint($color, $amount) {
		return $this->mix( $this->rgb(255,255,255), $color, $amount);
	}

	public function shade($color, $amount) {
		return $this->mix($this->rgb(0, 0, 0), $color, $amount);
	}

	public function extract($values, $index ){
		$index = (int)$index->value - 1; // (1-based index)
		// handle non-array values as an array of length 1
		// return 'undefined' if index is invalid
		if( property_exists($values,'value') && is_array($values->value) ){
			if( isset($values->value[$index]) ){
				return $values->value[$index];
			}
			return null;

		}elseif( (int)$index === 0 ){
			return $values;
		}

		return null;
	}

	public function length($values){
		$n = (property_exists($values,'value') && is_array($values->value)) ? count($values->value) : 1;
		return new Less_Tree_Dimension($n);
	}

	public function datauri($mimetypeNode, $filePathNode = null ) {

		$filePath = ( $filePathNode ? $filePathNode->value : null );
		$mimetype = $mimetypeNode->value;

		$args = 2;
		if( !$filePath ){
			$filePath = $mimetype;
			$args = 1;
		}

		$filePath = str_replace('\\','/',$filePath);
		if( Less_Environment::isPathRelative($filePath) ){

			if( Less_Parser::$options['relativeUrls'] ){
				$temp = $this->currentFileInfo['currentDirectory'];
			} else {
				$temp = $this->currentFileInfo['entryPath'];
			}

			if( !empty($temp) ){
				$filePath = Less_Environment::normalizePath(rtrim($temp,'/').'/'.$filePath);
			}

		}


		// detect the mimetype if not given
		if( $args < 2 ){

			/* incomplete
			$mime = require('mime');
			mimetype = mime.lookup(path);

			// use base 64 unless it's an ASCII or UTF-8 format
			var charset = mime.charsets.lookup(mimetype);
			useBase64 = ['US-ASCII', 'UTF-8'].indexOf(charset) < 0;
			if (useBase64) mimetype += ';base64';
			*/

			$mimetype = Less_Mime::lookup($filePath);

			$charset = Less_Mime::charsets_lookup($mimetype);
			$useBase64 = !in_array($charset,array('US-ASCII', 'UTF-8'));
			if( $useBase64 ){ $mimetype .= ';base64'; }

		}else{
			$useBase64 = preg_match('/;base64$/',$mimetype);
		}


		if( file_exists($filePath) ){
			$buf = @file_get_contents($filePath);
		}else{
			$buf = false;
		}


		// IE8 cannot handle a data-uri larger than 32KB. If this is exceeded
		// and the --ieCompat flag is enabled, return a normal url() instead.
		$DATA_URI_MAX_KB = 32;
		$fileSizeInKB = round( strlen($buf) / 1024 );
		if( $fileSizeInKB >= $DATA_URI_MAX_KB ){
			$url = new Less_Tree_Url( ($filePathNode ? $filePathNode : $mimetypeNode), $this->currentFileInfo);
			return $url->compile($this);
		}

		if( $buf ){
			$buf = $useBase64 ? base64_encode($buf) : rawurlencode($buf);
			$filePath = '"data:' . $mimetype . ',' . $buf . '"';
		}

		return new Less_Tree_Url( new Less_Tree_Anonymous($filePath) );
	}

	//svg-gradient
	public function svggradient( $direction ){

		$throw_message = 'svg-gradient expects direction, start_color [start_position], [color position,]..., end_color [end_position]';
		$arguments = func_get_args();

		if( count($arguments) < 3 ){
			throw new \Less_Exception_Compiler( $throw_message );
		}

		$stops = array_slice($arguments,1);
		$gradientType = 'linear';
		$rectangleDimension = 'x="0" y="0" width="1" height="1"';
		$useBase64 = true;
		$directionValue = $direction->toCSS();


		switch( $directionValue ){
			case "to bottom":
				$gradientDirectionSvg = 'x1="0%" y1="0%" x2="0%" y2="100%"';
				break;
			case "to right":
				$gradientDirectionSvg = 'x1="0%" y1="0%" x2="100%" y2="0%"';
				break;
			case "to bottom right":
				$gradientDirectionSvg = 'x1="0%" y1="0%" x2="100%" y2="100%"';
				break;
			case "to top right":
				$gradientDirectionSvg = 'x1="0%" y1="100%" x2="100%" y2="0%"';
				break;
			case "ellipse":
			case "ellipse at center":
				$gradientType = "radial";
				$gradientDirectionSvg = 'cx="50%" cy="50%" r="75%"';
				$rectangleDimension = 'x="-50" y="-50" width="101" height="101"';
				break;
			default:
				throw new \Less_Exception_Compiler( "svg-gradient direction must be 'to bottom', 'to right', 'to bottom right', 'to top right' or 'ellipse at center'" );
		}

		$returner = '<?xml version="1.0" ?>' .
			'<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="100%" height="100%" viewBox="0 0 1 1" preserveAspectRatio="none">' .
			'<' . $gradientType . 'Gradient id="gradient" gradientUnits="userSpaceOnUse" ' . $gradientDirectionSvg . '>';

		for( $i = 0; $i < count($stops); $i++ ){
			if( is_object($stops[$i]) && property_exists($stops[$i],'value') ){
				$color = $stops[$i]->value[0];
				$position = $stops[$i]->value[1];
			}else{
				$color = $stops[$i];
				$position = null;
			}

			if( !($color instanceof Less_Tree_Color) || (!(($i === 0 || $i+1 === count($stops)) && $position === null) && !($position instanceof Less_Tree_Dimension)) ){
				throw new \Less_Exception_Compiler( $throw_message );
			}
			if( $position ){
				$positionValue = $position->toCSS();
			}elseif( $i === 0 ){
				$positionValue = '0%';
			}else{
				$positionValue = '100%';
			}
			$alpha = $color->alpha;
			$returner .= '<stop offset="' . $positionValue . '" stop-color="' . $color->toRGB() . '"' . ($alpha < 1 ? ' stop-opacity="' . $alpha . '"' : '') . '/>';
		}

		$returner .= '</' . $gradientType . 'Gradient><rect ' . $rectangleDimension . ' fill="url(#gradient)" /></svg>';


		if( $useBase64 ){
			$returner = "'data:image/svg+xml;base64,".base64_encode($returner)."'";
		}else{
			$returner = "'data:image/svg+xml,".$returner."'";
		}

		return new Less_Tree_URL( new Less_Tree_Anonymous( $returner ) );
	}


	/**
	 * @param string $type
	 */
	private static function Expected( $type, $arg ){

		$debug = debug_backtrace();
		array_shift($debug);
		$last = array_shift($debug);
		$last = array_intersect_key($last,array('function'=>'','class'=>'','line'=>''));

		$message = 'Object of type '.get_class($arg).' passed to darken function. Expecting `'.$type.'`. '.$arg->toCSS().'. '.print_r($last,true);
		throw new \Less_Exception_Compiler($message);

	}

	/**
	 * Php version of javascript's `encodeURIComponent` function
	 *
	 * @param string $string The string to encode
	 * @return string The encoded string
	 */
	public static function encodeURIComponent($string){
		$revert = array('%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')');
		return strtr(rawurlencode($string), $revert);
	}


	// Color Blending
	// ref: http://www.w3.org/TR/compositing-1

	public function colorBlend( $mode, $color1, $color2 ){
		$ab = $color1->alpha;	// backdrop
		$as = $color2->alpha;	// source
		$r = array();			// result

		$ar = $as + $ab * (1 - $as);
		for( $i = 0; $i < 3; $i++ ){
			$cb = $color1->rgb[$i] / 255;
			$cs = $color2->rgb[$i] / 255;
			$cr = call_user_func( $mode, $cb, $cs );
			if( $ar ){
				$cr = ($as * $cs + $ab * ($cb - $as * ($cb + $cs - $cr))) / $ar;
			}
			$r[$i] = $cr * 255;
		}

		return new Less_Tree_Color($r, $ar);
	}

	public function multiply($color1, $color2 ){
		return $this->colorBlend( array($this,'colorBlendMultiply'),  $color1, $color2 );
	}

	private function colorBlendMultiply($cb, $cs){
		return $cb * $cs;
	}

	public function screen($color1, $color2 ){
		return $this->colorBlend( array($this,'colorBlendScreen'),  $color1, $color2 );
	}

	private function colorBlendScreen( $cb, $cs){
		return $cb + $cs - $cb * $cs;
	}

	public function overlay($color1, $color2){
		return $this->colorBlend( array($this,'colorBlendOverlay'),  $color1, $color2 );
	}

	private function colorBlendOverlay($cb, $cs ){
		$cb *= 2;
		return ($cb <= 1)
			? $this->colorBlendMultiply($cb, $cs)
			: $this->colorBlendScreen($cb - 1, $cs);
	}

	public function softlight($color1, $color2){
		return $this->colorBlend( array($this,'colorBlendSoftlight'),  $color1, $color2 );
	}

	private function colorBlendSoftlight($cb, $cs ){
		$d = 1;
		$e = $cb;
		if( $cs > 0.5 ){
			$e = 1;
			$d = ($cb > 0.25) ? sqrt($cb)
				: ((16 * $cb - 12) * $cb + 4) * $cb;
		}
		return $cb - (1 - 2 * $cs) * $e * ($d - $cb);
	}

	public function hardlight($color1, $color2){
		return $this->colorBlend( array($this,'colorBlendHardlight'),  $color1, $color2 );
	}

	private function colorBlendHardlight( $cb, $cs ){
		return $this->colorBlendOverlay($cs, $cb);
	}

	public function difference($color1, $color2) {
		return $this->colorBlend( array($this,'colorBlendDifference'),  $color1, $color2 );
	}

	private function colorBlendDifference( $cb, $cs ){
		return abs($cb - $cs);
	}

	public function exclusion( $color1, $color2 ){
		return $this->colorBlend( array($this,'colorBlendExclusion'),  $color1, $color2 );
	}

	private function colorBlendExclusion( $cb, $cs ){
		return $cb + $cs - 2 * $cb * $cs;
	}

	public function average($color1, $color2){
		return $this->colorBlend( array($this,'colorBlendAverage'),  $color1, $color2 );
	}

	// non-w3c functions:
	public function colorBlendAverage($cb, $cs ){
		return ($cb + $cs) / 2;
	}

	public function negation($color1, $color2 ){
		return $this->colorBlend( array($this,'colorBlendNegation'),  $color1, $color2 );
	}

	public function colorBlendNegation($cb, $cs){
		return 1 - abs($cb + $cs - 1);
	}

	// ~ End of Color Blending

}
 

/**
 * Mime lookup
 *
 * @package Less
 * @subpackage node
 */
class Less_Mime{

	// this map is intentionally incomplete
	// if you want more, install 'mime' dep
	static $_types = array(
	        '.htm' => 'text/html',
	        '.html'=> 'text/html',
	        '.gif' => 'image/gif',
	        '.jpg' => 'image/jpeg',
	        '.jpeg'=> 'image/jpeg',
	        '.png' => 'image/png',
	        '.ttf' => 'application/x-font-ttf',
	        '.otf' => 'application/x-font-otf',
	        '.eot' => 'application/vnd.ms-fontobject',
	        '.woff' => 'application/x-font-woff',
	        '.svg' => 'image/svg+xml',
	        );

	public static function lookup( $filepath ){
		$parts = explode('.',$filepath);
		$ext = '.'.strtolower(array_pop($parts));

		if( !isset(self::$_types[$ext]) ){
			return null;
		}
		return self::$_types[$ext];
	}

	public static function charsets_lookup( $type = null ){
		// assumes all text types are UTF-8
		return $type && preg_match('/^text\//',$type) ? 'UTF-8' : '';
	}
}
 

/**
 * Tree
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree{

	public $cache_string;

	public function toCSS(){
		$output = new Less_Output();
		$this->genCSS($output);
		return $output->toString();
	}


    /**
     * Generate CSS by adding it to the output object
     *
     * @param Less_Output $output The output
     * @return void
     */
    public function genCSS($output){}


	/**
	 * @param Less_Tree_Ruleset[] $rules
	 */
	public static function outputRuleset( $output, $rules ){

		$ruleCnt = count($rules);
		Less_Environment::$tabLevel++;


		// Compressed
		if( Less_Parser::$options['compress'] ){
			$output->add('{');
			for( $i = 0; $i < $ruleCnt; $i++ ){
				$rules[$i]->genCSS( $output );
			}

			$output->add( '}' );
			Less_Environment::$tabLevel--;
			return;
		}


		// Non-compressed
		$tabSetStr = "\n".str_repeat( '  ' , Less_Environment::$tabLevel-1 );
		$tabRuleStr = $tabSetStr.'  ';

		$output->add( " {" );
		for($i = 0; $i < $ruleCnt; $i++ ){
			$output->add( $tabRuleStr );
			$rules[$i]->genCSS( $output );
		}
		Less_Environment::$tabLevel--;
		$output->add( $tabSetStr.'}' );

	}

	public function accept($visitor){}


	public static function ReferencedArray($rules){
		foreach($rules as $rule){
			if( method_exists($rule, 'markReferenced') ){
				$rule->markReferenced();
			}
		}
	}


	/**
	 * Requires php 5.3+
	 */
	public static function __set_state($args){

		$class = get_called_class();
		$obj = new $class(null,null,null,null);
		foreach($args as $key => $val){
			$obj->$key = $val;
		}
		return $obj;
	}

} 

/**
 * Parser output
 *
 * @package Less
 * @subpackage output
 */
class Less_Output{

	/**
	 * Output holder
	 *
	 * @var string
	 */
	protected $strs = array();

	/**
	 * Adds a chunk to the stack
	 *
	 * @param string $chunk The chunk to output
	 * @param Less_FileInfo $fileInfo The file information
	 * @param integer $index The index
	 * @param mixed $mapLines
	 */
	public function add($chunk, $fileInfo = null, $index = 0, $mapLines = null){
		$this->strs[] = $chunk;
	}

	/**
	 * Is the output empty?
	 *
	 * @return boolean
	 */
	public function isEmpty(){
		return count($this->strs) === 0;
	}


	/**
	 * Converts the output to string
	 *
	 * @return string
	 */
	public function toString(){
		return implode('',$this->strs);
	}

} 

/**
 * Visitor
 *
 * @package Less
 * @subpackage visitor
 */
class Less_Visitor{

	protected $methods = array();
	protected $_visitFnCache = array();

	public function __construct(){
		$this->_visitFnCache = get_class_methods(get_class($this));
		$this->_visitFnCache = array_flip($this->_visitFnCache);
	}

	public function visitObj( $node ){

		$funcName = 'visit'.$node->type;
		if( isset($this->_visitFnCache[$funcName]) ){

			$visitDeeper = true;
			$this->$funcName( $node, $visitDeeper );

			if( $visitDeeper ){
				$node->accept($this);
			}

			$funcName = $funcName . "Out";
			if( isset($this->_visitFnCache[$funcName]) ){
				$this->$funcName( $node );
			}

		}else{
			$node->accept($this);
		}

		return $node;
	}

	public function visitArray( $nodes ){

		array_map( array($this,'visitObj'), $nodes);
		return $nodes;
	}
}

 

/**
 * Replacing Visitor
 *
 * @package Less
 * @subpackage visitor
 */
class Less_VisitorReplacing extends Less_Visitor{

	public function visitObj( $node ){

		$funcName = 'visit'.$node->type;
		if( isset($this->_visitFnCache[$funcName]) ){

			$visitDeeper = true;
			$node = $this->$funcName( $node, $visitDeeper );

			if( $node ){
				if( $visitDeeper && is_object($node) ){
					$node->accept($this);
				}

				$funcName = $funcName . "Out";
				if( isset($this->_visitFnCache[$funcName]) ){
					$this->$funcName( $node );
				}
			}

		}else{
			$node->accept($this);
		}

		return $node;
	}

	public function visitArray( $nodes ){

		$newNodes = array();
		foreach($nodes as $node){
			$evald = $this->visitObj($node);
			if( $evald ){
				if( is_array($evald) ){
					self::flatten($evald,$newNodes);
				}else{
					$newNodes[] = $evald;
				}
			}
		}
		return $newNodes;
	}

	public function flatten( $arr, &$out ){

		foreach($arr as $item){
			if( !is_array($item) ){
				$out[] = $item;
				continue;
			}

			foreach($item as $nestedItem){
				if( is_array($nestedItem) ){
					self::flatten( $nestedItem, $out);
				}else{
					$out[] = $nestedItem;
				}
			}
		}

		return $out;
	}

}

 





 


 


 


 





 


 


 


 

