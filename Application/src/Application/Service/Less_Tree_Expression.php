<?php
namespace Application\Service;
/**
 * Created by PhpStorm.
 * User: deflopian
 * Date: 10.03.15
 * Time: 13:33
 */
/**
 * Expression
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Expression extends Less_Tree{

    public $value = array();
    public $parens = false;
    public $parensInOp = false;
    public $type = 'Expression';

    public function __construct( $value, $parens = null ){
        $this->value = $value;
        $this->parens = $parens;
    }

    public function accept( $visitor ){
        $this->value = $visitor->visitArray( $this->value );
    }

    public function compile($env) {

        $doubleParen = false;

        if( $this->parens && !$this->parensInOp ){
            Less_Environment::$parensStack++;
        }

        $returnValue = null;
        if( $this->value ){

            $count = count($this->value);

            if( $count > 1 ){

                $ret = array();
                foreach($this->value as $e){
                    $ret[] = $e->compile($env);
                }
                $returnValue = new Less_Tree_Expression($ret);

            }else{

                if( ($this->value[0] instanceof Less_Tree_Expression) && $this->value[0]->parens && !$this->value[0]->parensInOp ){
                    $doubleParen = true;
                }

                $returnValue = $this->value[0]->compile($env);
            }

        } else {
            $returnValue = $this;
        }

        if( $this->parens ){
            if( !$this->parensInOp ){
                Less_Environment::$parensStack--;

            }elseif( !Less_Environment::isMathOn() && !$doubleParen ){
                $returnValue = new Less_Tree_Paren($returnValue);

            }
        }
        return $returnValue;
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){
        $val_len = count($this->value);
        for( $i = 0; $i < $val_len; $i++ ){
            $this->value[$i]->genCSS( $output );
            if( $i + 1 < $val_len ){
                $output->add( ' ' );
            }
        }
    }

    public function throwAwayComments() {

        if( is_array($this->value) ){
            $new_value = array();
            foreach($this->value as $v){
                if( $v instanceof Less_Tree_Comment ){
                    continue;
                }
                $new_value[] = $v;
            }
            $this->value = $new_value;
        }
    }
}


/**
 * Extend
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Extend extends Less_Tree{

    public $selector;
    public $option;
    public $index;
    public $selfSelectors = array();
    public $allowBefore;
    public $allowAfter;
    public $firstExtendOnThisSelectorPath;
    public $type = 'Extend';
    public $ruleset;


    public $object_id;
    public $parent_ids = array();

    /**
     * @param integer $index
     */
    public function __construct($selector, $option, $index){
        static $i = 0;
        $this->selector = $selector;
        $this->option = $option;
        $this->index = $index;

        switch($option){
            case "all":
                $this->allowBefore = true;
                $this->allowAfter = true;
                break;
            default:
                $this->allowBefore = false;
                $this->allowAfter = false;
                break;
        }

        $this->object_id = $i++;
        $this->parent_ids = array($this->object_id);
    }

    public function accept( $visitor ){
        $this->selector = $visitor->visitObj( $this->selector );
    }

    public function compile( $env ){
        Less_Parser::$has_extends = true;
        $this->selector = $this->selector->compile($env);
        return $this;
        //return new Less_Tree_Extend( $this->selector->compile($env), $this->option, $this->index);
    }

    public function findSelfSelectors( $selectors ){
        $selfElements = array();


        for( $i = 0, $selectors_len = count($selectors); $i < $selectors_len; $i++ ){
            $selectorElements = $selectors[$i]->elements;
            // duplicate the logic in genCSS function inside the selector node.
            // future TODO - move both logics into the selector joiner visitor
            if( $i && $selectorElements && $selectorElements[0]->combinator === "") {
                $selectorElements[0]->combinator = ' ';
            }
            $selfElements = array_merge( $selfElements, $selectors[$i]->elements );
        }

        $this->selfSelectors = array(new Less_Tree_Selector($selfElements));
    }

}

/**
 * CSS @import node
 *
 * The general strategy here is that we don't want to wait
 * for the parsing to be completed, before we start importing
 * the file. That's because in the context of a browser,
 * most of the time will be spent waiting for the server to respond.
 *
 * On creation, we push the import path to our import queue, though
 * `import,push`, we also pass it a callback, which it'll call once
 * the file has been fetched, and parsed.
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Import extends Less_Tree{

    public $options;
    public $index;
    public $path;
    public $features;
    public $currentFileInfo;
    public $css;
    public $skip;
    public $root;
    public $type = 'Import';

    public function __construct($path, $features, $options, $index, $currentFileInfo = null ){
        $this->options = $options;
        $this->index = $index;
        $this->path = $path;
        $this->features = $features;
        $this->currentFileInfo = $currentFileInfo;

        if( is_array($options) ){
            $this->options += array('inline'=>false);

            if( isset($this->options['less']) || $this->options['inline'] ){
                $this->css = !isset($this->options['less']) || !$this->options['less'] || $this->options['inline'];
            } else {
                $pathValue = $this->getPath();
                if( $pathValue && preg_match('/css([\?;].*)?$/',$pathValue) ){
                    $this->css = true;
                }
            }
        }
    }

//
// The actual import node doesn't return anything, when converted to CSS.
// The reason is that it's used at the evaluation stage, so that the rules
// it imports can be treated like any other rules.
//
// In `eval`, we make sure all Import nodes get evaluated, recursively, so
// we end up with a flat structure, which can easily be imported in the parent
// ruleset.
//

    public function accept($visitor){

        if( $this->features ){
            $this->features = $visitor->visitObj($this->features);
        }
        $this->path = $visitor->visitObj($this->path);

        if( !$this->options['inline'] && $this->root ){
            $this->root = $visitor->visit($this->root);
        }
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){
        if( $this->css ){

            $output->add( '@import ', $this->currentFileInfo, $this->index );

            $this->path->genCSS( $output );
            if( $this->features ){
                $output->add( ' ' );
                $this->features->genCSS( $output );
            }
            $output->add( ';' );
        }
    }

    public function toCSS(){
        $features = $this->features ? ' ' . $this->features->toCSS() : '';

        if ($this->css) {
            return "@import " . $this->path->toCSS() . $features . ";\n";
        } else {
            return "";
        }
    }

    /**
     * @return string
     */
    public function getPath(){
        if ($this->path instanceof Less_Tree_Quoted) {
            $path = $this->path->value;
            $path = ( isset($this->css) || preg_match('/(\.[a-z]*$)|([\?;].*)$/',$path)) ? $path : $path . '.less';
        } else if ($this->path instanceof Less_Tree_URL) {
            $path = $this->path->value->value;
        }else{
            return null;
        }

        //remove query string and fragment
        return preg_replace('/[\?#][^\?]*$/','',$path);
    }

    public function compileForImport( $env ){
        return new Less_Tree_Import( $this->path->compile($env), $this->features, $this->options, $this->index, $this->currentFileInfo);
    }

    public function compilePath($env) {
        $path = $this->path->compile($env);
        $rootpath = '';
        if( $this->currentFileInfo && $this->currentFileInfo['rootpath'] ){
            $rootpath = $this->currentFileInfo['rootpath'];
        }


        if( !($path instanceof Less_Tree_URL) ){
            if( $rootpath ){
                $pathValue = $path->value;
                // Add the base path if the import is relative
                if( $pathValue && Less_Environment::isPathRelative($pathValue) ){
                    $path->value = $this->currentFileInfo['uri_root'].$pathValue;
                }
            }
            $path->value = Less_Environment::normalizePath($path->value);
        }



        return $path;
    }

    public function compile( $env ){

        $evald = $this->compileForImport($env);

        //get path & uri
        $path_and_uri = null;
        if( is_callable(Less_Parser::$options['import_callback']) ){
            $path_and_uri = call_user_func(Less_Parser::$options['import_callback'],$evald);
        }

        if( !$path_and_uri ){
            $path_and_uri = $evald->PathAndUri();
        }

        if( $path_and_uri ){
            list($full_path, $uri) = $path_and_uri;
        }else{
            $full_path = $uri = $evald->getPath();
        }


        //import once
        if( $evald->skip( $full_path, $env) ){
            return array();
        }

        if( $this->options['inline'] ){
            //todo needs to reference css file not import
            //$contents = new Less_Tree_Anonymous($this->root, 0, array('filename'=>$this->importedFilename), true );

            Less_Parser::AddParsedFile($full_path);
            $contents = new Less_Tree_Anonymous( file_get_contents($full_path), 0, array(), true );

            if( $this->features ){
                return new Less_Tree_Media( array($contents), $this->features->value );
            }

            return array( $contents );
        }


        // css ?
        if( $evald->css ){
            $features = ( $evald->features ? $evald->features->compile($env) : null );
            return new Less_Tree_Import( $this->compilePath( $env), $features, $this->options, $this->index);
        }


        return $this->ParseImport( $full_path, $uri, $env );
    }


    /**
     * Using the import directories, get the full absolute path and uri of the import
     *
     * @param Less_Tree_Import $evald
     */
    public function PathAndUri(){

        $evald_path = $this->getPath();

        if( $evald_path ){

            $import_dirs = array();

            if( Less_Environment::isPathRelative($evald_path) ){
                //if the path is relative, the file should be in the current directory
                $import_dirs[ $this->currentFileInfo['currentDirectory'] ] = $this->currentFileInfo['uri_root'];

            }else{
                //otherwise, the file should be relative to the server root
                $import_dirs[ $this->currentFileInfo['entryPath'] ] = $this->currentFileInfo['entryUri'];

                //if the user supplied entryPath isn't the actual root
                $import_dirs[ $_SERVER['DOCUMENT_ROOT'] ] = '';

            }

            // always look in user supplied import directories
            $import_dirs = array_merge( $import_dirs, Less_Parser::$options['import_dirs'] );


            foreach( $import_dirs as $rootpath => $rooturi){
                if( is_callable($rooturi) ){
                    list($path, $uri) = call_user_func($rooturi, $evald_path);
                    if( is_string($path) ){
                        $full_path = $path;
                        return array( $full_path, $uri );
                    }
                }else{
                    $path = rtrim($rootpath,'/\\').'/'.ltrim($evald_path,'/\\');

                    if( file_exists($path) ){
                        $full_path = Less_Environment::normalizePath($path);
                        $uri = Less_Environment::normalizePath(dirname($rooturi.$evald_path));
                        return array( $full_path, $uri );
                    }
                }
            }
        }
    }


    /**
     * Parse the import url and return the rules
     *
     * @return Less_Tree_Media|array
     */
    public function ParseImport( $full_path, $uri, $env ){

        $import_env = clone $env;
        if( (isset($this->options['reference']) && $this->options['reference']) || isset($this->currentFileInfo['reference']) ){
            $import_env->currentFileInfo['reference'] = true;
        }

        if( (isset($this->options['multiple']) && $this->options['multiple']) ){
            $import_env->importMultiple = true;
        }

        $parser = new Less_Parser($import_env);
        $root = $parser->parseFile($full_path, $uri, true);


        $ruleset = new Less_Tree_Ruleset(array(), $root->rules );
        $ruleset->evalImports($import_env);

        return $this->features ? new Less_Tree_Media($ruleset->rules, $this->features->value) : $ruleset->rules;
    }


    /**
     * Should the import be skipped?
     *
     * @return boolean|null
     */
    private function Skip($path, $env){

        $path = realpath($path);

        if( $path && Less_Parser::FileParsed($path) ){

            if( isset($this->currentFileInfo['reference']) ){
                return true;
            }

            return !isset($this->options['multiple']) && !$env->importMultiple;
        }

    }
}



/**
 * Javascript
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Javascript extends Less_Tree{

    public $type = 'Javascript';
    public $escaped;
    public $expression;
    public $index;

    /**
     * @param boolean $index
     * @param boolean $escaped
     */
    public function __construct($string, $index, $escaped){
        $this->escaped = $escaped;
        $this->expression = $string;
        $this->index = $index;
    }

    public function compile(){
        return new Less_Tree_Anonymous('/* Sorry, can not do JavaScript evaluation in PHP... :( */');
    }

}


/**
 * Keyword
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Keyword extends Less_Tree{

    public $value;
    public $type = 'Keyword';

    /**
     * @param string $value
     */
    public function __construct($value){
        $this->value = $value;
    }

    public function compile(){
        return $this;
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){

        if( $this->value === '%') {
            throw new \Less_Exception_Compiler("Invalid % without number");
        }

        $output->add( $this->value );
    }

    public function compare($other) {
        if ($other instanceof Less_Tree_Keyword) {
            return $other->value === $this->value ? 0 : 1;
        } else {
            return -1;
        }
    }
}


/**
 * Media
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Media extends Less_Tree{

    public $features;
    public $rules;
    public $index;
    public $currentFileInfo;
    public $isReferenced;
    public $type = 'Media';

    public function __construct($value = array(), $features = array(), $index = null, $currentFileInfo = null ){

        $this->index = $index;
        $this->currentFileInfo = $currentFileInfo;

        $selectors = $this->emptySelectors();

        $this->features = new Less_Tree_Value($features);

        $this->rules = array(new Less_Tree_Ruleset($selectors, $value));
        $this->rules[0]->allowImports = true;
    }

    public function accept( $visitor ){
        $this->features = $visitor->visitObj($this->features);
        $this->rules = $visitor->visitArray($this->rules);
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){

        $output->add( '@media ', $this->currentFileInfo, $this->index );
        $this->features->genCSS( $output );
        Less_Tree::outputRuleset( $output, $this->rules);

    }

    public function compile($env) {

        $media = new Less_Tree_Media(array(), array(), $this->index, $this->currentFileInfo );

        $strictMathBypass = false;
        if( Less_Parser::$options['strictMath'] === false) {
            $strictMathBypass = true;
            Less_Parser::$options['strictMath'] = true;
        }

        $media->features = $this->features->compile($env);

        if( $strictMathBypass ){
            Less_Parser::$options['strictMath'] = false;
        }

        $env->mediaPath[] = $media;
        $env->mediaBlocks[] = $media;

        array_unshift($env->frames, $this->rules[0]);
        $media->rules = array($this->rules[0]->compile($env));
        array_shift($env->frames);

        array_pop($env->mediaPath);

        return !$env->mediaPath ? $media->compileTop($env) : $media->compileNested($env);
    }

    public function variable($name) {
        return $this->rules[0]->variable($name);
    }

    public function find($selector) {
        return $this->rules[0]->find($selector, $this);
    }

    public function emptySelectors(){
        $el = new Less_Tree_Element('','&', $this->index, $this->currentFileInfo );
        $sels = array( new Less_Tree_Selector(array($el), array(), null, $this->index, $this->currentFileInfo) );
        $sels[0]->mediaEmpty = true;
        return $sels;
    }

    public function markReferenced(){
        $this->rules[0]->markReferenced();
        $this->isReferenced = true;
        Less_Tree::ReferencedArray($this->rules[0]->rules);
    }

    // evaltop
    public function compileTop($env) {
        $result = $this;

        if (count($env->mediaBlocks) > 1) {
            $selectors = $this->emptySelectors();
            $result = new Less_Tree_Ruleset($selectors, $env->mediaBlocks);
            $result->multiMedia = true;
        }

        $env->mediaBlocks = array();
        $env->mediaPath = array();

        return $result;
    }

    public function compileNested($env) {
        $path = array_merge($env->mediaPath, array($this));

        // Extract the media-query conditions separated with `,` (OR).
        foreach ($path as $key => $p) {
            $value = $p->features instanceof Less_Tree_Value ? $p->features->value : $p->features;
            $path[$key] = is_array($value) ? $value : array($value);
        }

        // Trace all permutations to generate the resulting media-query.
        //
        // (a, b and c) with nested (d, e) ->
        //	a and d
        //	a and e
        //	b and c and d
        //	b and c and e

        $permuted = $this->permute($path);
        $expressions = array();
        foreach($permuted as $path){

            for( $i=0, $len=count($path); $i < $len; $i++){
                $path[$i] = Less_Parser::is_method($path[$i], 'toCSS') ? $path[$i] : new Less_Tree_Anonymous($path[$i]);
            }

            for( $i = count($path) - 1; $i > 0; $i-- ){
                array_splice($path, $i, 0, array(new Less_Tree_Anonymous('and')));
            }

            $expressions[] = new Less_Tree_Expression($path);
        }
        $this->features = new Less_Tree_Value($expressions);



        // Fake a tree-node that doesn't output anything.
        return new Less_Tree_Ruleset(array(), array());
    }

    public function permute($arr) {
        if (!$arr)
            return array();

        if (count($arr) == 1)
            return $arr[0];

        $result = array();
        $rest = $this->permute(array_slice($arr, 1));
        foreach ($rest as $r) {
            foreach ($arr[0] as $a) {
                $result[] = array_merge(
                    is_array($a) ? $a : array($a),
                    is_array($r) ? $r : array($r)
                );
            }
        }

        return $result;
    }

    public function bubbleSelectors($selectors) {

        if( !$selectors) return;

        $this->rules = array(new Less_Tree_Ruleset( $selectors, array($this->rules[0])));
    }

}


/**
 * A simple css name-value pair
 * ex: width:100px;
 *
 * In bootstrap, there are about 600-1,000 simple name-value pairs (depending on how forgiving the match is) -vs- 6,020 dynamic rules (Less_Tree_Rule)
 * Using the name-value object can speed up bootstrap compilation slightly, but it breaks color keyword interpretation: color:red -> color:#FF0000;
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_NameValue extends Less_Tree{

    public $name;
    public $value;
    public $index;
    public $currentFileInfo;
    public $type = 'NameValue';

    public function __construct($name, $value = null, $index = null, $currentFileInfo = null ){
        $this->name = $name;
        $this->value = $value;
        $this->index = $index;
        $this->currentFileInfo = $currentFileInfo;
    }

    public function genCSS( $output ){

        $output->add(
            $this->name
            . Less_Environment::$_outputMap[': ']
            . $this->value
            . (((Less_Environment::$lastRule && Less_Parser::$options['compress'])) ? "" : ";")
            , $this->currentFileInfo, $this->index);
    }

    public function compile ($env){
        return $this;
    }
}


/**
 * Negative
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Negative extends Less_Tree{

    public $value;
    public $type = 'Negative';

    public function __construct($node){
        $this->value = $node;
    }

    //function accept($visitor) {
    //	$this->value = $visitor->visit($this->value);
    //}

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){
        $output->add( '-' );
        $this->value->genCSS( $output );
    }

    public function compile($env) {
        if( Less_Environment::isMathOn() ){
            $ret = new Less_Tree_Operation('*', array( new Less_Tree_Dimension(-1), $this->value ) );
            return $ret->compile($env);
        }
        return new Less_Tree_Negative( $this->value->compile($env) );
    }
}

/**
 * Operation
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Operation extends Less_Tree{

    public $op;
    public $operands;
    public $isSpaced;
    public $type = 'Operation';

    /**
     * @param string $op
     */
    public function __construct($op, $operands, $isSpaced = false){
        $this->op = trim($op);
        $this->operands = $operands;
        $this->isSpaced = $isSpaced;
    }

    public function accept($visitor) {
        $this->operands = $visitor->visitArray($this->operands);
    }

    public function compile($env){
        $a = $this->operands[0]->compile($env);
        $b = $this->operands[1]->compile($env);


        if( Less_Environment::isMathOn() ){

            if( $a instanceof Less_Tree_Dimension && $b instanceof Less_Tree_Color ){
                $a = $a->toColor();

            }elseif( $b instanceof Less_Tree_Dimension && $a instanceof Less_Tree_Color ){
                $b = $b->toColor();

            }

            if( !method_exists($a,'operate') ){
                throw new \Less_Exception_Compiler("Operation on an invalid type");
            }

            return $a->operate( $this->op, $b);
        }

        return new Less_Tree_Operation($this->op, array($a, $b), $this->isSpaced );
    }


    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){
        $this->operands[0]->genCSS( $output );
        if( $this->isSpaced ){
            $output->add( " " );
        }
        $output->add( $this->op );
        if( $this->isSpaced ){
            $output->add( ' ' );
        }
        $this->operands[1]->genCSS( $output );
    }

}


/**
 * Paren
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Paren extends Less_Tree{

    public $value;
    public $type = 'Paren';

    public function __construct($value) {
        $this->value = $value;
    }

    public function accept($visitor){
        $this->value = $visitor->visitObj($this->value);
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){
        $output->add( '(' );
        $this->value->genCSS( $output );
        $output->add( ')' );
    }

    public function compile($env) {
        return new Less_Tree_Paren($this->value->compile($env));
    }

}


/**
 * Quoted
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Quoted extends Less_Tree{
    public $escaped;
    public $value;
    public $quote;
    public $index;
    public $currentFileInfo;
    public $type = 'Quoted';

    /**
     * @param string $str
     */
    public function __construct($str, $content = '', $escaped = false, $index = false, $currentFileInfo = null ){
        $this->escaped = $escaped;
        $this->value = $content;
        if( $str ){
            $this->quote = $str[0];
        }
        $this->index = $index;
        $this->currentFileInfo = $currentFileInfo;
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){
        if( !$this->escaped ){
            $output->add( $this->quote, $this->currentFileInfo, $this->index );
        }
        $output->add( $this->value );
        if( !$this->escaped ){
            $output->add( $this->quote );
        }
    }

    public function compile($env){

        $value = $this->value;
        if( preg_match_all('/`([^`]+)`/', $this->value, $matches) ){
            foreach($matches as $i => $match){
                $js = new Less_Tree_JavaScript($matches[1], $this->index, true);
                $js = $js->compile()->value;
                $value = str_replace($matches[0][$i], $js, $value);
            }
        }

        if( preg_match_all('/@\{([\w-]+)\}/',$value,$matches) ){
            foreach($matches[1] as $i => $match){
                $v = new Less_Tree_Variable('@' . $match, $this->index, $this->currentFileInfo );
                $v = $v->compile($env);
                $v = ($v instanceof Less_Tree_Quoted) ? $v->value : $v->toCSS();
                $value = str_replace($matches[0][$i], $v, $value);
            }
        }

        return new Less_Tree_Quoted($this->quote . $value . $this->quote, $value, $this->escaped, $this->index, $this->currentFileInfo);
    }

    public function compare($x) {

        if( !Less_Parser::is_method($x, 'toCSS') ){
            return -1;
        }

        $left = $this->toCSS();
        $right = $x->toCSS();

        if ($left === $right) {
            return 0;
        }

        return $left < $right ? -1 : 1;
    }
}


/**
 * Rule
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Rule extends Less_Tree{

    public $name;
    public $value;
    public $important;
    public $merge;
    public $index;
    public $inline;
    public $variable;
    public $currentFileInfo;
    public $type = 'Rule';

    /**
     * @param string $important
     */
    public function __construct($name, $value = null, $important = null, $merge = null, $index = null, $currentFileInfo = null,  $inline = false){
        $this->name = $name;
        $this->value = ($value instanceof Less_Tree_Value || $value instanceof Less_Tree_Ruleset) ? $value : new Less_Tree_Value(array($value));
        $this->important = $important ? ' ' . trim($important) : '';
        $this->merge = $merge;
        $this->index = $index;
        $this->currentFileInfo = $currentFileInfo;
        $this->inline = $inline;
        $this->variable = ( is_string($name) && $name[0] === '@');
    }

    public function accept($visitor) {
        $this->value = $visitor->visitObj( $this->value );
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){

        $output->add( $this->name . Less_Environment::$_outputMap[': '], $this->currentFileInfo, $this->index);
        try{
            $this->value->genCSS( $output);

        }catch( Less_Exception_Parser $e ){
            $e->index = $this->index;
            $e->currentFile = $this->currentFileInfo;
            throw $e;
        }
        $output->add( $this->important . (($this->inline || (Less_Environment::$lastRule && Less_Parser::$options['compress'])) ? "" : ";"), $this->currentFileInfo, $this->index);
    }

    public function compile ($env){

        $name = $this->name;
        if( is_array($name) ){
            // expand 'primitive' name directly to get
            // things faster (~10% for benchmark.less):
            if( count($name) === 1 && $name[0] instanceof Less_Tree_Keyword ){
                $name = $name[0]->value;
            }else{
                $name = $this->CompileName($env,$name);
            }
        }

        $strictMathBypass = Less_Parser::$options['strictMath'];
        if( $name === "font" && !Less_Parser::$options['strictMath'] ){
            Less_Parser::$options['strictMath'] = true;
        }

        try {
            $evaldValue = $this->value->compile($env);

            if( !$this->variable && $evaldValue->type === "DetachedRuleset") {
                throw new \Less_Exception_Compiler("Rulesets cannot be evaluated on a property.", null, $this->index, $this->currentFileInfo);
            }

            if( Less_Environment::$mixin_stack ){
                $return = new Less_Tree_Rule($name, $evaldValue, $this->important, $this->merge, $this->index, $this->currentFileInfo, $this->inline);
            }else{
                $this->name = $name;
                $this->value = $evaldValue;
                $return = $this;
            }

        }catch( Less_Exception_Parser $e ){
            if( !is_numeric($e->index) ){
                $e->index = $this->index;
                $e->currentFile = $this->currentFileInfo;
            }
            throw $e;
        }

        Less_Parser::$options['strictMath'] = $strictMathBypass;

        return $return;
    }


    public function CompileName( $env, $name ){
        $output = new Less_Output();
        foreach($name as $n){
            $n->compile($env)->genCSS($output);
        }
        return $output->toString();
    }

    public function makeImportant(){
        return new Less_Tree_Rule($this->name, $this->value, '!important', $this->merge, $this->index, $this->currentFileInfo, $this->inline);
    }

}


/**
 * Ruleset
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Ruleset extends Less_Tree{

    protected $lookups;
    public $_variables;
    public $_rulesets;

    public $strictImports;

    public $selectors;
    public $rules;
    public $root;
    public $allowImports;
    public $paths;
    public $firstRoot;
    public $type = 'Ruleset';
    public $multiMedia;
    public $allExtends;

    public $ruleset_id;
    public $originalRuleset;

    public $first_oelements;

    public function SetRulesetIndex(){
        $this->ruleset_id = Less_Parser::$next_id++;
        $this->originalRuleset = $this->ruleset_id;

        if( $this->selectors ){
            foreach($this->selectors as $sel){
                if( $sel->_oelements ){
                    $this->first_oelements[$sel->_oelements[0]] = true;
                }
            }
        }
    }

    public function __construct($selectors, $rules, $strictImports = null){
        $this->selectors = $selectors;
        $this->rules = $rules;
        $this->lookups = array();
        $this->strictImports = $strictImports;
        $this->SetRulesetIndex();
    }

    public function accept( $visitor ){
        if( $this->paths ){
            $paths_len = count($this->paths);
            for($i = 0,$paths_len; $i < $paths_len; $i++ ){
                $this->paths[$i] = $visitor->visitArray($this->paths[$i]);
            }
        }elseif( $this->selectors ){
            $this->selectors = $visitor->visitArray($this->selectors);
        }

        if( $this->rules ){
            $this->rules = $visitor->visitArray($this->rules);
        }
    }

    public function compile($env){

        $ruleset = $this->PrepareRuleset($env);


        // Store the frames around mixin definitions,
        // so they can be evaluated like closures when the time comes.
        $rsRuleCnt = count($ruleset->rules);
        for( $i = 0; $i < $rsRuleCnt; $i++ ){
            if( $ruleset->rules[$i] instanceof Less_Tree_Mixin_Definition || $ruleset->rules[$i] instanceof Less_Tree_DetachedRuleset ){
                $ruleset->rules[$i] = $ruleset->rules[$i]->compile($env);
            }
        }

        $mediaBlockCount = 0;
        if( $env instanceof Less_Environment ){
            $mediaBlockCount = count($env->mediaBlocks);
        }

        // Evaluate mixin calls.
        $this->EvalMixinCalls( $ruleset, $env, $rsRuleCnt );


        // Evaluate everything else
        for( $i=0; $i<$rsRuleCnt; $i++ ){
            if(! ($ruleset->rules[$i] instanceof Less_Tree_Mixin_Definition || $ruleset->rules[$i] instanceof Less_Tree_DetachedRuleset) ){
                $ruleset->rules[$i] = $ruleset->rules[$i]->compile($env);
            }
        }

        // Evaluate everything else
        for( $i=0; $i<$rsRuleCnt; $i++ ){
            $rule = $ruleset->rules[$i];

            // for rulesets, check if it is a css guard and can be removed
            if( $rule instanceof Less_Tree_Ruleset && $rule->selectors && count($rule->selectors) === 1 ){

                // check if it can be folded in (e.g. & where)
                if( $rule->selectors[0]->isJustParentSelector() ){
                    array_splice($ruleset->rules,$i--,1);
                    $rsRuleCnt--;

                    for($j = 0; $j < count($rule->rules); $j++ ){
                        $subRule = $rule->rules[$j];
                        if( !($subRule instanceof Less_Tree_Rule) || !$subRule->variable ){
                            array_splice($ruleset->rules, ++$i, 0, array($subRule));
                            $rsRuleCnt++;
                        }
                    }

                }
            }
        }


        // Pop the stack
        $env->shiftFrame();

        if ($mediaBlockCount) {
            $len = count($env->mediaBlocks);
            for($i = $mediaBlockCount; $i < $len; $i++ ){
                $env->mediaBlocks[$i]->bubbleSelectors($ruleset->selectors);
            }
        }

        return $ruleset;
    }

    /**
     * Compile Less_Tree_Mixin_Call objects
     *
     * @param Less_Tree_Ruleset $ruleset
     * @param integer $rsRuleCnt
     */
    private function EvalMixinCalls( $ruleset, $env, &$rsRuleCnt ){
        for($i=0; $i < $rsRuleCnt; $i++){
            $rule = $ruleset->rules[$i];

            if( $rule instanceof Less_Tree_Mixin_Call ){
                $rule = $rule->compile($env);

                $temp = array();
                foreach($rule as $r){
                    if( ($r instanceof Less_Tree_Rule) && $r->variable ){
                        // do not pollute the scope if the variable is
                        // already there. consider returning false here
                        // but we need a way to "return" variable from mixins
                        if( !$ruleset->variable($r->name) ){
                            $temp[] = $r;
                        }
                    }else{
                        $temp[] = $r;
                    }
                }
                $temp_count = count($temp)-1;
                array_splice($ruleset->rules, $i, 1, $temp);
                $rsRuleCnt += $temp_count;
                $i += $temp_count;
                $ruleset->resetCache();

            }elseif( $rule instanceof Less_Tree_RulesetCall ){

                $rule = $rule->compile($env);
                $rules = array();
                foreach($rule->rules as $r){
                    if( ($r instanceof Less_Tree_Rule) && $r->variable ){
                        continue;
                    }
                    $rules[] = $r;
                }

                array_splice($ruleset->rules, $i, 1, $rules);
                $temp_count = count($rules);
                $rsRuleCnt += $temp_count - 1;
                $i += $temp_count-1;
                $ruleset->resetCache();
            }

        }
    }


    /**
     * Compile the selectors and create a new ruleset object for the compile() method
     *
     */
    private function PrepareRuleset($env){

        $hasOnePassingSelector = false;
        $selectors = array();
        if( $this->selectors ){
            Less_Tree_DefaultFunc::error("it is currently only allowed in parametric mixin guards,");

            foreach($this->selectors as $s){
                $selector = $s->compile($env);
                $selectors[] = $selector;
                if( $selector->evaldCondition ){
                    $hasOnePassingSelector = true;
                }
            }

            Less_Tree_DefaultFunc::reset();
        } else {
            $hasOnePassingSelector = true;
        }

        if( $this->rules && $hasOnePassingSelector ){
            $rules = $this->rules;
        }else{
            $rules = array();
        }

        $ruleset = new Less_Tree_Ruleset($selectors, $rules, $this->strictImports);

        $ruleset->originalRuleset = $this->ruleset_id;

        $ruleset->root = $this->root;
        $ruleset->firstRoot = $this->firstRoot;
        $ruleset->allowImports = $this->allowImports;


        // push the current ruleset to the frames stack
        $env->unshiftFrame($ruleset);


        // Evaluate imports
        if( $ruleset->root || $ruleset->allowImports || !$ruleset->strictImports ){
            $ruleset->evalImports($env);
        }

        return $ruleset;
    }

    function evalImports($env) {

        $rules_len = count($this->rules);
        for($i=0; $i < $rules_len; $i++){
            $rule = $this->rules[$i];

            if( $rule instanceof Less_Tree_Import ){
                $rules = $rule->compile($env);
                if( is_array($rules) ){
                    array_splice($this->rules, $i, 1, $rules);
                    $temp_count = count($rules)-1;
                    $i += $temp_count;
                    $rules_len += $temp_count;
                }else{
                    array_splice($this->rules, $i, 1, array($rules));
                }

                $this->resetCache();
            }
        }
    }

    function makeImportant(){

        $important_rules = array();
        foreach($this->rules as $rule){
            if( $rule instanceof Less_Tree_Rule || $rule instanceof Less_Tree_Ruleset ){
                $important_rules[] = $rule->makeImportant();
            }else{
                $important_rules[] = $rule;
            }
        }

        return new Less_Tree_Ruleset($this->selectors, $important_rules, $this->strictImports );
    }

    public function matchArgs($args){
        return !$args;
    }

    // lets you call a css selector with a guard
    public function matchCondition( $args, $env ){
        $lastSelector = end($this->selectors);

        if( !$lastSelector->evaldCondition ){
            return false;
        }
        if( $lastSelector->condition && !$lastSelector->condition->compile( $env->copyEvalEnv( $env->frames ) ) ){
            return false;
        }
        return true;
    }

    function resetCache(){
        $this->_rulesets = null;
        $this->_variables = null;
        $this->lookups = array();
    }

    public function variables(){
        $this->_variables = array();
        foreach( $this->rules as $r){
            if ($r instanceof Less_Tree_Rule && $r->variable === true) {
                $this->_variables[$r->name] = $r;
            }
        }
    }

    public function variable($name){

        if( is_null($this->_variables) ){
            $this->variables();
        }
        return isset($this->_variables[$name]) ? $this->_variables[$name] : null;
    }

    public function find( $selector, $self = null ){

        $key = implode(' ',$selector->_oelements);

        if( !isset($this->lookups[$key]) ){

            if( !$self ){
                $self = $this->ruleset_id;
            }

            $this->lookups[$key] = array();

            $first_oelement = $selector->_oelements[0];

            foreach($this->rules as $rule){
                if( $rule instanceof Less_Tree_Ruleset && $rule->ruleset_id != $self ){

                    if( isset($rule->first_oelements[$first_oelement]) ){

                        foreach( $rule->selectors as $ruleSelector ){
                            $match = $selector->match($ruleSelector);
                            if( $match ){
                                if( $selector->elements_len > $match ){
                                    $this->lookups[$key] = array_merge($this->lookups[$key], $rule->find( new Less_Tree_Selector(array_slice($selector->elements, $match)), $self));
                                } else {
                                    $this->lookups[$key][] = $rule;
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $this->lookups[$key];
    }


    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){

        if( !$this->root ){
            Less_Environment::$tabLevel++;
        }

        $tabRuleStr = $tabSetStr = '';
        if( !Less_Parser::$options['compress'] ){
            if( Less_Environment::$tabLevel ){
                $tabRuleStr = "\n".str_repeat( '  ' , Less_Environment::$tabLevel );
                $tabSetStr = "\n".str_repeat( '  ' , Less_Environment::$tabLevel-1 );
            }else{
                $tabSetStr = $tabRuleStr = "\n";
            }
        }


        $ruleNodes = array();
        $rulesetNodes = array();
        foreach($this->rules as $rule){

            $class = get_class($rule);
            if( ($class === 'Less_Tree_Media') || ($class === 'Less_Tree_Directive') || ($this->root && $class === 'Less_Tree_Comment') || ($class === 'Less_Tree_Ruleset' && $rule->rules) ){
                $rulesetNodes[] = $rule;
            }else{
                $ruleNodes[] = $rule;
            }
        }

        // If this is the root node, we don't render
        // a selector, or {}.
        if( !$this->root ){

            /*
			debugInfo = tree.debugInfo(env, this, tabSetStr);

			if (debugInfo) {
				output.add(debugInfo);
				output.add(tabSetStr);
			}
			*/

            $paths_len = count($this->paths);
            for( $i = 0; $i < $paths_len; $i++ ){
                $path = $this->paths[$i];
                $firstSelector = true;

                foreach($path as $p){
                    $p->genCSS( $output, $firstSelector );
                    $firstSelector = false;
                }

                if( $i + 1 < $paths_len ){
                    $output->add( ',' . $tabSetStr );
                }
            }

            $output->add( (Less_Parser::$options['compress'] ? '{' : " {") . $tabRuleStr );
        }

        // Compile rules and rulesets
        $ruleNodes_len = count($ruleNodes);
        $rulesetNodes_len = count($rulesetNodes);
        for( $i = 0; $i < $ruleNodes_len; $i++ ){
            $rule = $ruleNodes[$i];

            // @page{ directive ends up with root elements inside it, a mix of rules and rulesets
            // In this instance we do not know whether it is the last property
            if( $i + 1 === $ruleNodes_len && (!$this->root || $rulesetNodes_len === 0 || $this->firstRoot ) ){
                Less_Environment::$lastRule = true;
            }

            $rule->genCSS( $output );

            if( !Less_Environment::$lastRule ){
                $output->add( $tabRuleStr );
            }else{
                Less_Environment::$lastRule = false;
            }
        }

        if( !$this->root ){
            $output->add( $tabSetStr . '}' );
            Less_Environment::$tabLevel--;
        }

        $firstRuleset = true;
        $space = ($this->root ? $tabRuleStr : $tabSetStr);
        for( $i = 0; $i < $rulesetNodes_len; $i++ ){

            if( $ruleNodes_len && $firstRuleset ){
                $output->add( $space );
            }elseif( !$firstRuleset ){
                $output->add( $space );
            }
            $firstRuleset = false;
            $rulesetNodes[$i]->genCSS( $output);
        }

        if( !Less_Parser::$options['compress'] && $this->firstRoot ){
            $output->add( "\n" );
        }

    }


    function markReferenced(){
        if( !$this->selectors ){
            return;
        }
        foreach($this->selectors as $selector){
            $selector->markReferenced();
        }
    }

    public function joinSelectors( $context, $selectors ){
        $paths = array();
        if( is_array($selectors) ){
            foreach($selectors as $selector) {
                $this->joinSelector( $paths, $context, $selector);
            }
        }
        return $paths;
    }

    public function joinSelector( &$paths, $context, $selector){

        $hasParentSelector = false;

        foreach($selector->elements as $el) {
            if( $el->value === '&') {
                $hasParentSelector = true;
            }
        }

        if( !$hasParentSelector ){
            if( $context ){
                foreach($context as $context_el){
                    $paths[] = array_merge($context_el, array($selector) );
                }
            }else {
                $paths[] = array($selector);
            }
            return;
        }


        // The paths are [[Selector]]
        // The first list is a list of comma seperated selectors
        // The inner list is a list of inheritance seperated selectors
        // e.g.
        // .a, .b {
        //   .c {
        //   }
        // }
        // == [[.a] [.c]] [[.b] [.c]]
        //

        // the elements from the current selector so far
        $currentElements = array();
        // the current list of new selectors to add to the path.
        // We will build it up. We initiate it with one empty selector as we "multiply" the new selectors
        // by the parents
        $newSelectors = array(array());


        foreach( $selector->elements as $el){

            // non parent reference elements just get added
            if( $el->value !== '&' ){
                $currentElements[] = $el;
            } else {
                // the new list of selectors to add
                $selectorsMultiplied = array();

                // merge the current list of non parent selector elements
                // on to the current list of selectors to add
                if( $currentElements ){
                    $this->mergeElementsOnToSelectors( $currentElements, $newSelectors);
                }

                // loop through our current selectors
                foreach($newSelectors as $sel){

                    // if we don't have any parent paths, the & might be in a mixin so that it can be used
                    // whether there are parents or not
                    if( !$context ){
                        // the combinator used on el should now be applied to the next element instead so that
                        // it is not lost
                        if( $sel ){
                            $sel[0]->elements = array_slice($sel[0]->elements,0);
                            $sel[0]->elements[] = new Less_Tree_Element($el->combinator, '', $el->index, $el->currentFileInfo );
                        }
                        $selectorsMultiplied[] = $sel;
                    }else {

                        // and the parent selectors
                        foreach($context as $parentSel){
                            // We need to put the current selectors
                            // then join the last selector's elements on to the parents selectors

                            // our new selector path
                            $newSelectorPath = array();
                            // selectors from the parent after the join
                            $afterParentJoin = array();
                            $newJoinedSelectorEmpty = true;

                            //construct the joined selector - if & is the first thing this will be empty,
                            // if not newJoinedSelector will be the last set of elements in the selector
                            if( $sel ){
                                $newSelectorPath = $sel;
                                $lastSelector = array_pop($newSelectorPath);
                                $newJoinedSelector = $selector->createDerived( array_slice($lastSelector->elements,0) );
                                $newJoinedSelectorEmpty = false;
                            }
                            else {
                                $newJoinedSelector = $selector->createDerived(array());
                            }

                            //put together the parent selectors after the join
                            if ( count($parentSel) > 1) {
                                $afterParentJoin = array_merge($afterParentJoin, array_slice($parentSel,1) );
                            }

                            if ( $parentSel ){
                                $newJoinedSelectorEmpty = false;

                                // join the elements so far with the first part of the parent
                                $newJoinedSelector->elements[] = new Less_Tree_Element( $el->combinator, $parentSel[0]->elements[0]->value, $el->index, $el->currentFileInfo);

                                $newJoinedSelector->elements = array_merge( $newJoinedSelector->elements, array_slice($parentSel[0]->elements, 1) );
                            }

                            if (!$newJoinedSelectorEmpty) {
                                // now add the joined selector
                                $newSelectorPath[] = $newJoinedSelector;
                            }

                            // and the rest of the parent
                            $newSelectorPath = array_merge($newSelectorPath, $afterParentJoin);

                            // add that to our new set of selectors
                            $selectorsMultiplied[] = $newSelectorPath;
                        }
                    }
                }

                // our new selectors has been multiplied, so reset the state
                $newSelectors = $selectorsMultiplied;
                $currentElements = array();
            }
        }

        // if we have any elements left over (e.g. .a& .b == .b)
        // add them on to all the current selectors
        if( $currentElements ){
            $this->mergeElementsOnToSelectors($currentElements, $newSelectors);
        }
        foreach( $newSelectors as $new_sel){
            if( $new_sel ){
                $paths[] = $new_sel;
            }
        }
    }

    function mergeElementsOnToSelectors( $elements, &$selectors){

        if( !$selectors ){
            $selectors[] = array( new Less_Tree_Selector($elements) );
            return;
        }


        foreach( $selectors as &$sel){

            // if the previous thing in sel is a parent this needs to join on to it
            if( $sel ){
                $last = count($sel)-1;
                $sel[$last] = $sel[$last]->createDerived( array_merge($sel[$last]->elements, $elements) );
            }else{
                $sel[] = new Less_Tree_Selector( $elements );
            }
        }
    }
}


/**
 * RulesetCall
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_RulesetCall extends Less_Tree{

    public $variable;
    public $type = "RulesetCall";

    public function __construct($variable){
        $this->variable = $variable;
    }

    public function accept($visitor) {}

    public function compile( $env ){
        $variable = new Less_Tree_Variable($this->variable);
        $detachedRuleset = $variable->compile($env);
        return $detachedRuleset->callEval($env);
    }
}



/**
 * Selector
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Selector extends Less_Tree{

    public $elements;
    public $condition;
    public $extendList = array();
    public $_css;
    public $index;
    public $evaldCondition = false;
    public $type = 'Selector';
    public $currentFileInfo = array();
    public $isReferenced;
    public $mediaEmpty;

    public $elements_len = 0;

    public $_oelements;
    public $_oelements_len;
    public $cacheable = true;

    /**
     * @param boolean $isReferenced
     */
    public function __construct( $elements, $extendList = array() , $condition = null, $index=null, $currentFileInfo=null, $isReferenced=null ){

        $this->elements = $elements;
        $this->elements_len = count($elements);
        $this->extendList = $extendList;
        $this->condition = $condition;
        if( $currentFileInfo ){
            $this->currentFileInfo = $currentFileInfo;
        }
        $this->isReferenced = $isReferenced;
        if( !$condition ){
            $this->evaldCondition = true;
        }

        $this->CacheElements();
    }

    public function accept($visitor) {
        $this->elements = $visitor->visitArray($this->elements);
        $this->extendList = $visitor->visitArray($this->extendList);
        if( $this->condition ){
            $this->condition = $visitor->visitObj($this->condition);
        }

        if( $visitor instanceof Less_Visitor_extendFinder ){
            $this->CacheElements();
        }
    }

    public function createDerived( $elements, $extendList = null, $evaldCondition = null ){
        $newSelector = new Less_Tree_Selector( $elements, ($extendList ? $extendList : $this->extendList), null, $this->index, $this->currentFileInfo, $this->isReferenced);
        $newSelector->evaldCondition = $evaldCondition ? $evaldCondition : $this->evaldCondition;
        return $newSelector;
    }


    public function match( $other ){

        if( !$other->_oelements || ($this->elements_len < $other->_oelements_len) ){
            return 0;
        }

        for( $i = 0; $i < $other->_oelements_len; $i++ ){
            if( $this->elements[$i]->value !== $other->_oelements[$i]) {
                return 0;
            }
        }

        return $other->_oelements_len; // return number of matched elements
    }


    public function CacheElements(){

        $this->_oelements = array();
        $css = '';

        foreach($this->elements as $v){

            $css .= $v->combinator;
            if( !$v->value_is_object ){
                $css .= $v->value;
                continue;
            }

            if( !property_exists($v->value,'value') || !is_string($v->value->value) ){
                $this->cacheable = false;
                return;
            }
            $css .= $v->value->value;
        }

        $this->_oelements_len = preg_match_all('/[,&#\.\w-](?:[\w-]|(?:\\\\.))*/', $css, $matches);
        if( $this->_oelements_len ){
            $this->_oelements = $matches[0];

            if( $this->_oelements[0] === '&' ){
                array_shift($this->_oelements);
                $this->_oelements_len--;
            }
        }
    }

    public function isJustParentSelector(){
        return !$this->mediaEmpty &&
        count($this->elements) === 1 &&
        $this->elements[0]->value === '&' &&
        ($this->elements[0]->combinator === ' ' || $this->elements[0]->combinator === '');
    }

    public function compile($env) {

        $elements = array();
        foreach($this->elements as $el){
            $elements[] = $el->compile($env);
        }

        $extendList = array();
        foreach($this->extendList as $el){
            $extendList[] = $el->compile($el);
        }

        $evaldCondition = false;
        if( $this->condition ){
            $evaldCondition = $this->condition->compile($env);
        }

        return $this->createDerived( $elements, $extendList, $evaldCondition );
    }


    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output, $firstSelector = true ){

        if( !$firstSelector && $this->elements[0]->combinator === "" ){
            $output->add(' ', $this->currentFileInfo, $this->index);
        }

        foreach($this->elements as $element){
            $element->genCSS( $output );
        }
    }

    public function markReferenced(){
        $this->isReferenced = true;
    }

    public function getIsReferenced(){
        return !isset($this->currentFileInfo['reference']) || !$this->currentFileInfo['reference'] || $this->isReferenced;
    }

    public function getIsOutput(){
        return $this->evaldCondition;
    }

}


/**
 * UnicodeDescriptor
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_UnicodeDescriptor extends Less_Tree{

    public $value;
    public $type = 'UnicodeDescriptor';

    public function __construct($value){
        $this->value = $value;
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){
        $output->add( $this->value );
    }

    public function compile(){
        return $this;
    }
}



/**
 * Unit
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Unit extends Less_Tree{

    var $numerator = array();
    var $denominator = array();
    public $backupUnit;
    public $type = 'Unit';

    public function __construct($numerator = array(), $denominator = array(), $backupUnit = null ){
        $this->numerator = $numerator;
        $this->denominator = $denominator;
        $this->backupUnit = $backupUnit;
    }

    public function __clone(){
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){

        if( $this->numerator ){
            $output->add( $this->numerator[0] );
        }elseif( $this->denominator ){
            $output->add( $this->denominator[0] );
        }elseif( !Less_Parser::$options['strictUnits'] && $this->backupUnit ){
            $output->add( $this->backupUnit );
            return ;
        }
    }

    public function toString(){
        $returnStr = implode('*',$this->numerator);
        foreach($this->denominator as $d){
            $returnStr .= '/'.$d;
        }
        return $returnStr;
    }

    public function __toString(){
        return $this->toString();
    }


    /**
     * @param Less_Tree_Unit $other
     */
    public function compare($other) {
        return $this->is( $other->toString() ) ? 0 : -1;
    }

    public function is($unitString){
        return $this->toString() === $unitString;
    }

    public function isLength(){
        $css = $this->toCSS();
        return !!preg_match('/px|em|%|in|cm|mm|pc|pt|ex/',$css);
    }

    public function isAngle() {
        return isset( Less_Tree_UnitConversions::$angle[$this->toCSS()] );
    }

    public function isEmpty(){
        return !$this->numerator && !$this->denominator;
    }

    public function isSingular() {
        return count($this->numerator) <= 1 && !$this->denominator;
    }


    public function usedUnits(){
        $result = array();

        foreach(Less_Tree_UnitConversions::$groups as $groupName){
            $group = Less_Tree_UnitConversions::${$groupName};

            foreach($this->numerator as $atomicUnit){
                if( isset($group[$atomicUnit]) && !isset($result[$groupName]) ){
                    $result[$groupName] = $atomicUnit;
                }
            }

            foreach($this->denominator as $atomicUnit){
                if( isset($group[$atomicUnit]) && !isset($result[$groupName]) ){
                    $result[$groupName] = $atomicUnit;
                }
            }
        }

        return $result;
    }

    public function cancel(){
        $counter = array();
        $backup = null;

        foreach($this->numerator as $atomicUnit){
            if( !$backup ){
                $backup = $atomicUnit;
            }
            $counter[$atomicUnit] = ( isset($counter[$atomicUnit]) ? $counter[$atomicUnit] : 0) + 1;
        }

        foreach($this->denominator as $atomicUnit){
            if( !$backup ){
                $backup = $atomicUnit;
            }
            $counter[$atomicUnit] = ( isset($counter[$atomicUnit]) ? $counter[$atomicUnit] : 0) - 1;
        }

        $this->numerator = array();
        $this->denominator = array();

        foreach($counter as $atomicUnit => $count){
            if( $count > 0 ){
                for( $i = 0; $i < $count; $i++ ){
                    $this->numerator[] = $atomicUnit;
                }
            }elseif( $count < 0 ){
                for( $i = 0; $i < -$count; $i++ ){
                    $this->denominator[] = $atomicUnit;
                }
            }
        }

        if( !$this->numerator && !$this->denominator && $backup ){
            $this->backupUnit = $backup;
        }

        sort($this->numerator);
        sort($this->denominator);
    }


}



/**
 * UnitConversions
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_UnitConversions{

    public static $groups = array('length','duration','angle');

    public static $length = array(
        'm'=> 1,
        'cm'=> 0.01,
        'mm'=> 0.001,
        'in'=> 0.0254,
        'px'=> 0.000264583, // 0.0254 / 96,
        'pt'=> 0.000352778, // 0.0254 / 72,
        'pc'=> 0.004233333, // 0.0254 / 72 * 12
    );

    public static $duration = array(
        's'=> 1,
        'ms'=> 0.001
    );

    public static $angle = array(
        'rad' => 0.1591549430919,	// 1/(2*M_PI),
        'deg' => 0.002777778, 		// 1/360,
        'grad'=> 0.0025,			// 1/400,
        'turn'=> 1
    );

}

/**
 * Url
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Url extends Less_Tree{

    public $attrs;
    public $value;
    public $currentFileInfo;
    public $isEvald;
    public $type = 'Url';

    public function __construct($value, $currentFileInfo = null, $isEvald = null){
        $this->value = $value;
        $this->currentFileInfo = $currentFileInfo;
        $this->isEvald = $isEvald;
    }

    public function accept( $visitor ){
        $this->value = $visitor->visitObj($this->value);
    }

    /**
     * @see Less_Tree::genCSS
     */
    public function genCSS( $output ){
        $output->add( 'url(' );
        $this->value->genCSS( $output );
        $output->add( ')' );
    }

    /**
     * @param Less_Functions $ctx
     */
    public function compile($ctx){
        $val = $this->value->compile($ctx);

        if( !$this->isEvald ){
            // Add the base path if the URL is relative
            if( Less_Parser::$options['relativeUrls']
                && $this->currentFileInfo
                && is_string($val->value)
                && Less_Environment::isPathRelative($val->value)
            ){
                $rootpath = $this->currentFileInfo['uri_root'];
                if ( !$val->quote ){
                    $rootpath = preg_replace('/[\(\)\'"\s]/', '\\$1', $rootpath );
                }
                $val->value = $rootpath . $val->value;
            }

            $val->value = Less_Environment::normalizePath( $val->value);
        }

        // Add cache buster if enabled
        if( Less_Parser::$options['urlArgs'] ){
            if( !preg_match('/^\s*data:/',$val->value) ){
                $delimiter = strpos($val->value,'?') === false ? '?' : '&';
                $urlArgs = $delimiter . Less_Parser::$options['urlArgs'];
                $hash_pos = strpos($val->value,'#');
                if( $hash_pos !== false ){
                    $val->value = substr_replace($val->value,$urlArgs, $hash_pos, 0);
                } else {
                    $val->value .= $urlArgs;
                }
            }
        }

        return new Less_Tree_URL($val, $this->currentFileInfo, true);
    }

}


/**
 * Value
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Value extends Less_Tree{

    public $type = 'Value';
    public $value;

    public function __construct($value){
        $this->value = $value;
    }

    public function accept($visitor) {
        $this->value = $visitor->visitArray($this->value);
    }

    public function compile($env){

        $ret = array();
        $i = 0;
        foreach($this->value as $i => $v){
            $ret[] = $v->compile($env);
        }
        if( $i > 0 ){
            return new Less_Tree_Value($ret);
        }
        return $ret[0];
    }

    /**
     * @see Less_Tree::genCSS
     */
    function genCSS( $output ){
        $len = count($this->value);
        for($i = 0; $i < $len; $i++ ){
            $this->value[$i]->genCSS( $output );
            if( $i+1 < $len ){
                $output->add( Less_Environment::$_outputMap[','] );
            }
        }
    }

}


/**
 * Variable
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Variable extends Less_Tree{

    public $name;
    public $index;
    public $currentFileInfo;
    public $evaluating = false;
    public $type = 'Variable';

    /**
     * @param string $name
     */
    public function __construct($name, $index = null, $currentFileInfo = null) {
        $this->name = $name;
        $this->index = $index;
        $this->currentFileInfo = $currentFileInfo;
    }

    public function compile($env) {

        if( $this->name[1] === '@' ){
            $v = new Less_Tree_Variable(substr($this->name, 1), $this->index + 1, $this->currentFileInfo);
            $name = '@' . $v->compile($env)->value;
        }else{
            $name = $this->name;
        }

        if ($this->evaluating) {
            throw new \Less_Exception_Compiler("Recursive variable definition for " . $name, null, $this->index, $this->currentFileInfo);
        }

        $this->evaluating = true;

        foreach($env->frames as $frame){
            if( $v = $frame->variable($name) ){
                $r = $v->value->compile($env);
                $this->evaluating = false;
                return $r;
            }
        }

        throw new \Less_Exception_Compiler("variable " . $name . " is undefined in file ".$this->currentFileInfo["filename"], null, $this->index );
    }

}



class Less_Tree_Mixin_Call extends Less_Tree{

    public $selector;
    public $arguments;
    public $index;
    public $currentFileInfo;

    public $important;
    public $type = 'MixinCall';

    /**
     * less.js: tree.mixin.Call
     *
     */
    public function __construct($elements, $args, $index, $currentFileInfo, $important = false){
        $this->selector = new Less_Tree_Selector($elements);
        $this->arguments = $args;
        $this->index = $index;
        $this->currentFileInfo = $currentFileInfo;
        $this->important = $important;
    }

    //function accept($visitor){
    //	$this->selector = $visitor->visit($this->selector);
    //	$this->arguments = $visitor->visit($this->arguments);
    //}


    public function compile($env){

        $rules = array();
        $match = false;
        $isOneFound = false;
        $candidates = array();
        $defaultUsed = false;
        $conditionResult = array();

        $args = array();
        foreach($this->arguments as $a){
            $args[] = array('name'=> $a['name'], 'value' => $a['value']->compile($env) );
        }

        foreach($env->frames as $frame){

            $mixins = $frame->find($this->selector);

            if( !$mixins ){
                continue;
            }

            $isOneFound = true;
            $defNone = 0;
            $defTrue = 1;
            $defFalse = 2;

            // To make `default()` function independent of definition order we have two "subpasses" here.
            // At first we evaluate each guard *twice* (with `default() == true` and `default() == false`),
            // and build candidate list with corresponding flags. Then, when we know all possible matches,
            // we make a final decision.

            $mixins_len = count($mixins);
            for( $m = 0; $m < $mixins_len; $m++ ){
                $mixin = $mixins[$m];

                if( $this->IsRecursive( $env, $mixin ) ){
                    continue;
                }

                if( $mixin->matchArgs($args, $env) ){

                    $candidate = array('mixin' => $mixin, 'group' => $defNone);

                    if( $mixin instanceof Less_Tree_Ruleset ){

                        for( $f = 0; $f < 2; $f++ ){
                            Less_Tree_DefaultFunc::value($f);
                            $conditionResult[$f] = $mixin->matchCondition( $args, $env);
                        }
                        if( $conditionResult[0] || $conditionResult[1] ){
                            if( $conditionResult[0] != $conditionResult[1] ){
                                $candidate['group'] = $conditionResult[1] ? $defTrue : $defFalse;
                            }

                            $candidates[] = $candidate;
                        }
                    }else{
                        $candidates[] = $candidate;
                    }

                    $match = true;
                }
            }

            Less_Tree_DefaultFunc::reset();


            $count = array(0, 0, 0);
            for( $m = 0; $m < count($candidates); $m++ ){
                $count[ $candidates[$m]['group'] ]++;
            }

            if( $count[$defNone] > 0 ){
                $defaultResult = $defFalse;
            } else {
                $defaultResult = $defTrue;
                if( ($count[$defTrue] + $count[$defFalse]) > 1 ){
                    throw new Exception( 'Ambiguous use of `default()` found when matching for `'. $this->format($args) + '`' );
                }
            }


            $candidates_length = count($candidates);
            $length_1 = ($candidates_length == 1);

            for( $m = 0; $m < $candidates_length; $m++){
                $candidate = $candidates[$m]['group'];
                if( ($candidate === $defNone) || ($candidate === $defaultResult) ){
                    try{
                        $mixin = $candidates[$m]['mixin'];
                        if( !($mixin instanceof Less_Tree_Mixin_Definition) ){
                            $mixin = new Less_Tree_Mixin_Definition('', array(), $mixin->rules, null, false);
                            $mixin->originalRuleset = $mixins[$m]->originalRuleset;
                        }
                        $rules = array_merge($rules, $mixin->evalCall($env, $args, $this->important)->rules);
                    } catch (Exception $e) {
                        //throw new Less_Exception_Compiler($e->getMessage(), $e->index, null, $this->currentFileInfo['filename']);
                        throw new \Less_Exception_Compiler($e->getMessage(), null, null, $this->currentFileInfo);
                    }
                }
            }

            if( $match ){
                if( !$this->currentFileInfo || !isset($this->currentFileInfo['reference']) || !$this->currentFileInfo['reference'] ){
                    Less_Tree::ReferencedArray($rules);
                }

                return $rules;
            }
        }

        if( $isOneFound ){
            throw new \Less_Exception_Compiler('No matching definition was found for `'.$this->Format( $args ).'`', null, $this->index, $this->currentFileInfo);

        }else{
            throw new \Less_Exception_Compiler(trim($this->selector->toCSS()) . " is undefined in ".$this->currentFileInfo['filename'], null, $this->index);
        }

    }

    /**
     * Format the args for use in exception messages
     *
     */
    private function Format($args){
        $message = array();
        if( $args ){
            foreach($args as $a){
                $argValue = '';
                if( $a['name'] ){
                    $argValue += $a['name']+':';
                }
                if( is_object($a['value']) ){
                    $argValue += $a['value']->toCSS();
                }else{
                    $argValue += '???';
                }
                $message[] = $argValue;
            }
        }
        return implode(', ',$message);
    }


    /**
     * Are we in a recursive mixin call?
     *
     * @return bool
     */
    private function IsRecursive( $env, $mixin ){

        foreach($env->frames as $recur_frame){
            if( !($mixin instanceof Less_Tree_Mixin_Definition) ){

                if( $mixin === $recur_frame ){
                    return true;
                }

                if( isset($recur_frame->originalRuleset) && $mixin->ruleset_id === $recur_frame->originalRuleset ){
                    return true;
                }
            }
        }

        return false;
    }

}




class Less_Tree_Mixin_Definition extends Less_Tree_Ruleset{
    public $name;
    public $selectors;
    public $params;
    public $arity		= 0;
    public $rules;
    public $lookups		= array();
    public $required	= 0;
    public $frames		= array();
    public $condition;
    public $variadic;
    public $type		= 'MixinDefinition';


    // less.js : /lib/less/tree/mixin.js : tree.mixin.Definition
    public function __construct($name, $params, $rules, $condition, $variadic = false, $frames = null ){
        $this->name = $name;
        $this->selectors = array(new Less_Tree_Selector(array( new Less_Tree_Element(null, $name))));

        $this->params = $params;
        $this->condition = $condition;
        $this->variadic = $variadic;
        $this->rules = $rules;

        if( $params ){
            $this->arity = count($params);
            foreach( $params as $p ){
                if (! isset($p['name']) || ($p['name'] && !isset($p['value']))) {
                    $this->required++;
                }
            }
        }

        $this->frames = $frames;
        $this->SetRulesetIndex();
    }



    //function accept( $visitor ){
    //	$this->params = $visitor->visit($this->params);
    //	$this->rules = $visitor->visit($this->rules);
    //	$this->condition = $visitor->visit($this->condition);
    //}


    public function toCSS(){
        return '';
    }

    // less.js : /lib/less/tree/mixin.js : tree.mixin.Definition.evalParams
    public function compileParams($env, $mixinFrames, $args = array() , &$evaldArguments = array() ){
        $frame = new Less_Tree_Ruleset(null, array());
        $params = $this->params;
        $mixinEnv = null;
        $argsLength = 0;

        if( $args ){
            $argsLength = count($args);
            for($i = 0; $i < $argsLength; $i++ ){
                $arg = $args[$i];

                if( $arg && $arg['name'] ){
                    $isNamedFound = false;

                    foreach($params as $j => $param){
                        if( !isset($evaldArguments[$j]) && $arg['name'] === $params[$j]['name']) {
                            $evaldArguments[$j] = $arg['value']->compile($env);
                            array_unshift($frame->rules, new Less_Tree_Rule( $arg['name'], $arg['value']->compile($env) ) );
                            $isNamedFound = true;
                            break;
                        }
                    }
                    if ($isNamedFound) {
                        array_splice($args, $i, 1);
                        $i--;
                        $argsLength--;
                        continue;
                    } else {
                        throw new \Less_Exception_Compiler("Named argument for " . $this->name .' '.$args[$i]['name'] . ' not found');
                    }
                }
            }
        }

        $argIndex = 0;
        foreach($params as $i => $param){

            if ( isset($evaldArguments[$i]) ){ continue; }

            $arg = null;
            if( isset($args[$argIndex]) ){
                $arg = $args[$argIndex];
            }

            if (isset($param['name']) && $param['name']) {

                if( isset($param['variadic']) ){
                    $varargs = array();
                    for ($j = $argIndex; $j < $argsLength; $j++) {
                        $varargs[] = $args[$j]['value']->compile($env);
                    }
                    $expression = new Less_Tree_Expression($varargs);
                    array_unshift($frame->rules, new Less_Tree_Rule($param['name'], $expression->compile($env)));
                }else{
                    $val = ($arg && $arg['value']) ? $arg['value'] : false;

                    if ($val) {
                        $val = $val->compile($env);
                    } else if ( isset($param['value']) ) {

                        if( !$mixinEnv ){
                            $mixinEnv = new Less_Environment();
                            $mixinEnv->frames = array_merge( array($frame), $mixinFrames);
                        }

                        $val = $param['value']->compile($mixinEnv);
                        $frame->resetCache();
                    } else {
                        throw new \Less_Exception_Compiler("Wrong number of arguments for " . $this->name . " (" . $argsLength . ' for ' . $this->arity . ")");
                    }

                    array_unshift($frame->rules, new Less_Tree_Rule($param['name'], $val));
                    $evaldArguments[$i] = $val;
                }
            }

            if ( isset($param['variadic']) && $args) {
                for ($j = $argIndex; $j < $argsLength; $j++) {
                    $evaldArguments[$j] = $args[$j]['value']->compile($env);
                }
            }
            $argIndex++;
        }

        ksort($evaldArguments);
        $evaldArguments = array_values($evaldArguments);

        return $frame;
    }

    public function compile($env) {
        if( $this->frames ){
            return new Less_Tree_Mixin_Definition($this->name, $this->params, $this->rules, $this->condition, $this->variadic, $this->frames );
        }
        return new Less_Tree_Mixin_Definition($this->name, $this->params, $this->rules, $this->condition, $this->variadic, $env->frames );
    }

    public function evalCall($env, $args = NULL, $important = NULL) {

        Less_Environment::$mixin_stack++;

        $_arguments = array();

        if( $this->frames ){
            $mixinFrames = array_merge($this->frames, $env->frames);
        }else{
            $mixinFrames = $env->frames;
        }

        $frame = $this->compileParams($env, $mixinFrames, $args, $_arguments);

        $ex = new Less_Tree_Expression($_arguments);
        array_unshift($frame->rules, new Less_Tree_Rule('@arguments', $ex->compile($env)));


        $ruleset = new Less_Tree_Ruleset(null, $this->rules);
        $ruleset->originalRuleset = $this->ruleset_id;


        $ruleSetEnv = new Less_Environment();
        $ruleSetEnv->frames = array_merge( array($this, $frame), $mixinFrames );
        $ruleset = $ruleset->compile( $ruleSetEnv );

        if( $important ){
            $ruleset = $ruleset->makeImportant();
        }

        Less_Environment::$mixin_stack--;

        return $ruleset;
    }


    public function matchCondition($args, $env) {

        if( !$this->condition ){
            return true;
        }

        $frame = $this->compileParams($env, array_merge($this->frames,$env->frames), $args );

        $compile_env = new Less_Environment();
        $compile_env->frames = array_merge(
            array($frame)		// the parameter variables
            , $this->frames		// the parent namespace/mixin frames
            , $env->frames		// the current environment frames
        );

        return (bool)$this->condition->compile($compile_env);
    }

    public function matchArgs($args, $env = NULL){
        $argsLength = count($args);

        if( !$this->variadic ){
            if( $argsLength < $this->required ){
                return false;
            }
            if( $argsLength > count($this->params) ){
                return false;
            }
        }else{
            if( $argsLength < ($this->required - 1)){
                return false;
            }
        }

        $len = min($argsLength, $this->arity);

        for( $i = 0; $i < $len; $i++ ){
            if( !isset($this->params[$i]['name']) && !isset($this->params[$i]['variadic']) ){
                if( $args[$i]['value']->compile($env)->toCSS() != $this->params[$i]['value']->compile($env)->toCSS() ){
                    return false;
                }
            }
        }

        return true;
    }

}


/**
 * Extend Finder Visitor
 *
 * @package Less
 * @subpackage visitor
 */
class Less_Visitor_extendFinder extends Less_Visitor{

    public $contexts = array();
    public $allExtendsStack;
    public $foundExtends;

    public function __construct(){
        $this->contexts = array();
        $this->allExtendsStack = array(array());
        parent::__construct();
    }

    /**
     * @param Less_Tree_Ruleset $root
     */
    public function run($root){
        $root = $this->visitObj($root);
        $root->allExtends =& $this->allExtendsStack[0];
        return $root;
    }

    public function visitRule($ruleNode, &$visitDeeper ){
        $visitDeeper = false;
    }

    public function visitMixinDefinition( $mixinDefinitionNode, &$visitDeeper ){
        $visitDeeper = false;
    }

    public function visitRuleset($rulesetNode){

        if( $rulesetNode->root ){
            return;
        }

        $allSelectorsExtendList = array();

        // get &:extend(.a); rules which apply to all selectors in this ruleset
        if( $rulesetNode->rules ){
            foreach($rulesetNode->rules as $rule){
                if( $rule instanceof Less_Tree_Extend ){
                    $allSelectorsExtendList[] = $rule;
                    $rulesetNode->extendOnEveryPath = true;
                }
            }
        }


        // now find every selector and apply the extends that apply to all extends
        // and the ones which apply to an individual extend
        foreach($rulesetNode->paths as $selectorPath){
            $selector = end($selectorPath); //$selectorPath[ count($selectorPath)-1];

            $j = 0;
            foreach($selector->extendList as $extend){
                $this->allExtendsStackPush($rulesetNode, $selectorPath, $extend, $j);
            }
            foreach($allSelectorsExtendList as $extend){
                $this->allExtendsStackPush($rulesetNode, $selectorPath, $extend, $j);
            }
        }

        $this->contexts[] = $rulesetNode->selectors;
    }

    public function allExtendsStackPush($rulesetNode, $selectorPath, $extend, &$j){
        $this->foundExtends = true;
        $extend = clone $extend;
        $extend->findSelfSelectors( $selectorPath );
        $extend->ruleset = $rulesetNode;
        if( $j === 0 ){
            $extend->firstExtendOnThisSelectorPath = true;
        }

        $end_key = count($this->allExtendsStack)-1;
        $this->allExtendsStack[$end_key][] = $extend;
        $j++;
    }


    public function visitRulesetOut( $rulesetNode ){
        if( !is_object($rulesetNode) || !$rulesetNode->root ){
            array_pop($this->contexts);
        }
    }

    public function visitMedia( $mediaNode ){
        $mediaNode->allExtends = array();
        $this->allExtendsStack[] =& $mediaNode->allExtends;
    }

    public function visitMediaOut(){
        array_pop($this->allExtendsStack);
    }

    public function visitDirective( $directiveNode ){
        $directiveNode->allExtends = array();
        $this->allExtendsStack[] =& $directiveNode->allExtends;
    }

    public function visitDirectiveOut(){
        array_pop($this->allExtendsStack);
    }
}




/*
class Less_Visitor_import extends Less_VisitorReplacing{

	public $_visitor;
	public $_importer;
	public $importCount;

	function __construct( $evalEnv ){
		$this->env = $evalEnv;
		$this->importCount = 0;
		parent::__construct();
	}


	function run( $root ){
		$root = $this->visitObj($root);
		$this->isFinished = true;

		//if( $this->importCount === 0) {
		//	$this->_finish();
		//}
	}

	function visitImport($importNode, &$visitDeeper ){
		$importVisitor = $this;
		$inlineCSS = $importNode->options['inline'];

		if( !$importNode->css || $inlineCSS ){
			$evaldImportNode = $importNode->compileForImport($this->env);

			if( $evaldImportNode && (!$evaldImportNode->css || $inlineCSS) ){
				$importNode = $evaldImportNode;
				$this->importCount++;
				$env = clone $this->env;

				if( (isset($importNode->options['multiple']) && $importNode->options['multiple']) ){
					$env->importMultiple = true;
				}

				//get path & uri
				$path_and_uri = null;
				if( is_callable(Less_Parser::$options['import_callback']) ){
					$path_and_uri = call_user_func(Less_Parser::$options['import_callback'],$importNode);
				}

				if( !$path_and_uri ){
					$path_and_uri = $importNode->PathAndUri();
				}

				if( $path_and_uri ){
					list($full_path, $uri) = $path_and_uri;
				}else{
					$full_path = $uri = $importNode->getPath();
				}


				//import once
				if( $importNode->skip( $full_path, $env) ){
					return array();
				}

				if( $importNode->options['inline'] ){
					//todo needs to reference css file not import
					//$contents = new Less_Tree_Anonymous($importNode->root, 0, array('filename'=>$importNode->importedFilename), true );

					Less_Parser::AddParsedFile($full_path);
					$contents = new Less_Tree_Anonymous( file_get_contents($full_path), 0, array(), true );

					if( $importNode->features ){
						return new Less_Tree_Media( array($contents), $importNode->features->value );
					}

					return array( $contents );
				}


				// css ?
				if( $importNode->css ){
					$features = ( $importNode->features ? $importNode->features->compile($env) : null );
					return new Less_Tree_Import( $importNode->compilePath( $env), $features, $importNode->options, $this->index);
				}

				return $importNode->ParseImport( $full_path, $uri, $env );
			}

		}

		$visitDeeper = false;
		return $importNode;
	}


	function visitRule( $ruleNode, &$visitDeeper ){
		$visitDeeper = false;
		return $ruleNode;
	}

	function visitDirective($directiveNode, $visitArgs){
		array_unshift($this->env->frames,$directiveNode);
		return $directiveNode;
	}

	function visitDirectiveOut($directiveNode) {
		array_shift($this->env->frames);
	}

	function visitMixinDefinition($mixinDefinitionNode, $visitArgs) {
		array_unshift($this->env->frames,$mixinDefinitionNode);
		return $mixinDefinitionNode;
	}

	function visitMixinDefinitionOut($mixinDefinitionNode) {
		array_shift($this->env->frames);
	}

	function visitRuleset($rulesetNode, $visitArgs) {
		array_unshift($this->env->frames,$rulesetNode);
		return $rulesetNode;
	}

	function visitRulesetOut($rulesetNode) {
		array_shift($this->env->frames);
	}

	function visitMedia($mediaNode, $visitArgs) {
		array_unshift($this->env->frames, $mediaNode->ruleset);
		return $mediaNode;
	}

	function visitMediaOut($mediaNode) {
		array_shift($this->env->frames);
	}

}
*/




/**
 * Join Selector Visitor
 *
 * @package Less
 * @subpackage visitor
 */
class Less_Visitor_joinSelector extends Less_Visitor{

    public $contexts = array( array() );

    /**
     * @param Less_Tree_Ruleset $root
     */
    public function run( $root ){
        return $this->visitObj($root);
    }

    public function visitRule( $ruleNode, &$visitDeeper ){
        $visitDeeper = false;
    }

    public function visitMixinDefinition( $mixinDefinitionNode, &$visitDeeper ){
        $visitDeeper = false;
    }

    public function visitRuleset( $rulesetNode ){

        $paths = array();

        if( !$rulesetNode->root ){
            $selectors = array();

            if( $rulesetNode->selectors && $rulesetNode->selectors ){
                foreach($rulesetNode->selectors as $selector){
                    if( $selector->getIsOutput() ){
                        $selectors[] = $selector;
                    }
                }
            }

            if( !$selectors ){
                $rulesetNode->selectors = null;
                $rulesetNode->rules = null;
            }else{
                $context = end($this->contexts); //$context = $this->contexts[ count($this->contexts) - 1];
                $paths = $rulesetNode->joinSelectors( $context, $selectors);
            }

            $rulesetNode->paths = $paths;
        }

        $this->contexts[] = $paths; //different from less.js. Placed after joinSelectors() so that $this->contexts will get correct $paths
    }

    public function visitRulesetOut(){
        array_pop($this->contexts);
    }

    public function visitMedia($mediaNode) {
        $context = end($this->contexts); //$context = $this->contexts[ count($this->contexts) - 1];

        if( !count($context) || (is_object($context[0]) && $context[0]->multiMedia) ){
            $mediaNode->rules[0]->root = true;
        }
    }

}



/**
 * Process Extends Visitor
 *
 * @package Less
 * @subpackage visitor
 */
class Less_Visitor_processExtends extends Less_Visitor{

    public $allExtendsStack;

    /**
     * @param Less_Tree_Ruleset $root
     */
    public function run( $root ){
        $extendFinder = new Less_Visitor_extendFinder();
        $extendFinder->run( $root );
        if( !$extendFinder->foundExtends){
            return $root;
        }

        $root->allExtends = $this->doExtendChaining( $root->allExtends, $root->allExtends);

        $this->allExtendsStack = array();
        $this->allExtendsStack[] = &$root->allExtends;

        return $this->visitObj( $root );
    }

    private function doExtendChaining( $extendsList, $extendsListTarget, $iterationCount = 0){
        //
        // chaining is different from normal extension.. if we extend an extend then we are not just copying, altering and pasting
        // the selector we would do normally, but we are also adding an extend with the same target selector
        // this means this new extend can then go and alter other extends
        //
        // this method deals with all the chaining work - without it, extend is flat and doesn't work on other extend selectors
        // this is also the most expensive.. and a match on one selector can cause an extension of a selector we had already processed if
        // we look at each selector at a time, as is done in visitRuleset

        $extendsToAdd = array();


        //loop through comparing every extend with every target extend.
        // a target extend is the one on the ruleset we are looking at copy/edit/pasting in place
        // e.g. .a:extend(.b) {} and .b:extend(.c) {} then the first extend extends the second one
        // and the second is the target.
        // the seperation into two lists allows us to process a subset of chains with a bigger set, as is the
        // case when processing media queries
        for( $extendIndex = 0, $extendsList_len = count($extendsList); $extendIndex < $extendsList_len; $extendIndex++ ){
            for( $targetExtendIndex = 0; $targetExtendIndex < count($extendsListTarget); $targetExtendIndex++ ){

                $extend = $extendsList[$extendIndex];
                $targetExtend = $extendsListTarget[$targetExtendIndex];

                // look for circular references
                if( in_array($targetExtend->object_id, $extend->parent_ids,true) ){
                    continue;
                }

                // find a match in the target extends self selector (the bit before :extend)
                $selectorPath = array( $targetExtend->selfSelectors[0] );
                $matches = $this->findMatch( $extend, $selectorPath);


                if( $matches ){

                    // we found a match, so for each self selector..
                    foreach($extend->selfSelectors as $selfSelector ){


                        // process the extend as usual
                        $newSelector = $this->extendSelector( $matches, $selectorPath, $selfSelector);

                        // but now we create a new extend from it
                        $newExtend = new Less_Tree_Extend( $targetExtend->selector, $targetExtend->option, 0);
                        $newExtend->selfSelectors = $newSelector;

                        // add the extend onto the list of extends for that selector
                        end($newSelector)->extendList = array($newExtend);
                        //$newSelector[ count($newSelector)-1]->extendList = array($newExtend);

                        // record that we need to add it.
                        $extendsToAdd[] = $newExtend;
                        $newExtend->ruleset = $targetExtend->ruleset;

                        //remember its parents for circular references
                        $newExtend->parent_ids = array_merge($newExtend->parent_ids,$targetExtend->parent_ids,$extend->parent_ids);

                        // only process the selector once.. if we have :extend(.a,.b) then multiple
                        // extends will look at the same selector path, so when extending
                        // we know that any others will be duplicates in terms of what is added to the css
                        if( $targetExtend->firstExtendOnThisSelectorPath ){
                            $newExtend->firstExtendOnThisSelectorPath = true;
                            $targetExtend->ruleset->paths[] = $newSelector;
                        }
                    }
                }
            }
        }

        if( $extendsToAdd ){
            // try to detect circular references to stop a stack overflow.
            // may no longer be needed.			$this->extendChainCount++;
            if( $iterationCount > 100) {

                try{
                    $selectorOne = $extendsToAdd[0]->selfSelectors[0]->toCSS();
                    $selectorTwo = $extendsToAdd[0]->selector->toCSS();
                }catch(Exception $e){
                    $selectorOne = "{unable to calculate}";
                    $selectorTwo = "{unable to calculate}";
                }

                throw new \Less_Exception_Parser("extend circular reference detected. One of the circular extends is currently:"+$selectorOne+":extend(" + $selectorTwo+")");
            }

            // now process the new extends on the existing rules so that we can handle a extending b extending c ectending d extending e...
            $extendsToAdd = $this->doExtendChaining( $extendsToAdd, $extendsListTarget, $iterationCount+1);
        }

        return array_merge($extendsList, $extendsToAdd);
    }


    protected function visitRule( $ruleNode, &$visitDeeper ){
        $visitDeeper = false;
    }

    protected function visitMixinDefinition( $mixinDefinitionNode, &$visitDeeper ){
        $visitDeeper = false;
    }

    protected function visitSelector( $selectorNode, &$visitDeeper ){
        $visitDeeper = false;
    }

    protected function visitRuleset($rulesetNode){


        if( $rulesetNode->root ){
            return;
        }

        $allExtends	= end($this->allExtendsStack);
        $paths_len = count($rulesetNode->paths);

        // look at each selector path in the ruleset, find any extend matches and then copy, find and replace
        foreach($allExtends as $allExtend){
            for($pathIndex = 0; $pathIndex < $paths_len; $pathIndex++ ){

                // extending extends happens initially, before the main pass
                if( isset($rulesetNode->extendOnEveryPath) && $rulesetNode->extendOnEveryPath ){
                    continue;
                }

                $selectorPath = $rulesetNode->paths[$pathIndex];

                if( end($selectorPath)->extendList ){
                    continue;
                }

                $this->ExtendMatch( $rulesetNode, $allExtend, $selectorPath);

            }
        }
    }


    private function ExtendMatch( $rulesetNode, $extend, $selectorPath ){
        $matches = $this->findMatch($extend, $selectorPath);

        if( $matches ){
            foreach($extend->selfSelectors as $selfSelector ){
                $rulesetNode->paths[] = $this->extendSelector($matches, $selectorPath, $selfSelector);
            }
        }
    }



    private function findMatch($extend, $haystackSelectorPath ){


        if( !$this->HasMatches($extend, $haystackSelectorPath) ){
            return false;
        }


        //
        // look through the haystack selector path to try and find the needle - extend.selector
        // returns an array of selector matches that can then be replaced
        //
        $needleElements = $extend->selector->elements;
        $potentialMatches = array();
        $potentialMatches_len = 0;
        $potentialMatch = null;
        $matches = array();



        // loop through the haystack elements
        $haystack_path_len = count($haystackSelectorPath);
        for($haystackSelectorIndex = 0; $haystackSelectorIndex < $haystack_path_len; $haystackSelectorIndex++ ){
            $hackstackSelector = $haystackSelectorPath[$haystackSelectorIndex];

            $haystack_elements_len = count($hackstackSelector->elements);
            for($hackstackElementIndex = 0; $hackstackElementIndex < $haystack_elements_len; $hackstackElementIndex++ ){

                $haystackElement = $hackstackSelector->elements[$hackstackElementIndex];

                // if we allow elements before our match we can add a potential match every time. otherwise only at the first element.
                if( $extend->allowBefore || ($haystackSelectorIndex === 0 && $hackstackElementIndex === 0) ){
                    $potentialMatches[] = array('pathIndex'=> $haystackSelectorIndex, 'index'=> $hackstackElementIndex, 'matched'=> 0, 'initialCombinator'=> $haystackElement->combinator);
                    $potentialMatches_len++;
                }

                for($i = 0; $i < $potentialMatches_len; $i++ ){

                    $potentialMatch = &$potentialMatches[$i];
                    $potentialMatch = $this->PotentialMatch( $potentialMatch, $needleElements, $haystackElement, $hackstackElementIndex );


                    // if we are still valid and have finished, test whether we have elements after and whether these are allowed
                    if( $potentialMatch && $potentialMatch['matched'] === $extend->selector->elements_len ){
                        $potentialMatch['finished'] = true;

                        if( !$extend->allowAfter && ($hackstackElementIndex+1 < $haystack_elements_len || $haystackSelectorIndex+1 < $haystack_path_len) ){
                            $potentialMatch = null;
                        }
                    }

                    // if null we remove, if not, we are still valid, so either push as a valid match or continue
                    if( $potentialMatch ){
                        if( $potentialMatch['finished'] ){
                            $potentialMatch['length'] = $extend->selector->elements_len;
                            $potentialMatch['endPathIndex'] = $haystackSelectorIndex;
                            $potentialMatch['endPathElementIndex'] = $hackstackElementIndex + 1; // index after end of match
                            $potentialMatches = array(); // we don't allow matches to overlap, so start matching again
                            $potentialMatches_len = 0;
                            $matches[] = $potentialMatch;
                        }
                        continue;
                    }

                    array_splice($potentialMatches, $i, 1);
                    $potentialMatches_len--;
                    $i--;
                }
            }
        }

        return $matches;
    }


    // Before going through all the nested loops, lets check to see if a match is possible
    // Reduces Bootstrap 3.1 compile time from ~6.5s to ~5.6s
    private function HasMatches($extend, $haystackSelectorPath){

        if( !$extend->selector->cacheable ){
            return true;
        }

        $first_el = $extend->selector->_oelements[0];

        foreach($haystackSelectorPath as $hackstackSelector){
            if( !$hackstackSelector->cacheable ){
                return true;
            }

            if( in_array($first_el, $hackstackSelector->_oelements) ){
                return true;
            }
        }

        return false;
    }


    /**
     * @param integer $hackstackElementIndex
     */
    private function PotentialMatch( $potentialMatch, $needleElements, $haystackElement, $hackstackElementIndex ){


        if( $potentialMatch['matched'] > 0 ){

            // selectors add " " onto the first element. When we use & it joins the selectors together, but if we don't
            // then each selector in haystackSelectorPath has a space before it added in the toCSS phase. so we need to work out
            // what the resulting combinator will be
            $targetCombinator = $haystackElement->combinator;
            if( $targetCombinator === '' && $hackstackElementIndex === 0 ){
                $targetCombinator = ' ';
            }

            if( $needleElements[ $potentialMatch['matched'] ]->combinator !== $targetCombinator ){
                return null;
            }
        }

        // if we don't match, null our match to indicate failure
        if( !$this->isElementValuesEqual( $needleElements[$potentialMatch['matched'] ]->value, $haystackElement->value) ){
            return null;
        }

        $potentialMatch['finished'] = false;
        $potentialMatch['matched']++;

        return $potentialMatch;
    }


    private function isElementValuesEqual( $elementValue1, $elementValue2 ){

        if( $elementValue1 === $elementValue2 ){
            return true;
        }

        if( is_string($elementValue1) || is_string($elementValue2) ) {
            return false;
        }

        if( $elementValue1 instanceof Less_Tree_Attribute ){
            return $this->isAttributeValuesEqual( $elementValue1, $elementValue2 );
        }

        $elementValue1 = $elementValue1->value;
        if( $elementValue1 instanceof Less_Tree_Selector ){
            return $this->isSelectorValuesEqual( $elementValue1, $elementValue2 );
        }

        return false;
    }


    /**
     * @param Less_Tree_Selector $elementValue1
     */
    private function isSelectorValuesEqual( $elementValue1, $elementValue2 ){

        $elementValue2 = $elementValue2->value;
        if( !($elementValue2 instanceof Less_Tree_Selector) || $elementValue1->elements_len !== $elementValue2->elements_len ){
            return false;
        }

        for( $i = 0; $i < $elementValue1->elements_len; $i++ ){

            if( $elementValue1->elements[$i]->combinator !== $elementValue2->elements[$i]->combinator ){
                if( $i !== 0 || ($elementValue1->elements[$i]->combinator || ' ') !== ($elementValue2->elements[$i]->combinator || ' ') ){
                    return false;
                }
            }

            if( !$this->isElementValuesEqual($elementValue1->elements[$i]->value, $elementValue2->elements[$i]->value) ){
                return false;
            }
        }

        return true;
    }


    /**
     * @param Less_Tree_Attribute $elementValue1
     */
    private function isAttributeValuesEqual( $elementValue1, $elementValue2 ){

        if( $elementValue1->op !== $elementValue2->op || $elementValue1->key !== $elementValue2->key ){
            return false;
        }

        if( !$elementValue1->value || !$elementValue2->value ){
            if( $elementValue1->value || $elementValue2->value ) {
                return false;
            }
            return true;
        }

        $elementValue1 = ($elementValue1->value->value ? $elementValue1->value->value : $elementValue1->value );
        $elementValue2 = ($elementValue2->value->value ? $elementValue2->value->value : $elementValue2->value );

        return $elementValue1 === $elementValue2;
    }


    private function extendSelector($matches, $selectorPath, $replacementSelector){

        //for a set of matches, replace each match with the replacement selector

        $currentSelectorPathIndex = 0;
        $currentSelectorPathElementIndex = 0;
        $path = array();
        $selectorPath_len = count($selectorPath);

        for($matchIndex = 0, $matches_len = count($matches); $matchIndex < $matches_len; $matchIndex++ ){


            $match = $matches[$matchIndex];
            $selector = $selectorPath[ $match['pathIndex'] ];

            $firstElement = new Less_Tree_Element(
                $match['initialCombinator'],
                $replacementSelector->elements[0]->value,
                $replacementSelector->elements[0]->index,
                $replacementSelector->elements[0]->currentFileInfo
            );

            if( $match['pathIndex'] > $currentSelectorPathIndex && $currentSelectorPathElementIndex > 0 ){
                $last_path = end($path);
                $last_path->elements = array_merge( $last_path->elements, array_slice( $selectorPath[$currentSelectorPathIndex]->elements, $currentSelectorPathElementIndex));
                $currentSelectorPathElementIndex = 0;
                $currentSelectorPathIndex++;
            }

            $newElements = array_merge(
                array_slice($selector->elements, $currentSelectorPathElementIndex, ($match['index'] - $currentSelectorPathElementIndex) ) // last parameter of array_slice is different than the last parameter of javascript's slice
                , array($firstElement)
                , array_slice($replacementSelector->elements,1)
            );

            if( $currentSelectorPathIndex === $match['pathIndex'] && $matchIndex > 0 ){
                $last_key = count($path)-1;
                $path[$last_key]->elements = array_merge($path[$last_key]->elements,$newElements);
            }else{
                $path = array_merge( $path, array_slice( $selectorPath, $currentSelectorPathIndex, $match['pathIndex'] ));
                $path[] = new Less_Tree_Selector( $newElements );
            }

            $currentSelectorPathIndex = $match['endPathIndex'];
            $currentSelectorPathElementIndex = $match['endPathElementIndex'];
            if( $currentSelectorPathElementIndex >= count($selectorPath[$currentSelectorPathIndex]->elements) ){
                $currentSelectorPathElementIndex = 0;
                $currentSelectorPathIndex++;
            }
        }

        if( $currentSelectorPathIndex < $selectorPath_len && $currentSelectorPathElementIndex > 0 ){
            $last_path = end($path);
            $last_path->elements = array_merge( $last_path->elements, array_slice($selectorPath[$currentSelectorPathIndex]->elements, $currentSelectorPathElementIndex));
            $currentSelectorPathIndex++;
        }

        $slice_len = $selectorPath_len - $currentSelectorPathIndex;
        $path = array_merge($path, array_slice($selectorPath, $currentSelectorPathIndex, $slice_len));

        return $path;
    }


    protected function visitMedia( $mediaNode ){
        $newAllExtends = array_merge( $mediaNode->allExtends, end($this->allExtendsStack) );
        $this->allExtendsStack[] = $this->doExtendChaining($newAllExtends, $mediaNode->allExtends);
    }

    protected function visitMediaOut(){
        array_pop( $this->allExtendsStack );
    }

    protected function visitDirective( $directiveNode ){
        $newAllExtends = array_merge( $directiveNode->allExtends, end($this->allExtendsStack) );
        $this->allExtendsStack[] = $this->doExtendChaining($newAllExtends, $directiveNode->allExtends);
    }

    protected function visitDirectiveOut(){
        array_pop($this->allExtendsStack);
    }

}

/**
 * toCSS Visitor
 *
 * @package Less
 * @subpackage visitor
 */
class Less_Visitor_toCSS extends Less_VisitorReplacing{

    private $charset;

    public function __construct(){
        parent::__construct();
    }

    /**
     * @param Less_Tree_Ruleset $root
     */
    public function run( $root ){
        return $this->visitObj($root);
    }

    public function visitRule( $ruleNode ){
        if( $ruleNode->variable ){
            return array();
        }
        return $ruleNode;
    }

    public function visitMixinDefinition($mixinNode){
        // mixin definitions do not get eval'd - this means they keep state
        // so we have to clear that state here so it isn't used if toCSS is called twice
        $mixinNode->frames = array();
        return array();
    }

    public function visitExtend(){
        return array();
    }

    public function visitComment( $commentNode ){
        if( $commentNode->isSilent() ){
            return array();
        }
        return $commentNode;
    }

    public function visitMedia( $mediaNode, &$visitDeeper ){
        $mediaNode->accept($this);
        $visitDeeper = false;

        if( !$mediaNode->rules ){
            return array();
        }
        return $mediaNode;
    }

    public function visitDirective( $directiveNode ){
        if( isset($directiveNode->currentFileInfo['reference']) && (!property_exists($directiveNode,'isReferenced') || !$directiveNode->isReferenced) ){
            return array();
        }
        if( $directiveNode->name === '@charset' ){
            // Only output the debug info together with subsequent @charset definitions
            // a comment (or @media statement) before the actual @charset directive would
            // be considered illegal css as it has to be on the first line
            if( isset($this->charset) && $this->charset ){

                //if( $directiveNode->debugInfo ){
                //	$comment = new Less_Tree_Comment('/* ' . str_replace("\n",'',$directiveNode->toCSS())." */\n");
                //	$comment->debugInfo = $directiveNode->debugInfo;
                //	return $this->visit($comment);
                //}


                return array();
            }
            $this->charset = true;
        }
        return $directiveNode;
    }

    public function checkPropertiesInRoot( $rulesetNode ){

        if( !$rulesetNode->firstRoot ){
            return;
        }

        foreach($rulesetNode->rules as $ruleNode){
            if( $ruleNode instanceof Less_Tree_Rule && !$ruleNode->variable ){
                $msg = "properties must be inside selector blocks, they cannot be in the root. Index ".$ruleNode->index.($ruleNode->currentFileInfo ? (' Filename: '.$ruleNode->currentFileInfo['filename']) : null);
                throw new \Less_Exception_Compiler($msg);
            }
        }
    }


    public function visitRuleset( $rulesetNode, &$visitDeeper ){

        $visitDeeper = false;

        $this->checkPropertiesInRoot( $rulesetNode );

        if( $rulesetNode->root ){
            return $this->visitRulesetRoot( $rulesetNode );
        }

        $rulesets = array();
        $rulesetNode->paths = $this->visitRulesetPaths($rulesetNode);


        // Compile rules and rulesets
        $nodeRuleCnt = count($rulesetNode->rules);
        for( $i = 0; $i < $nodeRuleCnt; ){
            $rule = $rulesetNode->rules[$i];

            if( property_exists($rule,'rules') ){
                // visit because we are moving them out from being a child
                $rulesets[] = $this->visitObj($rule);
                array_splice($rulesetNode->rules,$i,1);
                $nodeRuleCnt--;
                continue;
            }
            $i++;
        }


        // accept the visitor to remove rules and refactor itself
        // then we can decide now whether we want it or not
        if( $nodeRuleCnt > 0 ){
            $rulesetNode->accept($this);

            if( $rulesetNode->rules ){

                if( count($rulesetNode->rules) >  1 ){
                    $this->_mergeRules( $rulesetNode->rules );
                    $this->_removeDuplicateRules( $rulesetNode->rules );
                }

                // now decide whether we keep the ruleset
                if( $rulesetNode->paths ){
                    //array_unshift($rulesets, $rulesetNode);
                    array_splice($rulesets,0,0,array($rulesetNode));
                }
            }

        }


        if( count($rulesets) === 1 ){
            return $rulesets[0];
        }
        return $rulesets;
    }


    /**
     * Helper function for visitiRuleset
     *
     * return array|Less_Tree_Ruleset
     */
    private function visitRulesetRoot( $rulesetNode ){
        $rulesetNode->accept( $this );
        if( $rulesetNode->firstRoot || $rulesetNode->rules ){
            return $rulesetNode;
        }
        return array();
    }


    /**
     * Helper function for visitRuleset()
     *
     * @return array
     */
    private function visitRulesetPaths($rulesetNode){

        $paths = array();
        foreach($rulesetNode->paths as $p){
            if( $p[0]->elements[0]->combinator === ' ' ){
                $p[0]->elements[0]->combinator = '';
            }

            foreach($p as $pi){
                if( $pi->getIsReferenced() && $pi->getIsOutput() ){
                    $paths[] = $p;
                    break;
                }
            }
        }

        return $paths;
    }

    protected function _removeDuplicateRules( &$rules ){
        // remove duplicates
        $ruleCache = array();
        for( $i = count($rules)-1; $i >= 0 ; $i-- ){
            $rule = $rules[$i];
            if( $rule instanceof Less_Tree_Rule || $rule instanceof Less_Tree_NameValue ){

                if( !isset($ruleCache[$rule->name]) ){
                    $ruleCache[$rule->name] = $rule;
                }else{
                    $ruleList =& $ruleCache[$rule->name];

                    if( $ruleList instanceof Less_Tree_Rule || $ruleList instanceof Less_Tree_NameValue ){
                        $ruleList = $ruleCache[$rule->name] = array( $ruleCache[$rule->name]->toCSS() );
                    }

                    $ruleCSS = $rule->toCSS();
                    if( array_search($ruleCSS,$ruleList) !== false ){
                        array_splice($rules,$i,1);
                    }else{
                        $ruleList[] = $ruleCSS;
                    }
                }
            }
        }
    }

    protected function _mergeRules( &$rules ){
        $groups = array();

        //obj($rules);

        $rules_len = count($rules);
        for( $i = 0; $i < $rules_len; $i++ ){
            $rule = $rules[$i];

            if( ($rule instanceof Less_Tree_Rule) && $rule->merge ){

                $key = $rule->name;
                if( $rule->important ){
                    $key .= ',!';
                }

                if( !isset($groups[$key]) ){
                    $groups[$key] = array();
                }else{
                    array_splice($rules, $i--, 1);
                    $rules_len--;
                }

                $groups[$key][] = $rule;
            }
        }


        foreach($groups as $parts){

            if( count($parts) > 1 ){
                $rule = $parts[0];
                $spacedGroups = array();
                $lastSpacedGroup = array();
                $parts_mapped = array();
                foreach($parts as $p){
                    if( $p->merge === '+' ){
                        if( $lastSpacedGroup ){
                            $spacedGroups[] = self::toExpression($lastSpacedGroup);
                        }
                        $lastSpacedGroup = array();
                    }
                    $lastSpacedGroup[] = $p;
                }

                $spacedGroups[] = self::toExpression($lastSpacedGroup);
                $rule->value = self::toValue($spacedGroups);
            }
        }

    }

    public static function toExpression($values){
        $mapped = array();
        foreach($values as $p){
            $mapped[] = $p->value;
        }
        return new Less_Tree_Expression( $mapped );
    }

    public static function toValue($values){
        //return new Less_Tree_Value($values); ??

        $mapped = array();
        foreach($values as $p){
            $mapped[] = $p;
        }
        return new Less_Tree_Value($mapped);
    }
}



/**
 * Parser Exception
 *
 * @package Less
 * @subpackage exception
 */
class Less_Exception_Parser extends Exception{

    /**
     * The current file
     *
     * @var Less_ImportedFile
     */
    public $currentFile;

    /**
     * The current parser index
     *
     * @var integer
     */
    public $index;

    protected $input;

    protected $details = array();


    /**
     * Constructor
     *
     * @param string $message
     * @param Exception $previous Previous exception
     * @param integer $index The current parser index
     * @param Less_FileInfo|string $currentFile The file
     * @param integer $code The exception code
     */
    public function __construct($message = null, Exception $previous = null, $index = null, $currentFile = null, $code = 0){

        if (PHP_VERSION_ID < 50300) {
            $this->previous = $previous;
            parent::__construct($message, $code);
        } else {
            parent::__construct($message, $code, $previous);
        }

        $this->currentFile = $currentFile;
        $this->index = $index;

        $this->genMessage();
    }


    protected function getInput(){

        if( !$this->input && $this->currentFile && $this->currentFile['filename'] ){
            $this->input = file_get_contents( $this->currentFile['filename'] );
        }
    }



    /**
     * Converts the exception to string
     *
     * @return string
     */
    public function genMessage(){

        if( $this->currentFile && $this->currentFile['filename'] ){
            $this->message .= ' in '.basename($this->currentFile['filename']);
        }

        if( $this->index !== null ){
            $this->getInput();
            if( $this->input ){
                $line = self::getLineNumber();
                $this->message .= ' on line '.$line.', column '.self::getColumn();

                $lines = explode("\n",$this->input);

                $count = count($lines);
                $start_line = max(0, $line-3);
                $last_line = min($count, $start_line+6);
                $num_len = strlen($last_line);
                for( $i = $start_line; $i < $last_line; $i++ ){
                    $this->message .= "\n".str_pad($i+1,$num_len,'0',STR_PAD_LEFT).'| '.$lines[$i];
                }
            }
        }

    }

    /**
     * Returns the line number the error was encountered
     *
     * @return integer
     */
    public function getLineNumber(){
        if( $this->index ){
            return substr_count($this->input, "\n", 0, $this->index) + 1;
        }
        return 1;
    }


    /**
     * Returns the column the error was encountered
     *
     * @return integer
     */
    public function getColumn(){

        $part = substr($this->input, 0, $this->index);
        $pos = strrpos($part,"\n");
        return $this->index - $pos;
    }

}


/**
 * Chunk Exception
 *
 * @package Less
 * @subpackage exception
 */
class Less_Exception_Chunk extends Less_Exception_Parser{


    protected $parserCurrentIndex = 0;

    protected $emitFrom = 0;

    protected $input_len;


    /**
     * Constructor
     *
     * @param string $input
     * @param Exception $previous Previous exception
     * @param integer $index The current parser index
     * @param Less_FileInfo|string $currentFile The file
     * @param integer $code The exception code
     */
    public function __construct($input, Exception $previous = null, $index = null, $currentFile = null, $code = 0){

        $this->message = 'ParseError: Unexpected input'; //default message

        $this->index = $index;

        $this->currentFile = $currentFile;

        $this->input = $input;
        $this->input_len = strlen($input);

        $this->Chunks();
        $this->genMessage();
    }


    /**
     * See less.js chunks()
     * We don't actually need the chunks
     *
     */
    protected function Chunks(){
        $level = 0;
        $parenLevel = 0;
        $lastMultiCommentEndBrace = null;
        $lastOpening = null;
        $lastMultiComment = null;
        $lastParen = null;

        for( $this->parserCurrentIndex = 0; $this->parserCurrentIndex < $this->input_len; $this->parserCurrentIndex++ ){
            $cc = $this->CharCode($this->parserCurrentIndex);
            if ((($cc >= 97) && ($cc <= 122)) || ($cc < 34)) {
                // a-z or whitespace
                continue;
            }

            switch ($cc) {

                // (
                case 40:
                    $parenLevel++;
                    $lastParen = $this->parserCurrentIndex;
                    continue;

                // )
                case 41:
                    $parenLevel--;
                    if( $parenLevel < 0 ){
                        return $this->fail("missing opening `(`");
                    }
                    continue;

                // ;
                case 59:
                    //if (!$parenLevel) { $this->emitChunk();	}
                    continue;

                // {
                case 123:
                    $level++;
                    $lastOpening = $this->parserCurrentIndex;
                    continue;

                // }
                case 125:
                    $level--;
                    if( $level < 0 ){
                        return $this->fail("missing opening `{`");

                    }
                    //if (!$level && !$parenLevel) { $this->emitChunk(); }
                    continue;
                // \
                case 92:
                    if ($this->parserCurrentIndex < $this->input_len - 1) { $this->parserCurrentIndex++; continue; }
                    return $this->fail("unescaped `\\`");

                // ", ' and `
                case 34:
                case 39:
                case 96:
                    $matched = 0;
                    $currentChunkStartIndex = $this->parserCurrentIndex;
                    for ($this->parserCurrentIndex = $this->parserCurrentIndex + 1; $this->parserCurrentIndex < $this->input_len; $this->parserCurrentIndex++) {
                        $cc2 = $this->CharCode($this->parserCurrentIndex);
                        if ($cc2 > 96) { continue; }
                        if ($cc2 == $cc) { $matched = 1; break; }
                        if ($cc2 == 92) {        // \
                            if ($this->parserCurrentIndex == $this->input_len - 1) {
                                return $this->fail("unescaped `\\`");
                            }
                            $this->parserCurrentIndex++;
                        }
                    }
                    if ($matched) { continue; }
                    return $this->fail("unmatched `" + chr($cc) + "`", $currentChunkStartIndex);

                // /, check for comment
                case 47:
                    if ($parenLevel || ($this->parserCurrentIndex == $this->input_len - 1)) { continue; }
                    $cc2 = $this->CharCode($this->parserCurrentIndex+1);
                    if ($cc2 == 47) {
                        // //, find lnfeed
                        for ($this->parserCurrentIndex = $this->parserCurrentIndex + 2; $this->parserCurrentIndex < $this->input_len; $this->parserCurrentIndex++) {
                            $cc2 = $this->CharCode($this->parserCurrentIndex);
                            if (($cc2 <= 13) && (($cc2 == 10) || ($cc2 == 13))) { break; }
                        }
                    } else if ($cc2 == 42) {
                        // /*, find */
                        $lastMultiComment = $currentChunkStartIndex = $this->parserCurrentIndex;
                        for ($this->parserCurrentIndex = $this->parserCurrentIndex + 2; $this->parserCurrentIndex < $this->input_len - 1; $this->parserCurrentIndex++) {
                            $cc2 = $this->CharCode($this->parserCurrentIndex);
                            if ($cc2 == 125) { $lastMultiCommentEndBrace = $this->parserCurrentIndex; }
                            if ($cc2 != 42) { continue; }
                            if ($this->CharCode($this->parserCurrentIndex+1) == 47) { break; }
                        }
                        if ($this->parserCurrentIndex == $this->input_len - 1) {
                            return $this->fail("missing closing `*/`", $currentChunkStartIndex);
                        }
                    }
                    continue;

                // *, check for unmatched */
                case 42:
                    if (($this->parserCurrentIndex < $this->input_len - 1) && ($this->CharCode($this->parserCurrentIndex+1) == 47)) {
                        return $this->fail("unmatched `/*`");
                    }
                    continue;
            }
        }

        if( $level !== 0 ){
            if( ($lastMultiComment > $lastOpening) && ($lastMultiCommentEndBrace > $lastMultiComment) ){
                return $this->fail("missing closing `}` or `*/`", $lastOpening);
            } else {
                return $this->fail("missing closing `}`", $lastOpening);
            }
        } else if ( $parenLevel !== 0 ){
            return $this->fail("missing closing `)`", $lastParen);
        }


        //chunk didn't fail


        //$this->emitChunk(true);
    }

    public function CharCode($pos){
        return ord($this->input[$pos]);
    }


    public function fail( $msg, $index = null ){

        if( !$index ){
            $this->index = $this->parserCurrentIndex;
        }else{
            $this->index = $index;
        }
        $this->message = 'ParseError: '.$msg;
    }


    /*
	function emitChunk( $force = false ){
		$len = $this->parserCurrentIndex - $this->emitFrom;
		if ((($len < 512) && !$force) || !$len) {
			return;
		}
		$chunks[] = substr($this->input, $this->emitFrom, $this->parserCurrentIndex + 1 - $this->emitFrom );
		$this->emitFrom = $this->parserCurrentIndex + 1;
	}
	*/

}


/**
 * Compiler Exception
 *
 * @package Less
 * @subpackage exception
 */
class Less_Exception_Compiler extends Less_Exception_Parser{

}

/**
 * Parser output with source map
 *
 * @package Less
 * @subpackage Output
 */
class Less_Output_Mapped extends Less_Output {

    /**
     * The source map generator
     *
     * @var Less_SourceMap_Generator
     */
    protected $generator;

    /**
     * Current line
     *
     * @var integer
     */
    protected $lineNumber = 0;

    /**
     * Current column
     *
     * @var integer
     */
    protected $column = 0;

    /**
     * Array of contents map (file and its content)
     *
     * @var array
     */
    protected $contentsMap = array();

    /**
     * Constructor
     *
     * @param array $contentsMap Array of filename to contents map
     * @param Less_SourceMap_Generator $generator
     */
    public function __construct(array $contentsMap, $generator){
        $this->contentsMap = $contentsMap;
        $this->generator = $generator;
    }

    /**
     * Adds a chunk to the stack
     * The $index for less.php may be different from less.js since less.php does not chunkify inputs
     *
     * @param string $chunk
     * @param string $fileInfo
     * @param integer $index
     * @param mixed $mapLines
     */
    public function add($chunk, $fileInfo = null, $index = 0, $mapLines = null){

        //ignore adding empty strings
        if( $chunk === '' ){
            return;
        }


        $sourceLines = array();
        $sourceColumns = ' ';


        if( $fileInfo ){

            $url = $fileInfo['currentUri'];

            if( isset($this->contentsMap[$url]) ){
                $inputSource = substr($this->contentsMap[$url], 0, $index);
                $sourceLines = explode("\n", $inputSource);
                $sourceColumns = end($sourceLines);
            }else{
                throw new Exception('Filename '.$url.' not in contentsMap');
            }

        }

        $lines = explode("\n", $chunk);
        $columns = end($lines);

        if($fileInfo){

            if(!$mapLines){
                $this->generator->addMapping(
                    $this->lineNumber + 1,					// generated_line
                    $this->column,							// generated_column
                    count($sourceLines),					// original_line
                    strlen($sourceColumns),					// original_column
                    $fileInfo
                );
            }else{
                for($i = 0, $count = count($lines); $i < $count; $i++){
                    $this->generator->addMapping(
                        $this->lineNumber + $i + 1,				// generated_line
                        $i === 0 ? $this->column : 0,			// generated_column
                        count($sourceLines) + $i,				// original_line
                        $i === 0 ? strlen($sourceColumns) : 0, 	// original_column
                        $fileInfo
                    );
                }
            }
        }

        if(count($lines) === 1){
            $this->column += strlen($columns);
        }else{
            $this->lineNumber += count($lines) - 1;
            $this->column = strlen($columns);
        }

        // add only chunk
        parent::add($chunk);
    }

}

/**
 * Encode / Decode Base64 VLQ.
 *
 * @package Less
 * @subpackage SourceMap
 */
class Less_SourceMap_Base64VLQ {

    /**
     * Shift
     *
     * @var integer
     */
    private $shift = 5;

    /**
     * Mask
     *
     * @var integer
     */
    private $mask = 0x1F; // == (1 << shift) == 0b00011111

    /**
     * Continuation bit
     *
     * @var integer
     */
    private $continuationBit = 0x20; // == (mask - 1 ) == 0b00100000

    /**
     * Char to integer map
     *
     * @var array
     */
    private $charToIntMap = array(
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6,
        'H' => 7,'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13,
        'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20,
        'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25, 'a' => 26, 'b' => 27,
        'c' => 28, 'd' => 29, 'e' => 30, 'f' => 31, 'g' => 32, 'h' => 33, 'i' => 34,
        'j' => 35, 'k' => 36, 'l' => 37, 'm' => 38, 'n' => 39, 'o' => 40, 'p' => 41,
        'q' => 42, 'r' => 43, 's' => 44, 't' => 45, 'u' => 46, 'v' => 47, 'w' => 48,
        'x' => 49, 'y' => 50, 'z' => 51, 0 => 52, 1 => 53, 2 => 54, 3 => 55, 4 => 56,
        5 => 57,	6 => 58, 7 => 59, 8 => 60, 9 => 61, '+' => 62, '/' => 63,
    );

    /**
     * Integer to char map
     *
     * @var array
     */
    private $intToCharMap = array(
        0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D', 4 => 'E', 5 => 'F', 6 => 'G',
        7 => 'H', 8 => 'I', 9 => 'J', 10 => 'K', 11 => 'L', 12 => 'M', 13 => 'N',
        14 => 'O', 15 => 'P', 16 => 'Q', 17 => 'R', 18 => 'S', 19 => 'T', 20 => 'U',
        21 => 'V', 22 => 'W', 23 => 'X', 24 => 'Y', 25 => 'Z', 26 => 'a', 27 => 'b',
        28 => 'c', 29 => 'd', 30 => 'e', 31 => 'f', 32 => 'g', 33 => 'h', 34 => 'i',
        35 => 'j', 36 => 'k', 37 => 'l', 38 => 'm', 39 => 'n', 40 => 'o', 41 => 'p',
        42 => 'q', 43 => 'r', 44 => 's', 45 => 't', 46 => 'u', 47 => 'v', 48 => 'w',
        49 => 'x', 50 => 'y', 51 => 'z', 52 => '0', 53 => '1', 54 => '2', 55 => '3',
        56 => '4', 57 => '5', 58 => '6', 59 => '7', 60 => '8', 61 => '9', 62 => '+',
        63 => '/',
    );

    /**
     * Constructor
     */
    public function __construct(){
        // I leave it here for future reference
        // foreach(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/') as $i => $char)
        // {
        //	 $this->charToIntMap[$char] = $i;
        //	 $this->intToCharMap[$i] = $char;
        // }
    }

    /**
     * Convert from a two-complement value to a value where the sign bit is
     * is placed in the least significant bit.	For example, as decimals:
     *	 1 becomes 2 (10 binary), -1 becomes 3 (11 binary)
     *	 2 becomes 4 (100 binary), -2 becomes 5 (101 binary)
     * We generate the value for 32 bit machines, hence -2147483648 becomes 1, not 4294967297,
     * even on a 64 bit machine.
     * @param string $aValue
     */
    public function toVLQSigned($aValue){
        return 0xffffffff & ($aValue < 0 ? ((-$aValue) << 1) + 1 : ($aValue << 1) + 0);
    }

    /**
     * Convert to a two-complement value from a value where the sign bit is
     * is placed in the least significant bit. For example, as decimals:
     *	 2 (10 binary) becomes 1, 3 (11 binary) becomes -1
     *	 4 (100 binary) becomes 2, 5 (101 binary) becomes -2
     * We assume that the value was generated with a 32 bit machine in mind.
     * Hence
     *	 1 becomes -2147483648
     * even on a 64 bit machine.
     * @param integer $aValue
     */
    public function fromVLQSigned($aValue){
        return $aValue & 1 ? $this->zeroFill(~$aValue + 2, 1) | (-1 - 0x7fffffff) : $this->zeroFill($aValue, 1);
    }

    /**
     * Return the base 64 VLQ encoded value.
     *
     * @param string $aValue The value to encode
     * @return string The encoded value
     */
    public function encode($aValue){
        $encoded = '';
        $vlq = $this->toVLQSigned($aValue);
        do
        {
            $digit = $vlq & $this->mask;
            $vlq = $this->zeroFill($vlq, $this->shift);
            if($vlq > 0){
                $digit |= $this->continuationBit;
            }
            $encoded .= $this->base64Encode($digit);
        } while($vlq > 0);

        return $encoded;
    }

    /**
     * Return the value decoded from base 64 VLQ.
     *
     * @param string $encoded The encoded value to decode
     * @return integer The decoded value
     */
    public function decode($encoded){
        $vlq = 0;
        $i = 0;
        do
        {
            $digit = $this->base64Decode($encoded[$i]);
            $vlq |= ($digit & $this->mask) << ($i * $this->shift);
            $i++;
        } while($digit & $this->continuationBit);

        return $this->fromVLQSigned($vlq);
    }

    /**
     * Right shift with zero fill.
     *
     * @param integer $a number to shift
     * @param integer $b number of bits to shift
     * @return integer
     */
    public function zeroFill($a, $b){
        return ($a >= 0) ? ($a >> $b) : ($a >> $b) & (PHP_INT_MAX >> ($b - 1));
    }

    /**
     * Encode single 6-bit digit as base64.
     *
     * @param integer $number
     * @return string
     * @throws Exception If the number is invalid
     */
    public function base64Encode($number){
        if($number < 0 || $number > 63){
            throw new Exception(sprintf('Invalid number "%s" given. Must be between 0 and 63.', $number));
        }
        return $this->intToCharMap[$number];
    }

    /**
     * Decode single 6-bit digit from base64
     *
     * @param string $char
     * @return number
     * @throws Exception If the number is invalid
     */
    public function base64Decode($char){
        if(!array_key_exists($char, $this->charToIntMap)){
            throw new Exception(sprintf('Invalid base 64 digit "%s" given.', $char));
        }
        return $this->charToIntMap[$char];
    }

}


/**
 * Source map generator
 *
 * @package Less
 * @subpackage Output
 */
class Less_SourceMap_Generator extends Less_Configurable {

    /**
     * What version of source map does the generator generate?
     */
    const VERSION = 3;

    /**
     * Array of default options
     *
     * @var array
     */
    protected $defaultOptions = array(
        // an optional source root, useful for relocating source files
        // on a server or removing repeated values in the 'sources' entry.
        // This value is prepended to the individual entries in the 'source' field.
        'sourceRoot'			=> '',

        // an optional name of the generated code that this source map is associated with.
        'sourceMapFilename'		=> null,

        // url of the map
        'sourceMapURL'			=> null,

        // absolute path to a file to write the map to
        'sourceMapWriteTo'		=> null,

        // output source contents?
        'outputSourceFiles'		=> false,

        // base path for filename normalization
        'sourceMapRootpath'		=> '',

        // base path for filename normalization
        'sourceMapBasepath'   => ''
    );

    /**
     * The base64 VLQ encoder
     *
     * @var Less_SourceMap_Base64VLQ
     */
    protected $encoder;

    /**
     * Array of mappings
     *
     * @var array
     */
    protected $mappings = array();

    /**
     * The root node
     *
     * @var Less_Tree_Ruleset
     */
    protected $root;

    /**
     * Array of contents map
     *
     * @var array
     */
    protected $contentsMap = array();

    /**
     * File to content map
     *
     * @var array
     */
    protected $sources = array();
    protected $source_keys = array();

    /**
     * Constructor
     *
     * @param Less_Tree_Ruleset $root The root node
     * @param array $options Array of options
     */
    public function __construct(Less_Tree_Ruleset $root, $contentsMap, $options = array()){
        $this->root = $root;
        $this->contentsMap = $contentsMap;
        $this->encoder = new Less_SourceMap_Base64VLQ();

        $this->SetOptions($options);


        // fix windows paths
        if( !empty($this->options['sourceMapRootpath']) ){
            $this->options['sourceMapRootpath'] = str_replace('\\', '/', $this->options['sourceMapRootpath']);
            $this->options['sourceMapRootpath'] = rtrim($this->options['sourceMapRootpath'],'/').'/';
        }
    }

    /**
     * Generates the CSS
     *
     * @return string
     */
    public function generateCSS(){
        $output = new Less_Output_Mapped($this->contentsMap, $this);

        // catch the output
        $this->root->genCSS($output);


        $sourceMapUrl				= $this->getOption('sourceMapURL');
        $sourceMapFilename			= $this->getOption('sourceMapFilename');
        $sourceMapContent			= $this->generateJson();
        $sourceMapWriteTo			= $this->getOption('sourceMapWriteTo');

        if( !$sourceMapUrl && $sourceMapFilename ){
            $sourceMapUrl = $this->normalizeFilename($sourceMapFilename);
        }

        // write map to a file
        if( $sourceMapWriteTo ){
            $this->saveMap($sourceMapWriteTo, $sourceMapContent);
        }

        // inline the map
        if( !$sourceMapUrl ){
            $sourceMapUrl = sprintf('data:application/json,%s', Less_Functions::encodeURIComponent($sourceMapContent));
        }

        if( $sourceMapUrl ){
            $output->add( sprintf('/*# sourceMappingURL=%s */', $sourceMapUrl) );
        }

        return $output->toString();
    }

    /**
     * Saves the source map to a file
     *
     * @param string $file The absolute path to a file
     * @param string $content The content to write
     * @throws Exception If the file could not be saved
     */
    protected function saveMap($file, $content){
        $dir = dirname($file);
        // directory does not exist
        if( !is_dir($dir) ){
            // FIXME: create the dir automatically?
            throw new Exception(sprintf('The directory "%s" does not exist. Cannot save the source map.', $dir));
        }
        // FIXME: proper saving, with dir write check!
        if(file_put_contents($file, $content) === false){
            throw new Exception(sprintf('Cannot save the source map to "%s"', $file));
        }
        return true;
    }

    /**
     * Normalizes the filename
     *
     * @param string $filename
     * @return string
     */
    protected function normalizeFilename($filename){

        $filename = str_replace('\\', '/', $filename);
        $rootpath = $this->getOption('sourceMapRootpath');
        $basePath = $this->getOption('sourceMapBasepath');

        // "Trim" the 'sourceMapBasepath' from the output filename.
        if (strpos($filename, $basePath) === 0) {
            $filename = substr($filename, strlen($basePath));
        }

        // Remove extra leading path separators.
        if(strpos($filename, '\\') === 0 || strpos($filename, '/') === 0){
            $filename = substr($filename, 1);
        }

        return $rootpath . $filename;
    }

    /**
     * Adds a mapping
     *
     * @param integer $generatedLine The line number in generated file
     * @param integer $generatedColumn The column number in generated file
     * @param integer $originalLine The line number in original file
     * @param integer $originalColumn The column number in original file
     * @param string $sourceFile The original source file
     */
    public function addMapping($generatedLine, $generatedColumn, $originalLine, $originalColumn, $fileInfo ){

        $this->mappings[] = array(
            'generated_line' => $generatedLine,
            'generated_column' => $generatedColumn,
            'original_line' => $originalLine,
            'original_column' => $originalColumn,
            'source_file' => $fileInfo['currentUri']
        );

        $this->sources[$fileInfo['currentUri']] = $fileInfo['filename'];
    }


    /**
     * Generates the JSON source map
     *
     * @return string
     * @see https://docs.google.com/document/d/1U1RGAehQwRypUTovF1KRlpiOFze0b-_2gc6fAH0KY0k/edit#
     */
    protected function generateJson(){

        $sourceMap = array();
        $mappings = $this->generateMappings();

        // File version (always the first entry in the object) and must be a positive integer.
        $sourceMap['version'] = self::VERSION;


        // An optional name of the generated code that this source map is associated with.
        $file = $this->getOption('sourceMapFilename');
        if( $file ){
            $sourceMap['file'] = $file;
        }


        // An optional source root, useful for relocating source files on a server or removing repeated values in the 'sources' entry.	This value is prepended to the individual entries in the 'source' field.
        $root = $this->getOption('sourceRoot');
        if( $root ){
            $sourceMap['sourceRoot'] = $root;
        }


        // A list of original sources used by the 'mappings' entry.
        $sourceMap['sources'] = array();
        foreach($this->sources as $source_uri => $source_filename){
            $sourceMap['sources'][] = $this->normalizeFilename($source_filename);
        }


        // A list of symbol names used by the 'mappings' entry.
        $sourceMap['names'] = array();

        // A string with the encoded mapping data.
        $sourceMap['mappings'] = $mappings;

        if( $this->getOption('outputSourceFiles') ){
            // An optional list of source content, useful when the 'source' can't be hosted.
            // The contents are listed in the same order as the sources above.
            // 'null' may be used if some original sources should be retrieved by name.
            $sourceMap['sourcesContent'] = $this->getSourcesContent();
        }

        // less.js compat fixes
        if( count($sourceMap['sources']) && empty($sourceMap['sourceRoot']) ){
            unset($sourceMap['sourceRoot']);
        }

        return json_encode($sourceMap);
    }

    /**
     * Returns the sources contents
     *
     * @return array|null
     */
    protected function getSourcesContent(){
        if(empty($this->sources)){
            return;
        }
        $content = array();
        foreach($this->sources as $sourceFile){
            $content[] = file_get_contents($sourceFile);
        }
        return $content;
    }

    /**
     * Generates the mappings string
     *
     * @return string
     */
    public function generateMappings(){

        if( !count($this->mappings) ){
            return '';
        }

        $this->source_keys = array_flip(array_keys($this->sources));


        // group mappings by generated line number.
        $groupedMap = $groupedMapEncoded = array();
        foreach($this->mappings as $m){
            $groupedMap[$m['generated_line']][] = $m;
        }
        ksort($groupedMap);

        $lastGeneratedLine = $lastOriginalIndex = $lastOriginalLine = $lastOriginalColumn = 0;

        foreach($groupedMap as $lineNumber => $line_map){
            while(++$lastGeneratedLine < $lineNumber){
                $groupedMapEncoded[] = ';';
            }

            $lineMapEncoded = array();
            $lastGeneratedColumn = 0;

            foreach($line_map as $m){
                $mapEncoded = $this->encoder->encode($m['generated_column'] - $lastGeneratedColumn);
                $lastGeneratedColumn = $m['generated_column'];

                // find the index
                if( $m['source_file'] ){
                    $index = $this->findFileIndex($m['source_file']);
                    if( $index !== false ){
                        $mapEncoded .= $this->encoder->encode($index - $lastOriginalIndex);
                        $lastOriginalIndex = $index;

                        // lines are stored 0-based in SourceMap spec version 3
                        $mapEncoded .= $this->encoder->encode($m['original_line'] - 1 - $lastOriginalLine);
                        $lastOriginalLine = $m['original_line'] - 1;

                        $mapEncoded .= $this->encoder->encode($m['original_column'] - $lastOriginalColumn);
                        $lastOriginalColumn = $m['original_column'];
                    }
                }

                $lineMapEncoded[] = $mapEncoded;
            }

            $groupedMapEncoded[] = implode(',', $lineMapEncoded) . ';';
        }

        return rtrim(implode($groupedMapEncoded), ';');
    }

    /**
     * Finds the index for the filename
     *
     * @param string $filename
     * @return integer|false
     */
    protected function findFileIndex($filename){
        return $this->source_keys[$filename];
    }

} 