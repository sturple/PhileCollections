# Adding Collections and Twig Simple Filter Creation Support For Phile CMS

This plugin allows the user to add collections and  [Twig Simple Filters](http://twig.sensiolabs.org/doc/advanced.html#filters).

A ```colection``` is a group of Pages which have certain criteria, and can be sorted.




## Installation
### Composer
```
php composer.phar require sturple/phile-collections:dev-master
```

### Download
```
* Install [Phile](https://github.com/PhileCMS/Phile)
* Clone this https://github.com/sturple/phileCollections repo into `plugins/sturple/phileCollections/
```

## Usage

### Collections Example

Simply add a collections array to your phileCollections  plugin parameters.
Each collection will then become available in your template as a Page object, having all the same parameters as a Page Object.

#### Configuration

config.php

```php
$config['plugins']['sturple\\phileCollections'] =
array(
	'collections' => array (
		'subpages' => array(
			'pages_query_type' 	=> 'and',
			'pages_query' 		=>	[
				['field'=>'url','operator'=>'regex', 'value'=>'/^sub\/.*/'],
				['field'=>'ispost', 'operator' =>'==', 'value'=>'true', 'default'=>'true'],
				['field'=>'date','operator'=>'>','value'=>strtotime("-1 day")]
			],
			'pages_order' 		=>	'meta.title:asc',
			'pages_meta'		=> 	array('Template'=>'navigation')
		),		
		'posts' =>  array(
			'pages_query_type' 	=> 'or',		
			'pages_folder' 		=>  "/sub",			
			'pages_order' 	=>	'meta.title:DESC',
			'pages_meta'		=> 	array(
				'Template'=>'post',
				
			)
		),
		
	)

);
```

#### Setting up collections

Each collections requires the following

| Variable | Values | Description |
| ------ | ------- | ----------- |
| pages_query_type | {and \| or} | This defines how the rules will be applied, rules can't be mixed, it is either **and** or **or** |
| pages_folder | relative folder | this is where query starts making sure to put / before folder.  If no value it uses the entire site |
| pages_query| array(field='',operator='', value='', default='') | each rule has to have 3 parts 4th optional.  See below for explination   |
| pages_order | meta.{field}:{asc\|desc}| This uses the same logic as the core pages sort |
| pages_meta| array of meta names and values| This is an array list with key value pairs of meta name and values that you want to automatically add to this collection |


Query Params

| Variable | Values | Description |
| ------ | ------- | -------|
| field | any meta field | * all meta fields, and pageid, modified date(date), and url is added to metadata automatically |
| operator | {regex \| ***php operator***} | php operators are == , != , <> , <|> , <=, >= }|
| value | value | this is the value you are checking against |
| default | default | **optional** If the page doesn't have meta it uses default |



#### Twig Code
``` twig
<ul>
	{% for subpage in subpages %}
		<li>
			<h3><a href="{{subpage.url}}" data-template="{{subpage.meta.template}}">{{subpage.title}}</a></h3>
			<em>{{subpage.content|striptags|limit_words}}</em>
		</li>		
	{% endfor %}
</ul>
```




### Simple Twig Filters

This is the exact same functionallity as used in this plugin [Twig-Filters-Plugin](https://github.com/PhileCMS/phileTwigFilters).
I have allowed these functions to be added to your config file, instead of being coded into plugin.

#### Configuration

config.php

```php
$config['plugins']['sturple\\phileCollections'] =
array(
	'twigsimplefilters' => array(
		'excerpt' => function ($string){
			return strip_tags(substr($string,0,strpos($string,"</p>") + 4));
		},
		'limit_words' => function($string){
				$limit = 100;
				$words = str_word_count($string, 2);
				$nbwords = count($words);
				$pos = array_keys($words);
				if ($limit >= $nbwords) {
					return trim($string);
				}
				else {
					return trim(substr($string, 0, $pos[$limit])) . '...';
				}			
		}
	)

);
```
#### Twig Code

this will strip all tags off content, and limit words to 100;

``` twig

	{{content|striptags|limit_words}}
	
```

## Runtime Errors

* collections query if an invalid operator is being used.



