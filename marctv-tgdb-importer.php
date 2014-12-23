<?php

/*
Plugin Name: MarcTV The GameDatabase Importer
Plugin URI: http://marctv.de/blog/marctv-wordpress-plugins/
Description:
Version:  0.2
Author:  Marc Tönsing
Author URI: marctv.de
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

require_once('classes/game-api.php');

class MarcTVTGDBImporter
{
    private $version = '0.2';
    private $include_images = 'front';
    private $pluginPrefix = 'marctv-tgdb-importer';
    private $post_defaults = '';

    public function __construct()
    {
        $this->post_defaults = array(
            'post_status' => 'publish',
            'post_type' => 'game',
            'post_author' => 1,
            'ping_status' => get_option('default_ping_status'),
            'post_parent' => 0,
            'menu_order' => 0,
            'to_ping' => '',
            'pinged' => '',
            'post_password' => '',
            'guid' => '',
            'post_content_filtered' => '',
            'post_excerpt' => ''
        );

        $this->initBackend();
    }


    public function initBackend()
    {
        add_action('admin_menu', array($this, 'tgdb_import_menu'));
    }

    public function tgdb_import_menu()
    {
        add_submenu_page('tools.php', 'TGDB Import', 'TGDB Import', 'manage_options', $this->pluginPrefix, array($this, 'tgdb_import_options'));
    }

    public function tgdb_import_options()
    {
        require_once('pages/settings.php');
    }


    private function post_exists($id)
    {
        return is_string(get_post_status($id));
    }

    private function post_exists_by_title($title_str)
    {
        global $wpdb;
        $sql_obj = $wpdb->get_row("SELECT * FROM wp_posts WHERE post_title = '" . esc_sql($title_str) . "'", 'ARRAY_A');

        $id = $sql_obj['ID'];
        if (isset ($id)) {
            return $id;
        } else {
            return false;
        }
    }

    public function createGame($id)
    {
        $gameAPI = new gameDB();
        $game = $gameAPI->getGame($id);

        /*
        echo "<pre>";
        var_dump($game->Game);
        echo "</pre>";
        */

        if(isset($game->Game->id)) {
            $game_id = $game->Game->id;
        } else {
            error_log('ID ' . $game_id . ': error: no ID');
            return false;
        }

        if (isset ($game->Game->GameTitle)) {
            $game_title = $game->Game->GameTitle;
        } else {
            error_log('ID ' . $game_id . ': error in game title');
            return false;
        }

        if(isset($game->Game->Platform)) {
            $game_platform = $game->Game->Platform;
        }

        /* check if post/game id exists */
        if ($this->post_exists($game_id)) {
            return '<p>ID ' . $game_id . ' with the title <a href="/wp-admin/post.php?post=' . $game_id . '&action=edit">' . get_the_title($game_id) . '</a> already exists! </p>';
            error_log('ID ' . $game_id . ' already exists!');

            return false;
        }

        /* check if game already exists */
        if ($double_title = $this->post_exists_by_title(($game_title))) {
            echo 'Title ' . $game_title . ' already exists! Adding platform.</p>';
            error_log('Title ' . $game_title . ' already exists! Adding platform.');
            /* add the platforms */
            $this->addTerms($double_title, $game_platform, 'platform');

            return false;
        }

        if (isset($game->Game->Overview)) {
            $overview = $game->Game->Overview;
        } else {
            $overview = '';
        }



        if (isset ($game->Game->ReleaseDate)) {
            $release_date = date("Y-m-d H:i:s", strtotime($game->Game->ReleaseDate) + 43200); // release date plus 12 hours.
        } else {
            error_log('ID ' . $game_id . ': error in releasedate');
            return false;
        }

        $post_attributes = array_merge($this->post_defaults, array(
            'post_content' => $overview,
            'post_title' => $game_title,
            'post_date' => $release_date, //[ Y-m-d H:i:s ]
            'import_id' => $game_id
        ));

        // Insert the post into the database
        if ($wp_id = wp_insert_post($post_attributes)) {
            echo '<p>Successfully created <a href="/wp-admin/post.php?post=' . $wp_id . '&action=edit">' . $game_title . '</a></p>';

            if(isset($game->Game->Developer)) {
                $this->addCustomField($wp_id, 'Developer', $game->Game->Developer);
            }

            if(isset($game->Game->Publisher)) {
                $this->addCustomField($wp_id, 'Publisher', $game->Game->Publisher);
            }

            if(isset($game->Game->ESRB)) {
                $this->addCustomField($wp_id, 'ESRB', $game->Game->ESRB);
            }

            if(isset($game->Game->Youtube)) {
                $this->addCustomField($wp_id, 'Youtube', $game->Game->Youtube);
            }

            if(isset($game->Game->{'Co-op'})) {
                $this->addCustomField($wp_id, 'Co-op', $game->Game->{'Co-op'});
            }

             if(isset($game->Game->Players)) {
                $this->addCustomField($wp_id, 'Players', $game->Game->Players);
            }

            if(isset($game->Game->Genres->genre)) {
                $this->addTerms($wp_id, $game->Game->Genres->genre, 'genre');
            }

            if(isset($game_platform)) {
                $this->addTerms($wp_id, $game_platform, 'platform');
            }


            if(isset($game->Game->Images)) {
                $image_urls = $this->getTreeLeaves($game->Game->Images);
                foreach($image_urls as $image_url){
                    $url = $game->baseImgUrl.$image_url;
                    $path = explode('/',$image_url);
                    $title = $game_title . ' - ' . $path[0];
                    /* set upload directory to the date as the release date */
                    $time = date("Y/m", strtotime($release_date));

                    $this->saveImage($wp_id, $url, $title, $this->include_images, $time);
                }
            }

            return true;
        }
    }

    public function getTreeLeaves($object)
    {
        $return = array();
        $iterator = new RecursiveArrayIterator($object);

        while ($iterator->valid()) {

            if ($iterator->hasChildren()) {
                $return = array_merge($this->getTreeLeaves($iterator->getChildren()),$return);
            } else {
                $return[] = $iterator->current();
            }

            $iterator->next();
        }
        return $return;
    }

    public function strposa($haystack, $needles=array(), $offset=0) {
        $chr = array();
        foreach($needles as $needle) {
            $res = strpos($haystack, $needle, $offset);
            if ($res !== false) $chr[$needle] = $res;
        }
        if(empty($chr)) return false;
        return min($chr);
    }


    public function saveImage($wp_id, $url, $title = '', $include_csv = '', $time = null)
    {
        if(!empty($include_csv)) {
            $include_array = explode(',', $include_csv);

            if(!$this->strposa($url,$include_array)){
                return false;
            }
        }

        $parent_post_id = $wp_id;
        $file = $url;
        $wp_filetype = wp_check_filetype(basename($file), null);
        $filename = strtolower(sanitize_file_name($title)) . '.' . $wp_filetype['ext'];

        if(!isset($title)) {
            $title = preg_replace('/\.[^.]+$/', '', $filename);
            $filename = basename($file);
        }

        $upload_file = wp_upload_bits($filename, null, file_get_contents($file), $time);

        if (!$upload_file['error']) {

            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_parent' => $parent_post_id,
                'post_title' => $title,
                'post_content' => '',
                'post_status' => 'inherit',
                'import_id' => $parent_post_id + 5000000 //all attachment posts start with this. Any better idea to avoid collisions?
            );
            $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $parent_post_id);
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                set_post_thumbnail( $parent_post_id, $attachment_id );
            }
        } else {
            return false;
        }


    }

    public function addCustomField($wp_id, $label, $value = '')
    {
        if (isset ($value)) {
            add_post_meta($wp_id, $label, $value, true) || update_post_meta($wp_id, $label, $value);
        }
    }

    public function addTerms($wp_id, $term_obj, $taxonomy_string)
    {

        if (isset($term_obj)) {
            if (count($term_obj) > 1) {
                foreach ($term_obj as $obj_string) {
                    wp_set_object_terms($wp_id, $obj_string, $taxonomy_string, true);
                }
            } else {
                $obj_string = $term_obj;
                wp_set_object_terms($wp_id, $obj_string, $taxonomy_string, true);
            }
        }
    }

    public function searchGamesByName($name)
    {
        $gameAPI = new gameDB();
        $games = $gameAPI->getGamesList($name);

        return $games;

    }

    public function getGamesByPlatform($id) {
        $gameAPI = new gameDB();
        $games = $gameAPI->getPlatformGames($id);

        return $games;
    }

    public function import($name, $limit = 0){
        //$games = $this->searchGamesByName($name, $limit);
        $games = $this->getGamesByPlatform($name); // 4919 / 15
        //var_dump($this->createGame(24451));
        /*
        echo "<pre>";
        var_dump($games);
        echo "</pre>";
        */

        if (count($games->Game) > 0) {

            $i = 0;

            foreach ($games->Game as $game) {
                $id = $game->id;
                if(!$this->createGame($id)){
                    echo '<p>Error in: ' .$id . '</p>';
                }
                if (++$i == $limit) break;
            }
        }


    }

}


/**
 * Initialize plugin.
 */
new MarcTVTGDBImporter();
