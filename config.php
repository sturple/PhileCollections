<?php
/** config file */
return array(
	/**
	 *
	 * operator values {regex|==|!=|<>|<|>|<=|>=}
	 *
	 **/
	'collections' => array (
		'navigation2' => array(
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
			'pages_folder' 		=>  "/sub",			
			'pages_order' 	=>	'meta.title:DESC',
			'pages_meta'		=> 	array(
				'Template'=>'post',
				
			)
		),
		
	),
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