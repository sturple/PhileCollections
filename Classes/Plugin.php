<?php
/**
 * Plugin class
 */
namespace Phile\Plugin\Sturple\PhileCollections;

use Phile\Event;
use Phile\Registry;
use Phile\Repository\PageCollection;
use Phile\ServiceLocator;
use Phile\ServiceLocator\TemplateInterface;
use Phile\Utility;
/**
* Plugin Class PhileCollections
* @author sturple
* @link https://github.com/sturple/phileCollections
* @license http://oensource.org/licenses/MIT
* @package Phile\Plugin\Sturple\PhileCollections
*
*/
class Plugin extends \Phile\Plugin\AbstractPlugin implements \Phile\Gateway\EventObserverInterface {
	
	protected $events = [		
		'template_engine_registered' => 'onTemplateEngineRegistered'
	];	

	protected $logger;
	static $allowed_operators = ['==','===','!=','<>','<','>','<=','>='];
	
	/**
	 * @var array object storage for initialized objects, to prevent multiple loading of objects.
	 */
	protected $storage = array();
	/**
	 * @var \Phile\ServiceLocator\CacheInterface the cache implementation
	 */
	protected $cache = null;	
	/**
	 * the constructor
	 */
	public function __construct() {
		\Phile\Event::registerEvent('plugins_loaded', $this);
		$this->logger = (new \Phile\Plugin\Sturple\PhileLogger\Plugin($relDir='lib/cache/logs',  $logLevel='debug', $options=array()))->getLogger();

	}

	/**
	 * the onPluginsLoaded method
	 *
	 * @param array   $data
	 * @return mixed|void
	 */
	
	public function onTemplateEngineRegistered($data = array()){
		if (ServiceLocator::hasService('Phile_Cache')) {
			$this->cache = ServiceLocator::getService('Phile_Cache');
		}				
		$collections = $this->getCollections();
		foreach($collections as $key=>$value){
			$data['data'][$key] = $value;
		}
		if (isset($this->settings['twigsimplefilters'])){
			$this->setTwigSimpleFilters($data['engine']);
		}
		if (isset($this->settings['twigsimplefunctions'])){
			$this->setTwigSimpleFunctions($data['engine']);
		}
		
		
	}
	
	/**
	 *	setTwigSimpleFilters

	 *	@param $engine
	 *	@return void
	 *	This creates Twig simple filters from the config file config['sturple\\phileCollections']['plugins']['twigsimplefilters']. 	
	 */		
	protected function setTwigSimpleFilters($engine){
		$filters = array();
		if (!empty($this->settings['twigsimplefilters'])){
			foreach ($this->settings['twigsimplefilters'] as $name => $function){
				$filters[$name] = new \Twig_SimpleFilter($name, $function);
				$engine->addFilter($filters[$name]);				
			}
		}
	}
	/**
	 *	setTwigSimpleFunction

	 *	@param $engine
	 *	@return void
	 *	@todo This function does not currenly work.
	 *	This creates Twig simple functions from the config file config['sturple\\phileCollections']['plugins']['twigsimplefunctions'].
	 *	
	 */		
	protected function setTwigSimpleFunctions($engine){
		$functions = array();
		$this->logger->info('functions ',$this->settings['twigsimplefunctions']);
		if (!empty($this->settings['twigsimplefunctions'])){
			foreach ($this->settings['twigsimplefunctions'] as $name => $function){
				$functions[$name] = new \Twig_SimpleFunction($name, $function);
				$engine->addFunction($functions[$name]);				
			}
		}		
		
	}
	/**
	 *	getCollections	
	 *	@param $engine
	 *	@return void
	 *	Gets the collections to use in twig template from the config file config['sturple\\phileCollections']['plugins']['collections']
	 */			
	protected function getCollections()
	{
		$collections = [];
		// get collections to insert into twig template
		if (!empty($this->settings['collections'])){
			foreach ($this->settings['collections'] as $key=>$options){
				$folder = isset($options['pages_folder']) ? CONTENT_DIR . trim($options['pages_folder'],'/')  : CONTENT_DIR;
				$pc = $this->findAll($options, $folder);
				$collections[$key] = $pc;			
			}			
		}
		return $collections;
	}
	
	
	/**
	 *	findAll 	
	 *	@param $options
	 *	@param $folder
	 *	@return void
	 *	Gets the collections to use in twig template from the config file config['sturple\\phileCollections']['plugins']['collections']
	 *	This is modified from \Phile\Model\Page
	 */				
	public function findAll(array $options = array(), $folder = CONTENT_DIR) {
		return new PageCollection(function() use ($options, $folder){					
			// ignore files with a leading '.' in its filename
			$files = Utility::getFiles($folder, '\Phile\FilterIterator\ContentFileFilterIterator');
			$pages = array();
			foreach ($files as $file) {				
				if (str_replace($folder, '', $file) == '404' . CONTENT_EXT) {
					// jump to next page if file is the 404 page
					continue;
				}
				$page = $this->getPage($file, $folder, $options);
				if ($page !== false){
					$pages[] = $page;
				}				
			}
			
			if (empty($options['pages_order'])) {
				return $pages;
			}			
			return $this->applyFilter($pages,$options);
		});
	}
	
	/**
	 *	applyQuery	
	 *	@param &$page
	 *	@param $options
	 *	@return Phile\Repository\Page
	 *	This function checks query a query type can either be set as an AND or an OR.
	 *	Sets up flag $pass, by default it sets it up as a pass unless one of the conditions turn false, then flag gets set.
	 */		
	protected function applyQuery($page, $options){
		$data = $page->getMeta()->getAll();
		$data['pageId'] = $page->getPageId();
		$data['url'] = $page->getUrl();

		$passArray = array();
		// so this needs to be a false value for an AND and a true for OR value
		$pages_query_type = isset($options['pages_query_type']) ? (strtolower($options['pages_query_type']) == 'or') : false;
		// go through all queries
		if (!empty($options['pages_query'])) 
		{
			foreach ($options['pages_query'] as $q ){
				// getting value or a default value if set if no value or no default returns empty string;
				$value = isset($data[$q['field']]) ? $data[$q['field']] : (isset($q['default']) ? $q['default'] : '');
				$compare = $q['value'];
				$operator = trim(strtolower($q['operator']));
				$pass = true;				
				// only two types of operator 'regex' or a php comparison operator, all allowed variables are added to self::$allowed_operators
				switch ($operator) {							
					case 'regex' :
						$pass = (preg_match($compare, $value, $matches) > 0);
						break;
					default:
						// check to make sure operator is valid, otherwise shouldn't be checking.
						if (in_array($operator,self::$allowed_operators)){							
							if (!(empty($value)) and !(empty($compare))){
								$pass =   eval("return {$value}" . $operator . "{$compare};") ;		
							}													
						}
						// means that this operator wasnt valid throw an exception
						else {
							throw new \RuntimeException("Operator '{$operator}' is not valid, here are valid operators ". implode(',',self::$allowed_operators) .
														'While processing this comparison [ ' .$q['field']." {$value}" . $operator . "{$compare} ]", 3472004);
							
						}
						break;
				}
				//adding pass flags for each comparison
				$passArray[] = $pass;
								
			}
		}
		//or operator	checking all flags
		if ($pages_query_type){
			if (!in_array(true,$passArray) ){ return false;	}
		}
		// and operator checking all flags
		else { if (in_array(false,$passArray,TRUE) ){return false;	}}
		return $page;				
	}
	
	
	/**
	 *	applyFilter	
	 *	@param &$pages
	 *	@param $options
	 *	@return Phile\Repository\PageCollection
	 *	This is the same sort feature in the Phile\Model\Page in findAll() except seperated.
	 */	
	protected function applyFilter(&$pages, $options){
			// parse search	criteria
			$terms = preg_split('/\s+/', $options['pages_order'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($terms as $term) {
				$term = explode('.', $term);
				if (count($term) > 1) {
					$type = array_shift($term);
				} else {
					$type = null;
				}
				$term = explode(':', $term[0]);
				$sorting[] = array('type' => $type, 'key' => $term[0], 'order' => $term[1]);
			}

			// prepare search criteria for array_multisort
			foreach ($sorting as $sort) {
				$key = $sort['key'];
				$column = array();
				foreach ($pages as $page) {
					/** @var \Phile\Model\Page $page */
					$meta = $page->getMeta();
					if ($sort['type'] === 'page') {
						$method = 'get' . ucfirst($key);
						$value = $page->$method();
					} elseif ($sort['type'] === 'meta') {
						$value = $meta->get($key);
					} else {
						continue 2; // ignore unhandled search term
					}
					$column[] = $value;
				}
				$sortHelper[] = $column;
				$sortHelper[] = constant('SORT_' . strtoupper($sort['order']));
			}
			$sortHelper[] = &$pages;
			call_user_func_array('array_multisort', $sortHelper);
			return $pages;
	}
	
	
	
	/**
	 * get page from cache or filepath
	 *
	 * @param        $filePath
	 * @param string $folder
	 *
	 * @return mixed|\Phile\Model\Page
	 */
	protected function getPage($filePath, $folder = CONTENT_DIR , $options = array()) {
		$key = 'Phile_Model_Page_' . md5($filePath);
		if (isset($this->storage[$key])) {
			return $this->storage[$key];
		}
		
		if ($this->cache !== null) {
			if ($this->cache->has($key)) {
				$page = $this->cache->get($key);
			} else {
				$page = new \Phile\Model\Page($filePath, $folder);
				$ts = filemtime($page->getFilePath());			
				$page->getMeta()->set('date', $ts);				
				$this->cache->set($key, $page);
			}
		}
		else		
		{
			$page = new \Phile\Model\Page($filePath, $folder);
			if (isset($options['pages_meta'])){
				foreach ($options['pages_meta'] as $key=>$meta){
					$page->getMeta()->set(strtolower($key),$meta);
				}				
			}
			$ts = filemtime($page->getFilePath());			
			$page->getMeta()->set('date', $ts);
		}
			
			return $this->applyQuery($page, $options);
			
	}		
	
}