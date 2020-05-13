UserBlog plugin 2.1.0


This plugin creates a new blog when user clicks a link in their profile page. Blog short name and category will have the same name as login.

The plugin has several options built in:
You can choose if you want to use stub files or not, with or without extension, if you want to store stub files in separate directories or all in a subdirectory.

You can also decide if you want to give users administration rights and own media folder on registration or not, and set the default descriptions and default skin for the new user's blogs.


INSTALLATION:
	
	Install this plugin like all others, if you also want to manage user blogs from Users tab (Admin), add the following in /inc/users/views/_user_list.view.php on line 182
	

	// ===================================================================
	// Include Userblog users hack
	global $plugins_path;
	if ( is_file($plugins_path.'userblog_plugin/_users_list.inc.php') )
	{
		include $plugins_path.'userblog_plugin/_users_list.inc.php';
	}
	// ===================================================================
	

	Alternatively you can use prehacked file for b2evo-2.4.2 from installation folder
	