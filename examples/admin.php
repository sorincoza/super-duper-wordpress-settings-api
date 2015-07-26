<?php

add_action( 'init', 'create_settings_pages' );

function create_settings_pages(  ){
	

	$csc = new SuperDuperWPSettings();
	$csc->set_prefix( 'convertr' )
		->set_menu_position( 51 )
		->set_menu_icon( get_template_directory_uri() . '/img/convertr-logo-for-menu.png' )
		
		->add_menu_separator()
		->add_menu_page( array('Theme Options') )
			->add_section( array('General Options', 'x') )
				->add_checkbox( 
					'cnv__use_ace_editor', 
					__( 'Use Ace Editor', 'convertr' ), 
					__( 'Check this box to use the Ace Editor on admin pages for code editing, instead of the old textarea (recommended).', 'convertr' ), 
					'checked' 
				)
				->add_textarea_code( 
					'cnv__tracking_code', 
					__( 'Tracking Code', 'convertr' ),
					__( 'Enter tracking code provided by Google, Facebook, or others. This will be placed in the <code>&lt;head&gt;</code> area of your site.', 'convertr' )
				)				
				->add_textarea_code( 
					'cnv__additional_css', 
					__( 'Additional CSS code', 'convertr' ),
					__( 'Enter additional CSS code to style your pages. This will be the last added style (i.e. will have the highest priority).', 'convertr' ),
					'',
					'css'
				)
				->add_textarea_code( array(
						 'new_option',
						'title',
						'syntax' => 'javascript'
					) )
				->add_submenu_page( array(
						'title' => 'titlu checkbox',
						// 'description' => 'descriere',
						'newid', 'new-other', 'yet-another', 'id', 'position'=>10
					) ,array(
						'new title', 'newnewid', 'new desc'
						))
					->checkbox('test',array('title'=>'subpage chackbox', 'default'=>'checked'))

	;


}




class SuperDuperWPSettings{
	
	// current (temporary) items:
	public $fn; 	// holds the anonymous functions names
	private $__call_fn, 	// holds the name of the current function passed to __call()
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
	
	
	public function __call( $fn, $args ){
		$this->__call_fn = strtolower( $fn );
		
		if(  $this->fn_contains( array( 'checkbox' ) )  ){
			$this->add_checkbox( $args );
		}elseif(  $this->fn_contains( array( 'textarea_code' ) )  ){
			$this->add_textarea_code( $args );
		
			
		}elseif(  $this->fn_contains( array( 'section' ) )  ){
			$this->add_section( $args );
		}elseif(  $this->fn_contains( array( 'page', 'menu' ) )  ){
			if(  $this->fn_contains( array( 'sub' ) )  ){
				$this->add_submenu_page( $args );
			}else{
				$this->add_menu_page( $args );
			}
		}elseif ( $this->fn_contains( 'separator' ) ){
			$this->add_menu_separator( $args );
		}
		
		return $this;
		
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
	
	
	public function __construct(){
				
		// add actions:
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );


		// initialize variables:
	    $this->prefix        = '_pre_';
	    $this->menu_title    = 'Custom Options';
	    $this->page_title    = $this->menu_title;

	    $this->menu_icon     = '';
	    $this->menu_position = 99;
	    $this->group         = $this->get_group_id();

	    $this->page_counter = 0;
	    $this->option_counter = 0;
	    $this->section_counter = 0;
	    
	    $rendering_args = array();
	    
	    $this->config = array();

		
	}
	
	
	private function get_pattern( $path ){
		return file_get_contents( __DIR__ . '/patterns/' . $path );
	}
	


	public function admin_menu(  ) { 

		$pages = &$this->config['pages'];
		
		foreach ( $pages  as  &$page ){
			
			// build lambda function:
			$_page_slug = $page['slug'];
			$_sections_list = '';
			
			foreach ( $page['sections']  as  &$section ){
				$_sections_list .=  (  '"' . $section['group'] . '" => "' . $section['description'] . '", '  ) ;
			}
			
			
			$fn_content = $this->get_pattern( 'add-menu-page-cb.php' );

			
			$fn_content = str_replace(   
				array( '{{PAGE_SLUG}}'	, '{{SECTIONS_ARRAY}}'	,  '{{FN_RENDER_SECTIONS}}' ),  
				array( $_page_slug		,  $_sections_list		,  get_class( $this ) . '::render_sections' ),  
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
		
		$args = func_get_args();
		$other_args = array( 'parent_slug' => $this->get_slug() );

		$this->init_new_menu_page( $args, $other_args );
		
		return $this;
		
	}

	
	public function add_section( $title, $description = '' ){

		$args = func_get_args();
		
		$this->init_new_section( $args );

		return $this;
		
	}

	public function add_checkbox( $id, $title = '', $description ='', $default = 0 ){

		$args = func_get_args();
		$other_args = array( 'type' => 'checkbox' );
		
		$this->init_new_option( $args, $other_args );
		
		$default = $this->option['default'];
		if ( $default === 'checked'  ||  $default === 'check' ){ $this->option['default'] = 1; }
		
		return $this;

	}
	
	public function add_textarea_code( $id, $title = '', $description = '', $default = '', $syntax = 'html' ){
		
		$args = func_get_args();
		$other_args = array( 'syntax' => $syntax, 'type' => 'textarea_code' );
		
		$this->init_new_option( $args, $other_args );


		return $this;
		
	}
	
	
	
	
	
		
	private function init_new_menu_page( $args, $other_args = array() ){
		$this->section_counter = 0; // every page starts with 0 sections

		$this->config['pages'][ $this->page_counter ] = array();		// initialize
		$this->page = &$this->config['pages'][ $this->page_counter ];  	// store the current reference
		
		$args = $this->extract_args( array( 'title' ), $args );
		
				
		// update current vars:
		$this->page_title = $args['title'];
		$this->menu_title = $args['title'];
		
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
		
		
		// init section:
		$this->section = array(
				'id' => $this->get_section_id(),
				'title' => $args['title'],
				'description' => $args['description'],
				'group' => $this->get_group_id(),
				'counter' => $this->section_counter,
				'options' => array()
			);
			
		$this->section_counter++ ;
	}
	
	
	
	private function extract_args( $keys = array(), $args = array() ){
		
		$res = array();
		$aux = array();
		
		// if we have an array in array (from passing $args twice), get the inner one:
		if (   count( $args ) === 1  &&  isset( $args[0] )  &&  is_array( $args[0] )   ){
			$args = $args[0];
		}
		

		foreach ( $args  as  $k => $arg ){
			
			if ( isset( $keys[$k] ) ){
				$passed_key = $keys[$k] ;
			}else{
				break;
			}
			

			if ( is_array( $arg ) ){
				
				$aux = $arg;
		
				foreach ( $aux  as  $key => $val ){
					
					if ( ! is_int( $key ) ) {
						$res[ $key ] = $val;
						unset( $aux[$key] );		/***** TAKE NOTICE OF THIS unset() ********/
					}
					
				}


				break;	// we found an array so we get all our options from it

			}else{	// if this is not an array, we assign the normal value:
				$res[ $passed_key ] = $arg;
			}
			
		}
		

		// we need to return something for every key that was asked:
		foreach ( $keys  as  $key ){
			if ( ! isset( $res[$key] ) ){
				
				
				// try to get leftovers from $arg:
				if ( ! empty( $aux ) ){
					foreach ( $aux  as  $i => $val ){
						$res[$key] = $val;
						unset( $aux[$i] );			/***** TAKE NOTICE OF THIS unset() ********/
						break;
					}
				}else{
					$res[$key] = '';
				}
				
				
			}
		}
		
		
		return $res;
		
		
	}
	
	private function init_new_option( $args, $other_args = array() ){
		
		if ( empty($this->page) ){
			$this->add_menu_page( $this->page_title ); 
		}
		if ( empty($this->section)  ||  empty($this->page['sections'][ $this->section['counter'] ]) ){ 
			$this->add_section( $this->page_title ); 
		}
		
		

		$this->section['options'][ $this->option_counter ] = array();			// initialize
		$this->option = &$this->section['options'][ $this->option_counter ];	// store reference


		$args = $this->extract_args( array('id', 'title', 'description', 'default' ), $args );

		$this->option = $args;

		$this->option['counter'] = $this->option_counter;
			
			
		foreach ( $other_args  as  $key => $arg ){
			$this->option[ $key ] = $arg ;
		}
		

		$this->option_counter++ ;

	}
	
	
	
	
		
	private function insert_menu_separators(  ){
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
	
	
	



	public function settings_init(  ) { 



		$pages = &$this->config['pages'];
		foreach ( $pages  as  &$page ){

			
			
			$sections = $page['sections'];
			foreach ( $sections  as  &$section ){
				
				add_settings_section(
					$section['id'],
					$section['title'],
					array( $this, '__section__callback' ),
					$page['id']
				);
				
				
				
				$options = $section['options'];
				foreach ( $options  as  &$option ){
					
					register_setting( $section['group'], $option['id'] );
					
					// decide what function is appropriate for rendering HTML code:
					switch ( $option['type'] ){
						case 'checkbox':
							$rendering_function = array( $this, 'checkbox_field_render' );
							break;
							
						case 'textarea_code':
							$rendering_function = array( $this, 'textarea_code_field_render' );
							break;
							
						default:
							$rendering_function = array( $this, '__section__callback' );
							break;
					}
					
					add_settings_field(
						$option['id'],
						$option['title'],
						$rendering_function,
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
	
	
	
	
	

	
	public function checkbox_field_render( $args ) { 
		
		$this->set_rendering_args( $args );

		?>
			<input type='checkbox' name='<?php echo $this->rendering_args['option_name']; ?>' <?php checked( get_option( $this->rendering_args['option_name'], $this->rendering_args['default_value'] ), 1 ); ?> value='1'>
			<p class="option_description"><?php echo $this->rendering_args['option_description']; ?></p>
		<?php
	}
	
	public function textarea_field_render( $args ) { 
		
		$this->set_rendering_args( $args );

		?>
			<textarea cols='40' rows='5' name='<?php echo $this->rendering_args['option_name']; ?>'><?php echo get_option( $this->rendering_args['option_name'], $this->rendering_args['default_value'] ); ?></textarea>
			<p class="option_description"><?php echo $this->rendering_args['option_description']; ?></p>
		<?php
	}
		
	public function textarea_code_field_render( $args, $syntax = 'html' ) { 

		$this->set_rendering_args( $args );
		
		if ( isset( $args['option']['syntax'] ) ){ $syntax = $args['option']['syntax']; }

		?>
		 <div class='convertr-option-textarea-code'>
		   <pre>
			<textarea data-ace_mode='<?php echo $syntax; ?>' cols='40' rows='5' name='<?php echo $this->rendering_args['option_name']; ?>'><?php echo get_option( $this->rendering_args['option_name'], $this->rendering_args['default_value'] ); ?></textarea>
		   </pre>
		 </div>
		 <p class="option_description"><?php echo $this->rendering_args['option_description']; ?></p>
		 <?php
	}
	
	
	private function set_rendering_args( $args, $default_default = '' ){
		
		$this->rendering_args['option_name'] = $args['id'];
		$this->rendering_args['option_description'] = $args['description'];
		$this->rendering_args['default_value'] = isset( $args['default'] )  ?  $args['default']  :  $default_default;

	}



	
	
	
	private function get_group_id(  ){
		$page_number = ( isset($this->page['counter'])  ?  $this->page['counter']  :  '_' );	// this should be set by now, usually
		$section_number = $this->section_counter;
		return $this->prefix . "options_group__p{$page_number}s{$section_number}";
	}
	
		
	private function get_section_id(  ){
		return $this->get_group_id() . '__section';
	}
	
		
	private function get_slug(  ){
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
		?>
		<form action='options.php' method='post'>
			
			<?php
			foreach ( $sections  as  $option_group => $description ){
				settings_fields( $option_group );
			}
			do_settings_sections( $page_slug );
			submit_button();
			?>
			
		</form>
		<?php

	}
	
	
}