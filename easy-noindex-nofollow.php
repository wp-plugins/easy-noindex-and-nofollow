<?php
/**
 * Plugin Name: Easy Noindex And Nofollow
 * Short Name: easy_noindex_nofollow
 * Description: Easily add Noindex and Nofollow to post, page, search and category page.
 * Author: Ivan Kristianto
 * Version: 1.2
 * Requires at least: 2.7
 * Tested up to: 3.1.2
 * Tags: noindex, nofollow, seo, google panda
 * Contributors: Ivan Kristianto
 * WordPress URI: http://wordpress.org/extend/plugins/easy-noindex-and-nofollow/
 * Author URI: http://www.ivankristianto.com/
 * Donate URI: http://www.ivankristianto.com/portfolio/
 * Plugin URI: http://www.ivankristianto.com/web-development/programming/easy-noindex-and-nofollow-wordpress-plugin/1797/
 *
 *
 * easy-noindex-and-nofollow - Easy Noindex And Nofollow
 * Copyright (C) 2011	IvanKristianto.com
 *
 * This program is free software - you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.	If not, see <http://www.gnu.org/licenses/>.
 */

// exit if add_action or plugins_url functions do not exist
if (!function_exists('add_action') || !function_exists('plugins_url')) exit;

// function to replace wp_die if it doesn't exist
if (!function_exists('wp_die')) : function wp_die ($message = 'wp_die') { die($message); } endif;

// define some definitions if they already are not
!defined('WP_CONTENT_DIR') && define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
!defined('WP_PLUGIN_DIR') && define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
!defined('WP_CONTENT_URL') && define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
!defined('WP_PLUGIN_URL') && define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');

// don't load directly
!defined('ABSPATH') && exit;


/**
 * easy_noindex_nofollow
 * 
 * @package   
 * @author Ivan Kristianto
 * @version 2011
 * @access public
 */
class easy_noindex_nofollow{
	var $options = array();	// an array of options and values
	var $plugin = array();	// array to hold plugin information
	
	/**
	 * Defined blank for loading optimization
	 */
	function easy_noindex_nofollow() {}
	
	/**
	 * Loads options named by opts array into correspondingly named class vars
	 */
	function LoadOptions($opts=array('options', 'plugin')){
		foreach ($opts as $pn) $this->{$pn} = get_option("easy_noindex_nofollow_{$pn}");
	}
	
	/**
	 * Saves options from class vars passed in by opts array and the adsense key and api key
	 */
	function SaveOptions($opts=array('options','code','plugin'))	{
		foreach ($opts as $pn) update_option("easy_noindex_nofollow_{$pn}", $this->{$pn});
	}
	
	/**
	 * Gets and sets the default values for the plugin options, then saves them
	 */
	function default_options()	{
		
		// get all the plugin array data
		$this->plugin = $this->get_plugin_data();	
		
		// default options
		$this->options = array(
			'enabled' 			=> '1',	// WP Search is on by default
			'search_noindex' 	=> '0',	// Set noindex in search page
			'search_nofollow' 	=> '0',	// Set nofollow in search page
			'category_nofollow' => '0',	// Set noindex in category page
			'category_noindex' 	=> '0',	// Set nofollow in category page
		);
		
		// Save all these variables to database
		$this->SaveOptions();
	}
	
	function init() {
		$this->LoadOptions();
		
		add_action('wp_head', array(&$this, 'easy_noindex_nofollow_add_header'));
		
		add_action( 'admin_init', array(&$this, 'easy_noindex_nofollow_add_meta_box'));
		add_action( 'save_post', array(&$this,'easy_noindex_nofollow_meta_box_save') );
	
		add_action("load-{$this->plugin['hook']}", array(&$this, 'load'));
		add_action('admin_print_scripts', array(&$this,'config_page_scripts'));
		add_action('admin_print_styles', array(&$this,'config_page_styles'));	
		add_action("admin_footer-{$this->plugin['hook']}", create_function('', 'echo "<script src=\"'.plugins_url('/static/admin.js',__FILE__).'\" type=\"text/javascript\"></script>";'));
	}
	
	/**
	 * Enqueue javascript in Admin page.
	 * Enqueue required javascript.
	 * Run in admin_print_scripts hook
	 */
	function config_page_scripts() {
		if (isset($_GET['page']) && $_GET['page'] == $this->plugin['page']) {
			wp_enqueue_script('postbox');
			wp_enqueue_script('dashboard');
			wp_enqueue_script('thickbox');
			wp_enqueue_script('media-upload');
		}
	}
	
	/**
	 * Enqueue css styles in Admin page.
	 * Enqueue required css styles.
	 * Run in admin_print_styles hook
	 */
	function config_page_styles() {
		if (isset($_GET['page']) && $_GET['page'] == $this->plugin['page']) {
			
			wp_enqueue_style('dashboard');
			wp_enqueue_style('thickbox');
			wp_enqueue_style('global');
			wp_enqueue_style('wp-admin');
			wp_enqueue_style($this->plugin['pagenice'], plugins_url('/easy-noindex-nofollow.css',__FILE__));
		}
	}
	
	/**
	 * Run in every admin page load.
	 * Handle Post Request and update the wp_option.
	 * Run in load_ hook
	 */
	function load()
	{
		// parse and handle post requests to plugin
		if('POST' == $_SERVER['REQUEST_METHOD']) $this->handle_post();
  	}
	
	/**
	 * this plugin has to protect the code as it is displayed live on error pages, a prime target for malicious crackers and spammers
	 * and update the wp_options value
	 * @return
	 */
	function handle_post()
	{
		// if current user does not have administrator rights, then DIE
		if(!current_user_can('administrator')) wp_die('<strong>ERROR</strong>: Not an Admin!');
		
		// verify nonce, if not verified, then DIE
		if(isset($_POST["_{$this->plugin['nonce']}"])) wp_verify_nonce($_POST["_{$this->plugin['nonce']}"], $this->plugin['nonce']) || wp_die('<strong>ERROR</strong>: Incorrect Form Submission, please try again.');
		elseif(isset($_POST["reset"])) wp_verify_nonce($_POST["reset"], 'reset_nonce') || wp_die('<strong>ERROR</strong>: Incorrect Form Submission, please try again.');
		
		// resets options to default values
		if(isset($_POST["reset"])) return $this->default_options();
		
		// load up the current options from the database
		$this->LoadOptions();		
		
		//Process Checkbox
		foreach (array('search_noindex', 'search_nofollow', 'enabled', 'category_nofollow', 'category_noindex') as $k)$this->options[$k] = ((!isset($_POST["{$k}"])) ? '0' : '1');

		// Save code and options arrays to database
		$this->SaveOptions();
	}
	
	/**
	 * Create a Checkbox input field
	 */
	function checkbox($id) {
		$options = $this->options[$id];
		return '<input type="checkbox" id="'.$id.'" name="'.$id.'"'. checked($options,true,false).'/>';
	}
	
	/**
	 * Create a save button
	 */
	function save_button() {
		return '<div class="alignright"><input type="submit" class="button-primary" name="submit" value="Update WP Search Setting &raquo;" /></div><br class="clear"/>';
	}
	
	/**
	 */
	function options_page()	{
		if(!current_user_can('administrator')) wp_die('<strong>ERROR</strong>: Not an Admin!');
echo <<<JS
<script type="text/javascript">
jQuery(document).ready(function($) {
	$(".fade").fadeIn(1000).fadeTo(1000, 1).fadeOut(1000);
});
</script>

JS;
	?>
		<div class="wrap">
		<a href="http://www.ivankristianto.com/">
			<div id="easy-noindex-nofollow-icon" style="background: url(<?php echo plugin_dir_url(__FILE__) ?>easy-noindex-nofollow-icon.png) no-repeat;" class="icon32"><br /></div>
		</a>
		<h2><?php echo $this->plugin['plugin-name']; ?></h2>
		<?php if(isset($_POST['_wpnonce'])) {
			echo '<div class="updated fade" id="message"><p>'.__('Configuration', 'easy_noindex_nofollow').' <strong>'.__('SAVED', 'easy_noindex_nofollow').'</strong></p></div>';
		} ?>
		<div class="postbox-container" style="width:72%;">
			<div class="metabox-holder">	
				<div class="meta-box-sortables">
					<form action="<?php echo admin_url($this->plugin['action']); ?>" method="post" id="form">
						<?php
							wp_nonce_field($this->plugin['nonce']);
							$rows = array();
							$pre_content = '<p>Easy Noindex And Nofollow: A WordPress plugin that help you to easily add Noindex and Nofollow to post, page, search and category page..</p>';
							$content = '';
							
							$rows[] = array(
								'id' => 'enabled',
								'label' => 'Enable/Disable plugin',
								'desc' => 'Enable or disable this plugin',
								'content' =>  $this->checkbox('enabled'),
							);
							
							$rows[] = array(
								'id' => 'search_noindex',
								'label' => 'Add noindex in Search Page',
								'desc' => 'This option will add noindex in Search Page',
								'content' =>  $this->checkbox('search_noindex'),
							);
							
							$rows[] = array(
								'id' => 'search_nofollow',
								'label' => 'Add nofollow in Search Page',
								'desc' => 'This option will add nofollow in Search Page',
								'content' =>  $this->checkbox('search_nofollow'),
							);
							
							$rows[] = array(
								'id' => 'category_noindex',
								'label' => 'Add noindex in Category Page',
								'desc' => 'This option will add noindex in category Page',
								'content' =>  $this->checkbox('category_noindex'),
							);
							
							$rows[] = array(
								'id' => 'category_nofollow',
								'label' => 'Add nofollow in Category Page',
								'desc' => 'This option will add nofollow in category Page',
								'content' =>  $this->checkbox('category_nofollow'),
							);
							
							$this->postbox('generalsettings','General Settings',$pre_content.$this->form_table($rows).$this->save_button());
						?>
					</form>
		
					<form action="<?php echo admin_url($this->plugin['action']); ?>" method="post" onsubmit="javascript:return(confirm('Do you really want to reset all settings?'))">
					<?php wp_nonce_field('reset_nonce'); ?>
						<input type="hidden" name="reset" value="true"/>
						<div class="submit"><input type="submit" value="Reset All Settings &raquo;" /></div>
					</form>
				</div>
			</div>
		</div>
		
		<div class="postbox-container side" style="width:25%;">
			<div class="metabox-holder">	
				<div class="meta-box-sortables">
					<?php
						$this->plugin_like();
						$this->postbox('donate','<strong class="red">Donate $10, $20 or $50!</strong>','<p>This plugin has cost me countless hours of work, if you use it, please donate a token of your appreciation!</p><br/><form style="margin-left:50px;" action="https://www.paypal.com/cgi-bin/webscr" method="post">
						<input type="hidden" name="cmd" value="_s-xclick">
						<input type="hidden" name="hosted_button_id" value="G463UW5KA8EZ6">
						<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
						<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
						</form>');
						$this->plugin_support();
						$this->news(); 
					?>
				</div>
				<br/><br/><br/>
			</div>
		</div>
		<div class="clear"></div>
		</div>
		
		
<?php
	}
	
	/**
	 * A souped-up function that reads the plugin file __FILE__ and based on the plugin data (commented at very top of file) creates an array of vars
	 *
	 * @return array
	 */
	function get_plugin_data()
	{
		$data = $this->_readfile(__FILE__, 1500);
		$mtx = $plugin = array();
		preg_match_all('/[^a-z0-9]+((?:[a-z0-9]{2,25})(?:\ ?[a-z0-9]{2,25})?(?:\ ?[a-z0-9]{2,25})?)\:[\s\t]*(.+)/i', $data, $mtx, PREG_SET_ORDER);
		foreach ($mtx as $m) $plugin[trim(str_replace(' ', '-', strtolower($m[1])))] = str_replace(array("\r", "\n", "\t"), '', trim($m[2]));

		$plugin['title'] = '<a href="' . $plugin['plugin-uri'] . '" title="' . __('Visit plugin homepage') . '">' . $plugin['plugin-name'] . '</a>';
		$plugin['author'] = '<a href="' . $plugin['author-uri'] . '" title="' . __('Visit author homepage') . '">' . $plugin['author'] . '</a>';
		$plugin['pb'] = preg_replace('|^' . preg_quote(WP_PLUGIN_DIR, '|') . '/|', '', __FILE__);
		$plugin['page'] = basename(__FILE__);
		$plugin['pagenice'] = str_replace('.php', '', $plugin['page']);
		$plugin['nonce'] = 'form_' . $plugin['pagenice'];
		$plugin['hook'] = 'settings_page_' . $plugin['pagenice'];
		$plugin['action'] = 'options-general.php?page=' . $plugin['page'];

		if (preg_match_all('#(?:([^\W_]{1})(?:[^\W_]*?\W+)?)?#i', $plugin['pagenice'] . '.' . $plugin['version'], $m, PREG_SET_ORDER))$plugin['op'] = '';
		foreach($m as $k) sizeof($k == 2) && $plugin['op'] .= $k[1];
		$plugin['op'] = substr($plugin['op'], 0, 3) . '_';

		return $plugin;
	}

	function easy_noindex_nofollow_add_header(){
		global $wp_query;
		if(is_single() || is_page()){
			$post = $wp_query->get_queried_object();
			$easy_noindex_nofollow_index = get_post_meta( $post->ID, 'easy_noindex_nofollow_index', true );
			$easy_noindex_nofollow_follow = get_post_meta( $post->ID, 'easy_noindex_nofollow_follow', true );
			if(!empty( $easy_noindex_nofollow_index ) || !empty( $easy_noindex_nofollow_follow )){
				
				if ( !empty( $easy_noindex_nofollow_index ) && $easy_noindex_nofollow_index == "1" ){
					$noindex = "noindex";
				}
				else{
					$noindex = "index";
				}
				
				if ( !empty( $easy_noindex_nofollow_follow ) && $easy_noindex_nofollow_follow == "1" ){
					$nofollow = "nofollow";
				}
				else{
					$nofollow = "follow";
				}
				
				echo sprintf("<!--Add by easy-noindex-nofollow--><meta name=\"robots\" content=\"%s, %s\"/>\n", $noindex, $nofollow);
			}
		}
		
		if(is_search()){
			if ( !empty( $this->options['search_noindex'] ) && $this->options['search_noindex'] == "1" ){
					$noindex = "noindex";
				}
				else{
					$noindex = "index";
				}
				
				if ( !empty( $this->options['search_nofollow'] ) && $this->options['search_nofollow'] == "1" ){
					$nofollow = "nofollow";
				}
				else{
					$nofollow = "follow";
				}
				
				echo sprintf("<!--Add by easy-noindex-nofollow--><meta name=\"robots\" content=\"%s, %s\"/>\n", $noindex, $nofollow);
		}
		
		if(is_category()){
			if ( !empty( $this->options['category_noindex'] ) && $this->options['category_noindex'] == "1" ){
					$noindex = "noindex";
				}
				else{
					$noindex = "index";
				}
				
				if ( !empty( $this->options['category_nofollow'] ) && $this->options['category_nofollow'] == "1" ){
					$nofollow = "nofollow";
				}
				else{
					$nofollow = "follow";
				}
				
				echo sprintf("<!--Add by easy-noindex-nofollow--><meta name=\"robots\" content=\"%s, %s\"/>\n", $noindex, $nofollow);
		}
	}

	function easy_noindex_nofollow_add_meta_box() {
		add_meta_box( 'easy_noindex_nofollow_meta', __( 'Easy Noindex Nofollow', 'easy_noindex_nofollow' ), array($this,'easy_noindex_nofollow_meta_box_content'), 'page', 'advanced', 'high' );
		add_meta_box( 'easy_noindex_nofollow_meta', __( 'Easy Noindex Nofollow', 'easy_noindex_nofollow' ), array($this,'easy_noindex_nofollow_meta_box_content'), 'post', 'advanced', 'high' );
	}

	function easy_noindex_nofollow_meta_box_content( $post ) {
		$easy_noindex_nofollow_index = get_post_meta( $post->ID, 'easy_noindex_nofollow_index', true );
		$easy_noindex_nofollow_follow = get_post_meta( $post->ID, 'easy_noindex_nofollow_follow', true );

		if ( !empty( $easy_noindex_nofollow_index ) && $easy_noindex_nofollow_index == "1" )
			$easy_noindex_nofollow_index = ' checked="checked"';
		else
			$easy_noindex_nofollow_index = '';
		
		if ( !empty( $easy_noindex_nofollow_follow ) && $easy_noindex_nofollow_follow == "1" )
			$easy_noindex_nofollow_follow = ' checked="checked"';
		else
			$easy_noindex_nofollow_follow = '';

		echo '<p><label for="easy_noindex_nofollow_index"><input name="easy_noindex_nofollow_index" id="easy_noindex_nofollow_index"' . $easy_noindex_nofollow_index . ' type="checkbox"> ' . __( 'Add noindex.', 'easy_noindex_nofollow' ) . '</label></p>';
		
		echo '<p><label for="easy_noindex_nofollow_follow"><input name="easy_noindex_nofollow_follow" id="easy_noindex_nofollow_follow"' . $easy_noindex_nofollow_follow . ' type="checkbox"> ' . __( 'Add nofollow.', 'easy_noindex_nofollow' ) . '</label></p>';
	}

	function easy_noindex_nofollow_meta_box_save( $post_id ) {
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return $post_id;
		// Record easy_noindex_nofollow disable
		if ( 'post' == $_POST['post_type'] || 'page' == $_POST['post_type'] ) {
			if ( current_user_can( 'edit_post', $post_id ) ) {
				if ( isset( $_POST['easy_noindex_nofollow_index'] ) )
					update_post_meta( $post_id, 'easy_noindex_nofollow_index', 1 );
				else
					update_post_meta( $post_id, 'easy_noindex_nofollow_index', 0 );
				
				if ( isset( $_POST['easy_noindex_nofollow_follow'] ) )
					update_post_meta( $post_id, 'easy_noindex_nofollow_follow', 1 );
				else
					update_post_meta( $post_id, 'easy_noindex_nofollow_follow', 0 );
			}
		}

	  return $post_id;
	}
	
	/**
	 * Reads a file with fopen and fread for a binary-safe read.  $f is the file and $b is how many bytes to return, useful when you dont want to read the whole file (saving mem)
	 *
	 * @return string - the content of the file or fread return
	 */
	function _readfile($f, $b = false)
	{
		$fp = NULL;
		$d = '';
		!$b && $b = @filesize($f);
		if (!($b > 0) || !file_exists($f) || !false === ($fp = @fopen($f, 'r')) || !is_resource($fp)) return false;
		if ($b > 4096) while (!feof($fp) && strlen($d) < $b)$d .= @fread($fp, 4096);
		else $d = @fread($fp, $b);
		@fclose($fp);
		return $d;
	}
	
	/**
	 * Create a potbox widget
	 */
	function postbox($id, $title, $content) {
		echo <<<end
		<div id="{$id}" class="postbox">
			<div class="handlediv" title="Click to toggle"><br /></div>
			<h3 class="hndle"><span>{$title}</span></h3>
			<div class="inside">
				{$content}
			</div>
		</div>
end;
	}	
	
	/**
	 * Create a form table from an array of rows
	 */
	function form_table($rows) {
		$content = '<table class="form-table">';
		$i = 1;
		foreach ($rows as $row) {
			$class = '';
			if ($i > 1) {
				$class .= 'bws_row';
			}
			if ($i % 2 == 0) {
				$class .= ' even';
			}
			$content .= '<tr id="'.$row['id'].'_row" class="'.$class.'"><th valign="top" scrope="row">';
			if (isset($row['id']) && $row['id'] != '')
				$content .= '<label for="'.$row['id'].'">'.$row['label'].':</label>';
			else
				$content .= $row['label'];
			$content .= '</th><td valign="top">';
			$content .= $row['content'];
			$content .= '</td></tr>'; 
			if ( isset($row['desc']) && !empty($row['desc']) ) {
				$content .= '<tr class="'.$class.'"><td colspan="2" class="bws_desc"><small>'.$row['desc'].'</small></td></tr>';
			}
				
			$i++;
		}
		$content .= '</table>';
		return $content;
	}
	
	/**
	 * Create a "plugin like" box.
	 */
	function plugin_like() {
		$content = '<p>'.__('Why not do any or all of the following:','ivanplugin').'</p>';
		$content .= '<ul>';
		$content .= '<li><a href="'.$this->plugin['plugin-uri'].'">'.__('Link to it so other folks can find out about it.','ivanplugin').'</a></li>';
		$content .= '<li><a href="http://wordpress.org/extend/plugins/easy-noindex-and-nofollow/">'.__('Let other people know that it works with your WordPress setup.','ivanplugin').'</a></li>';
		$content .= '<li><a href="http://www.ivankristianto.com/internet/blogging/guide-to-improve-your-wordpress-blog-performance-for-free/1471/">'.__('Guide To Improve Your WordPress Blog Performance For Free.','ivanplugin').'</a></li>';
		$content .= '</ul>';
		$this->postbox($hook.'like', 'Like this plugin?', $content);
	}

	/**
	 * Info box with link to the bug tracker.
	 */
	function plugin_support() {
		$content = '<p>If you\'ve found a bug in this plugin, please submit it in the <a href="http://www.ivankristianto.com/about/">IvanKristianto.com Contact Form</a> with a clear description.</p>';
		$this->postbox($this->plugin['pagenice'].'support', __('Found a bug?','ystplugin'), $content);
	}

	/**
	 * Box with latest news from IvanKristianto.com
	 */
	function news() {
		include_once(ABSPATH . WPINC . '/feed.php');
		$rss = fetch_feed('http://www.ivankristianto.com/wordpress-series/feed/rss');
		$rss_items = $rss->get_items( 0, $rss->get_item_quantity(5) );
		$content = '<ul>';
		if ( !$rss_items ) {
			$content .= '<li class="ivankristianto">no news items, feed might be broken...</li>';
		} else {
			foreach ( $rss_items as $item ) {
				$content .= '<li class="ivankristianto">';
				$content .= '<a class="rsswidget" href="'.esc_url( $item->get_permalink(), $protocolls=null, 'display' ).'">'. htmlentities($item->get_title()) .'</a> ';
				$content .= '</li>';
			}
		}						
		$content .= '<li class="rss"><a href="http://feeds2.feedburner.com/ivankristianto">Subscribe with RSS</a></li>';
		//$content .= '<li class="email"><a href="http://ivankristianto.com/email-blog-updates/">Subscribe by email</a></li>';
		$content .= '</ul>';
		$this->postbox('ivankristiantolatest', 'Latest from IvanKristianto.com', $content);
	}

	function text_limit( $text, $limit, $finish = ' [&hellip;]') {
		if( strlen( $text ) > $limit ) {
			$text = substr( $text, 0, $limit );
			$text = substr( $text, 0, - ( strlen( strrchr( $text,' ') ) ) );
			$text .= $finish;
		}
		return $text;
	}	

}

//Start the engine
$easy_noindex_nofollow = new easy_noindex_nofollow();
add_action('init', array(&$easy_noindex_nofollow, 'init'));


/**
 * 
 *
 * @return
 */
function easy_noindex_nofollow_activation_hook(){
	global $easy_noindex_nofollow;
	
	if(!is_object($easy_noindex_nofollow))$easy_noindex_nofollow=new easy_noindex_nofollow();
	$easy_noindex_nofollow->default_options();
}

if (is_admin()) :
	register_activation_hook(__FILE__, 'easy_noindex_nofollow_activation_hook');

	add_action('admin_menu',
		create_function('', 'global $easy_noindex_nofollow; if(!is_object($easy_noindex_nofollow))$easy_noindex_nofollow=new easy_noindex_nofollow(); add_options_page( "Easy Noindex", "Easy Noindex", "administrator", "easy-noindex-nofollow.php", array(&$easy_noindex_nofollow,"options_page"));'));

	add_filter('plugin_links_easy_noindex_nofollow/easy-noindex-nofollow.php',
		create_function('$l', 'return array_merge(array("<a href=\"options-general.php?page=easy-noindex-nofollow.php\">Settings</a>"), $l);'));

	add_action('deactivate_easy_noindex_nofollow/easy-noindex-nofollow.php',
		create_function('', 'foreach ( array("options", "plugin") as $pn ) delete_option("easy_noindex_nofollow_{$pn}" );'));

endif;

?>