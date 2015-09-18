# Purpose
The Super Duper WordPress Settings class is intended to make it easy for me to add options pages and menus (as opposed to the WP way, which is incredibly and unnecessary difficult).

# How to use
Put these files in your theme folder somewhere, then include the class `include 'SDWPSettings.php';`.

Then instantiate it, and add pages and menus:
```
( new SDWPSettings() )

->...
->...

.....

```

#Functions

#### set_prefix( $prefix )
Sets the current prefix to work with

#### set_menu_position( $pos )
Sets the position (or priority) in the menu where we will start inserting items.

#### set_menu_icon( $icon )
Expects a URL.

#### add_menu_separator( $separator_id = false )
Adds a separator, with optional ID.

#### add_menu_page( $title )
Adds a page to the menu. If a menu was not defined by this time, a generic menu will be created

#### add_submenu_page( $title )
Adds a subpage under the current page.

#### add_node( $id, $title, $parent, $href, $group, $meta )
Adds a node to the top admin bar.

#### add_section( $title, $description = '' )
Adds a new section to the current page or subpage. If no current page, one will be created.

#### add_option(  )
Adds an option to the current section. If no section, one will be created.
It takes an array as argument:
```
array(

	'id' =>
	'title' =>
	'description' =>
	'type' => checkbox | textarea | textarea_code
	'default' => 

	'syntax' => html | javascript | css  (only if type == textarea_code) 

)
```


# Alternative functions use
This class recognizes certain keywords. As a consequence, it is possible to use:
```
->add_checkbox( $args );
->add_textarea( $args );

...

```

# Example
```
( new SDWPSettings() )
->set_prefix( 'my_wp_' )
->set_position( 70 )
->add_separator()
->add_menu_page( 'My Theme Options' )
	->add_section( 'General Options' )
		->add_option( array(
			'id' => 'checkbox_1',
			'title' => __( 'This is a checkbox', 'textdomain' )
			'type' => 'checkbox'
			'default' => 1
		) )
		->add_option( array(
			'id' => 'textarea_1',
			'title' => __( 'This is my textarea', 'textdomain' ),
			'type' => 'textarea'
		) )
	->add_section( 'Advanced Options' )
		->add_option( array(
			'id' => 'textarea_code_1',
			'title' => __( 'Enter code here', 'textdomain' ),
			'description' => __( 'Here you can enter your Google Analytics code', 'textdomain' ),
			'type' => 'textarea_code',
			'syntax' => 'javascript'
		) )


->add_submenu_page( 'This is a page under the previous one' )
 	->add_section(...)
 	..... and so on .....

```