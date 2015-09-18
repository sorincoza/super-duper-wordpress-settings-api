<?php

class SDWPSettings{



	// current (temporary) items:
	public $fn; 	// holds the anonymous functions names
	private $__call_fn, 	// holds the name of the current function passed to __call()
			$pattern_dir,	// holds directory where the patterns are stored (including options patterns)
			$options_from_files,
			$_flat_array = array(),	// just an aux variable
			
			$prefix,
			$menu_title, $menu_icon, $menu_position, 
			$page_title, $page_counter,
			$section_counter,
			$group,
			$option_counter,
			
			$rendering_args
			;
	
	
	
	// configuration variable:
	public $config = array();
	
	
	// references to keys inside $config (for easier read and keeping track of current items)
	public $page, $section, $option;
	
	// keeping track of the top level page:
	private $last_top_level_page;
	
	

	
	public function __construct(){
		
		// add actions:
		if ( is_admin() ){
		  add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		  add_action( 'admin_init', array( $this, 'settings_init' ) );
		}
		
		if ( is_user_logged_in() ){
		  add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 999 );
		}
		
		

		// initialize variables:
		$this->pattern_dir = __DIR__ . '/patterns';
		$this->options_from_files = $this->get_options_from_files();
		
	    $this->prefix        = '_pre_';
	    $this->menu_title    = 'Custom Options';
	    $this->page_title    = $this->menu_title;

	    $this->menu_icon     = '';
	    $this->menu_position = 99;
	    $this->group         = $this->get_group_id();

	    $this->page_counter = 0;
	    $this->option_counter = 0;
	    $this->section_counter = 0;
	    
	    $this->rendering_args = array();
	    
	    $this->config = array();

		
	}
	
	
	public function __call( $fn, $args ){

		$this->__call_fn = strtolower( $fn );

		
		// priority:
		// 1. options
		// 2. sections
		// 3. pages
		// 4. position
		// 5. icon
		// 6. prefix
		
		
		// search for matches and pick the longest one (assumption is that it's more specific == higher priority) :
		$matched_type = '';
		$max_length = 0;
		$current_length = 0;
		foreach ( $this->options_from_files  as  $option_type ){
			$current_length = strlen( $option_type );
			if (  $current_length > $max_length   &&   $this->fn_contains( $option_type )  ){
				$max_length = $current_length;
				$matched_type = $option_type;
			}
		}
		
		
		
		
		if ( ! empty( $matched_type ) ){
			$args['type'] = $matched_type;
			$this->add_option( $args );
		}
		
		elseif(  $this->fn_contains( array( 'section' ) )  ){
			$this->add_section( $args );
		}
		
		elseif(  $this->fn_contains( array( 'page', 'menu' ) )  ){
			if(  $this->fn_contains( array( 'sub' ) )  ){
				$this->add_submenu_page( $args );
			}else{
				$this->add_menu_page( $args );
			}
		}
		
		elseif ( $this->fn_contains( 'node' ) ){
			$this->add_node( $args );
		}
		
		elseif ( $this->fn_contains( 'separator' ) ){
			$this->add_menu_separator( $args );
		}
				
		elseif ( $this->fn_contains( array( 'position', 'priority' ) ) ){
			$this->set_menu_position( $args );
		}
				
		elseif ( $this->fn_contains( 'icon' ) ){
			$this->set_menu_icon( $args );
		}
				
		elseif ( $this->fn_contains( 'prefix' ) ){
			$this->set_prefix( $args );
		}
		
		
		
		// special cases:
				
		elseif ( $this->fn_contains( 'title' ) ){
			$this->menu_title = $args[0];
			$this->page_title = $this->menu_title;
		}
		
		
		return $this;
		
	}
	
	
	



	public function set_prefix( $prefix ){
		$prefix = ( substr($prefix, -1) == '_' )  ?  $prefix  :  ( $prefix . '_' );
		
		$this->prefix = $prefix;
		$this->config['prefix'] = $prefix;
		
		return $this;
	}
	
	public function set_menu_position( $pos ){
		
		$args = func_get_args();
		$args = $this->extract_args( array( 'position' ), $args );
		
		$this->menu_position = $args['position'];
		return $this;
	}
	
	public function set_menu_icon( $icon ){
		
		$args = func_get_args();
		$args = $this->extract_args( array( 'icon' ), $args );
		
		$this->menu_icon = $args['icon'];
		return $this;
	}
	
	
	
	
	
	public function add_menu_separator( $separator_id = false ){
		$position = $this->menu_position;

		$args = func_get_args();
		$args = $this->extract_args( array( 'separator_id' ), $args );
		
		$separator_id = $args['separator_id'];
		

		// if the user wants a special id for his separator, then slugify his wish:
		$separator_id = $separator_id  ?  $this->slugify( $separator_id )  :  ( $this->prefix . 'separator_' . $position );
		
		$this->config['separators'][$position] = $separator_id;
		$this->menu_position++ ;
		
		return $this;

	}

	public function add_menu_page( $title ){
		
		$args = func_get_args();

		$this->init_new_menu_page( $args );
		
		return $this;
		
	}
	
	public function add_submenu_page( $title ){
		
		if ( empty($this->page) ){
			$this->add_menu_page( $this->page_title ); 
		}
		
		
		$args = func_get_args();
		$other_args = array( 'parent_slug' => $this->get_slug( $this->last_top_level_page ) );

		$this->init_new_menu_page( $args, $other_args );
		
		return $this;
		
	}
	
	public function add_documentation_page(  ){
		
		$args = func_get_args();

		$args['callback'] = array( $this, 'render_documentation' );

		
		$this->add_submenu_page( $args );
		$this->add_section( $args );

		
		return $this;
		
	}
	
	
	public function add_section( $title, $description = '' ){

		$args = func_get_args();
		
		$this->init_new_section( $args );

		return $this;
		
	}
	
	public function add_option(  ){
		$args = func_get_args();
		
		$this->init_new_option( $args );
		
		
		if ( $this->option['type'] === 'checkbox' ){
			$default = $this->option['default'];
			if ( $default === 'checked'  ||  $default === 'check' ){ $this->option['default'] = 1; }			
		}
		
		
		return $this;
	}

	public function add_node( $id, $title = '', $parent = '', $href = '', $group = '', $meta = array() ){
		
		$args = func_get_args();

		
		// get meta before flattening args:
		$meta = $this->get_key_val_recursive( 'meta', $args );
		
		// extract the flat keys we need:
		$args = $this->extract_args( array( 'id', 'title', 'parent', 'href', 'group' ), $args );
		
		// add the 'meta' key:
		$args = array_merge( $args, $meta );
		
		
		$this->config['nodes'][] = $args;
		
		return $this;
		
	}
	
	
	

	public function field_render( $passed_args ){
		// $passed_args  from  add_settings_field()
		
		$this->set_rendering_args( $passed_args );
		
		$html = $this->replace_placeholders();
		
		echo $html;
		
	}




	private function init_new_menu_page( $args, $other_args = array() ){
		$this->section_counter = 0; // every page starts with 0 sections

		$this->config['pages'][ $this->page_counter ] = array();		// initialize
		$this->page = &$this->config['pages'][ $this->page_counter ];  	// store the current reference
		
		$args = $this->extract_args( array( 'title' ), $args );
		
				
		// update current vars:
		$this->page_title = $args['title'];
		$this->menu_title = $args['title'];
		
		// check every key for icon and position:
		foreach ( $args  as  $key => $val ){
			if ( strpos( $key, 'icon' ) !== false ){
				$this->menu_icon = $val;
			}elseif ( strpos( $key, 'position' ) !== false   ||    strpos( $key, 'priority' ) !== false ) {
				$this->menu_position = $val;
			}
		}

		

		// init the new page
		$this->page = array(
				'title' => $this->page_title,
				'slug' => $this->get_slug(),
				'id' => $this->get_slug(),
				'icon' => $this->menu_icon,
				'position' => $this->menu_position,
				'counter' => $this->page_counter,
				'sections' => array()
			);
			
			
		foreach ( $other_args  as  $key => $arg ){
			$this->page[ $key ] = $arg ;
		}
		
		
		// now, if the page doesn't have a parent slug, then it is a top level page:
		$is_top_level_page = empty( $this->page['parent_slug'] )  ?  true  :  false  ;
		
		if ( $is_top_level_page ){ 
			$this->last_top_level_page = $this->page;
		}
		
			
		$this->menu_position++ ;
		$this->page_counter++ ;
		
	}
		
	private function init_new_section( $args, $other_args = array() ){
		// update current variables:
		$this->option_counter = 0;  // every section starts with 0 options
		
		
		if ( empty($this->page) ){
			$this->add_menu_page( $this->page_title ); 
		}
		
		
		$this->page['sections'][ $this->section_counter ] = array();			// initialize
		$this->section = &$this->page['sections'][ $this->section_counter ]; 	// store reference
		
		$args = $this->extract_args( array( 'title', 'description' ), $args );
		

		// add other parameters:
		$params = array(
				'id' => $this->get_section_id(),
				'group' => $this->get_group_id(),
				'counter' => $this->section_counter,
				'options' => array()
			);

		// merge everything to init the new section:
		$this->section = array_merge( $args, $params );

		$this->section_counter++ ;
	}
	
	private function init_new_option( $args, $fallback_args = array() ){
		
		if ( empty($this->page) ){
			$this->add_menu_page( $this->page_title ); 
		}
		if ( empty($this->section)  ||  empty($this->page['sections'][ $this->section['counter'] ]) ){ 
			$this->add_section( $this->page_title ); 
		}
		
		

		$this->section['options'][ $this->option_counter ] = array();			// initialize
		$this->option = &$this->section['options'][ $this->option_counter ];	// store reference


		$args = $this->extract_args( array('id', 'title', 'description', 'default', 'type' ), $args );


		$this->option = $args;

		$this->option['counter'] = $this->option_counter;
			
			
		foreach ( $fallback_args  as  $key => $val ){
			if ( empty( $this->option[ $key ] ) ) {
				$this->option[ $key ] = $val;
			}
		}
		

		$this->option_counter++ ;

	}
	
	
	
	
	
	private function extract_args( $keys = array(), $args = array() ){
		
		// init our aux variable with an empty array:
		$this->_flat_array = array();
		
		// callback can be an array - no need to flatten it, so we find and protect it:
		$callback = $this->get_key_val_recursive( 'callback', $args );
		if ( !empty( $callback ) ){  $this->unset_key_recursive_by_reference( 'callback', $args );  }

		// flatten the args array:
		array_walk_recursive(  $args, array( $this, 'array_walk_cb' )  );
		$args = $this->_flat_array;
		

		// we need to return something for every key that was asked:
		foreach ( $keys  as  $key ){
			if ( ! isset( $args[$key] ) ){
				
				
				$args[$key] = '';
				
				foreach ( $args  as  $i => $val ){
					if ( is_int( $i ) ){
						$args[$key] = $val;
						unset( $args[$i] );			/***** TAKE NOTICE OF THIS unset() ********/
						break;
					}
				}


			}

		}
		

		$args = array_merge( $args, $callback );

		return $args;
		
		
	}
	
	private function array_walk_cb( $val, $key ){
		if ( is_int( $key ) ){
			$this->_flat_array[] = $val;
		}else{
			$this->_flat_array[$key] = $val;
		}
	}


	
	
	
	private function insert_menu_separators(  ){
		
		if (  ! isset( $this->config['separators'] )  ){
			return;
		}


		global $menu;
		
		foreach ( $this->config['separators']  as  $position => $separator_id ){
		
			// add separator at ( menu_position ):
			$menu[ $position ] = array(
				0	=>	'',
				1	=>	'read',
				2	=>	$separator_id,
				3	=>	'',
				4	=>	'wp-menu-separator'
			);
			
		}
		
		ksort($menu);
		
	}
	
	public function admin_menu(  ) { 
		
		$pages = &$this->config['pages'];
		
		foreach ( $pages  as  &$page ){
			
			// build lambda function:
			$_page_slug = $page['slug'];
			$_sections_array = array();    // this will be json_encoded a little later
			
			foreach ( $page['sections']  as  &$section ){
				
				// add the options we need
				$_sections_array[] = array(
					'group' => $section['group'],
					'opt_count' => count( $section['options'] ),
				);
				
			}
			
			
			$fn_content = $this->get_pattern( 'add-menu-page-cb.php' );

			
			$fn_content = str_replace(   
				array( '{{PAGE_SLUG}}'	, '{{SECTIONS_ARRAY}}'				,  '{{FN_RENDER_SECTIONS}}' ),  
				array( $_page_slug		,  json_encode( $_sections_array )	,  get_class( $this ) . '::render_sections' ),  
				$fn_content   
			);


			$this->fn = create_function( '', $fn_content );


			// now add the menu or submenu page:
			if ( isset( $page['parent_slug'] ) ){
			
			
				add_submenu_page(
					$page['parent_slug'],
					$page['title'],
					$page['title'],
					'manage_options',
					$page['slug'],
					$this->fn
				);
				
				
			}else{
			
			
				add_menu_page(
					$page['title'],
					$page['title'],
					'manage_options',
					$page['slug'],
					$this->fn,
					$page['icon'],
					$page['position']
				);
				
				
			}
			
		}
		
		// and add separators, if any:
		$this->insert_menu_separators();
	
	}
	
	public function admin_bar_menu( ){
		
		if ( empty( $this->config['nodes'] ) ){
			return;
		}
		
		
		global $wp_admin_bar;
		
		$nodes = &$this->config['nodes'];
		foreach ( $nodes  as  &$node ){

			$wp_admin_bar->add_node( $node );

		}
		
	}

	public function settings_init(  ) { 



		$pages = &$this->config['pages'];
		foreach ( $pages  as  &$page ){

			
			
			$sections = $page['sections'];
			foreach ( $sections  as  &$section ){
				
				$callback = empty( $section['callback'] )  ?  array( $this, '__section__callback' )  :  $section['callback'];
	
				add_settings_section(
					$section['id'],
					$section['title'],
					$callback,
					$page['id']
				);
				
				
				
				$options = $section['options'];
				foreach ( $options  as  &$option ){
					
					register_setting( $section['group'], $option['id'] );

					add_settings_field(
						$option['id'],
						$option['title'],
						array( $this, 'field_render' ),	//render function
						$page['id'],
						$section['id'],
						array( 
							'label_for' => $option['id'],
							'id' => $option['id'],
							'description' => $option['description'],
							'default' => $option['default'],
							'option' => $option
						)
					);
					
				}
				
			}
			
		}
		
	}
	
	


	
	private function set_rendering_args( $args, $default_default = '' ){
		
		$this->rendering_args = $args['option'];
		
		$this->rendering_args['name'] = $args['id'];

	}
	
	private function replace_placeholders(  ){
		
		$pattern = $this->get_pattern( 'options/' . $this->rendering_args['type'] . '.html' );
				

		$this->rendering_args['checked'] = checked(  get_option( $this->rendering_args['id'], $this->rendering_args['default'] ),  1  ,  false  );
		$this->rendering_args['content'] = get_option(  $this->rendering_args['id'],  $this->rendering_args['default']  );
		$this->rendering_args['value'] = $this->rendering_args['content'] ;	// 'value' as synonym for 'content' here
	

		foreach ( $this->rendering_args  as  $key => $arg ){
			
			if ( ! is_array($arg) ){
				$placeholder = '{{' . $key . '}}' ;
				$pattern = str_ireplace(  $placeholder, $arg, $pattern  );	// case-insensitive replace
			}
			
		}
		
		// remove all unmatched placeholders:
		$pattern = preg_replace( '/{{([^{|}]*)}}/', '', $pattern);


		return $pattern;
		
	}




	private function fn_contains( $arg ){
		
		if ( is_array($arg) ){
			foreach ( $arg  as  $str ){
				if ( strpos($this->__call_fn, $str) !== false ) {
					return true;
					break;
				}
			}
		}else{
			return ( strpos($this->__call_fn, $arg) !== false );
		}
		
		return false;
	}
	
	
	
	
	
	public function render_documentation(){
		echo $this->get_documentation_pattern();
	}
	
	
	
	
	private function unset_key_recursive_by_reference( $key, &$array ){

		foreach ( $array  as  $k => &$v ){
			if ( is_array( $v )  &&  $k !== $key ){
				$this->unset_key_recursive_by_reference( $key, $v );
			}elseif ( $key === $k ){
				unset( $array[$k] );
			}
		}
		
	}
	

	
	
	
	private function get_documentation_pattern(){

		$pattern = $this->get_pattern( 'documentation.html' );


		// get all the parts to construct page:
		$keys = array(
			'main'      , 'main_end'    ,
			'side'      , 'side_end'    ,
			'metabox'   , 'metabox_end' , 'metabox-closed' ,
			'title'     , 'title_end'   ,
			'content'   , 'content_end' ,
		);

		foreach( $keys  as  $key ){
			$part = $this->get_pattern( "doc-parts/{$key}.html" );

			// construct the html tag from key:
			if ( strpos( $key, '_end' ) !== false ){
				$html_tag = '</' . str_replace( '_end', '', $key ) . '>';
			}elseif( strpos( $key, '-' ) !== false ){
				$html_tag = '<' . str_replace( '-', ' ', $key ) . '>';
			}else{
				$html_tag = '<' . $key . '>';
			}


			// replace tag with the part:
			$pattern = str_replace( $html_tag, $part, $pattern );

		}

		return $pattern;
	}
	
	
	private function get_key_val_recursive( $key, $array ){
		$res = array();
		
		// the first occurence of $key will be returned, and no other
		foreach ( $array  as  $k => $v ){
			if ( is_array( $v )  &&  $k !== $key ){
				$res = $this->get_key_val_recursive( $key, $v );
				if ( !empty( $res ) ) {
					break;
				}
			}elseif ( $key === $k ){
				$res = array( $k => $v );
				break;
			}
		}
		

		return $res;
		
	}
	
	
	private function get_options_from_files(){
		$files = scandir( $this->pattern_dir . '/options' );
		$options = array();
		foreach ( $files  as  $file ){
			$options[] = str_ireplace( '.html', '', $file );
		}
		
		return $options;
		
	}

	
	private function get_pattern( $path ){
		return file_get_contents( $this->pattern_dir . '/' . $path );
	}

	
	private function get_group_id(  ){
		$page_number = ( isset($this->page['counter'])  ?  $this->page['counter']  :  '_' );	// this should be set by now, usually
		$section_number = $this->section_counter;
		return $this->prefix . "options_group__p{$page_number}s{$section_number}";
	}
	
		
	private function get_section_id(  ){
		return $this->get_group_id() . '__section';
	}
	
		
	private function get_slug( $passed_page = array() ){
		
		if ( ! empty( $passed_page['slug'] ) ){   // if a page is passed, get that pages's slug
			return $passed_page['slug'];
		}
		
		if ( ! empty( $this->page['slug'] ) ){
			return $this->page['slug'];
		}else{  // create the slug:
			$page_number = $this->page_counter;
			return $this->prefix . $this->slugify( $this->menu_title ) . "_{$page_number}";
		}
		
	}
	
	private function slugify( $text ){  // makes phrase into slug
		return sanitize_title_with_dashes( $text, '', 'save' );
	}
	
	
	
	
	
	public function __section__callback( $section_passed ){
		$section_id = $section_passed['id'];

		$pages = &$this->config['pages'];
		foreach ( $pages  as  &$page ){
			

			$sections = $page['sections'];
			foreach ( $sections  as  &$section ){
			


				if ( $section['id'] === $section_id ){
					echo $section['description'];
					break 2;
				}
				
				
				
				
			}
		}
	}
	
	
	
	public static function render_sections( $page_slug = '', $sections = array() ) { 
		/*********   TAKE NOTICE - THIS IS A STATIC FUNCTION    ***********/
		
		$opt_count = 0;
		foreach ( $sections  as  &$section ){
			$section = (array) $section;	// solve type problem
			$opt_count += $section['opt_count'];
		}
		
		$have_options = ( $opt_count > 0 );
		
		// <form>
		if ( $have_options ){ echo '<form action="options.php" method="post">';	}
		

			 foreach ( $sections  as  &$section ){
				settings_fields( $section['group'] );
			 }
			 
			 do_settings_sections( $page_slug );
			
			 if ( $have_options ){
				submit_button();
			 }
			

		// </form>
		if ( $have_options ){ echo '</form>'; }
		

	}
	

	
}