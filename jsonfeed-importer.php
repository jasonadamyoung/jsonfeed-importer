<?php
/*
Plugin Name: JSONFeed Importer
Plugin URI: https://github.com/jasonadamyoung/jsonfeed-importer
Description: Import posts from an JSON Feed ( https://jsonfeed.org/ ). Directly based on the wordpressdotorg RSS-Importer.
Author: jasonadamyoung
Author URI: https://rambleon.org/
Version: 0.1
Stable tag: 0.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
Text Domain: jsonfeed-importer
*/

if ( !defined('WP_LOAD_IMPORTERS') )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

/**
 * JSONFeed Importer
 *
 * @package WordPress
 * @subpackage Importer
 */

/**
 * JSONFeed Importer
 *
 * Will process a JSONFeed  for importing posts into WordPress.
 *
 * @since unknown
 */
if ( class_exists( 'WP_Importer' ) ) {
class JSONFeed_Import extends WP_Importer {

	var $posts = array ();
	var $file;

	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>'.__('Import JSONFeed', 'jsonfeed-importer').'</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function greet() {
		echo '<div class="narrow">';
		echo '<p>'.__('This importer allows you to extract posts from an JSONFeed file into your WordPress site. This is useful if you want to import your posts from a system that is not handled by a custom import tool. Pick an JSONFeed file to upload and click Import.', 'jsonfeed-importer').'</p>';
		wp_import_upload_form("admin.php?import=jsonfeed&amp;step=1");
		echo '</div>';
	}

	function _normalize_tag( $matches ) {
  	return '<' . strtolower( $matches[1] );
	}

	function get_posts() {
		global $wpdb;

		$importdata = file_get_contents($this->file); // Read the file into an array
		$jsondata = json_decode($importdata);
		$this->posts = $jsondata->{'items'};
		$index = 0;
		foreach ($this->posts as $post) {
			$post_title = $post->{'title'};
			$date_published = new DateTime($post->{'date_published'});
			$date_published->setTimezone(new DateTimeZone('UTC'));
			$post_date = $date_published->format('Y-m-d H:i:s');
			$categories = $post->{'tags'};
			$guid = $post->{'id'};
			$post_content = $post->{'content_html'};
			// Clean up content
			$post_content = preg_replace_callback('|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $post_content);
			$post_content = str_replace('<br>', '<br />', $post_content);
			$post_content = str_replace('<hr>', '<hr />', $post_content);

			$post_author = 1;
			$post_status = 'publish';
			$this->posts[$index] = compact('post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_status', 'guid', 'categories');
			$index++;
		}
	}

	function import_posts() {
		echo '<ol>';

		foreach ($this->posts as $post) {
			echo "<li>".__('Importing post...', 'jsonfeed-importer');

			extract($post);

			if ($post_id = post_exists($post_title, $post_content, $post_date)) {
				_e('Post already imported', 'jsonfeed-importer');
			} else {
				$post_id = wp_insert_post($post);
				if ( is_wp_error( $post_id ) )
					return $post_id;
				if (!$post_id) {
					_e('Couldn&#8217;t get post ID', 'jsonfeed-importer');
					return;
				}

				if (0 != count($categories))
					wp_create_categories($categories, $post_id);
				_e('Done!', 'rss-importer');
			}
			echo '</li>';
		}

		echo '</ol>';

	}

	function import() {
		$file = wp_import_handle_upload();
		if ( isset($file['error']) ) {
			echo $file['error'];
			return;
		}

		$this->file = $file['file'];
		$this->get_posts();
		$result = $this->import_posts();
		if ( is_wp_error( $result ) )
			return $result;
		wp_import_cleanup($file['id']);
		do_action('import_done', 'rss');

		echo '<h3>';
		printf(__('All done. <a href="%s">Have fun!</a>', 'rss-importer'), get_option('home'));
		echo '</h3>';
	}

	function dispatch() {
		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];

		$this->header();

		switch ($step) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer('import-upload');
				$result = $this->import();
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
		}

		$this->footer();
	}

	function JSONFeed_Import() {
		// Nothing.
	}
}

$jsonfeed_import = new JSONFeed_Import();

register_importer('jsonfeed', __('JSONFeed', 'jsonfeed-importer'), __('Import posts from an JSONFeed.', 'jsonfeed-importer'), array ($jsonfeed_import, 'dispatch'));

} // class_exists( 'WP_Importer' )

function jsonfeed_importer_init() {
	// Nothing.
}
add_action( 'init', 'jsonfeed_importer_init' );
