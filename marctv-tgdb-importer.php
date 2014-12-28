<?php

/*
Plugin Name: MarcTV The GameDatabase Importer
Plugin URI: http://marctv.de/blog/marctv-wordpress-plugins/
Description:
Version:  0.4
Author:  Marc Tönsing
Author URI: marctv.de
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

require_once('classes/game-api.php');

class MarcTVTGDBImporter
{
    private $version = '0.4';
    private $pluginUrl = '';
    private $image_type = 'front';
    private $pluginPrefix = 'marctv-tgdb';
    private $post_defaults = '';
    private $game_api;

    public function __construct()
    {
        $this->post_defaults = array(
            'post_status' => 'publish',
            'post_type' => 'game',
            'post_author' => 1,
            'ping_status' => get_option('default_ping_status'),
            'post_parent' => 0,
            'menu_order' => 0,
            'post_password' => '',
            'post_content_filtered' => '',
            'post_excerpt' => ''
        );

        $this->game_api = new gameDB();

        $this->pluginUrl = plugins_url(false, __FILE__);

        $this->initBackend();
    }


    public function initBackend()
    {
        add_action('admin_menu', array($this, 'tgdb_import_menu'));
        add_action('admin_init', array($this, 'registerSettings'));

    }

    /**
     * Registers settings for plugin.
     */
    public function registerSettings()
    {
        register_setting($this->pluginPrefix . '-settings-group', $this->pluginPrefix . '-platform');
        register_setting($this->pluginPrefix . '-settings-group', $this->pluginPrefix . '-limit');
        register_setting($this->pluginPrefix . '-settings-group', $this->pluginPrefix . '-startimport');

    }

    public function tgdb_import_menu()
    {
        $hook_suffix = add_options_page('TGDB Import', 'TGDB Import', 'manage_options', $this->pluginPrefix, array($this, 'tgdb_import_options'));
        add_action('admin_head-' . $hook_suffix, array($this, 'tgdb_admin_head'));
    }

    public function tgdb_import_options()
    {
        require_once('pages/settings.php');
    }

    public function tgdb_admin_head()
    {
        //wp_enqueue_style($this->pluginPrefix . '_style', $this->pluginUrl . "/marctv-tgdb.css", '', $this->version);
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
        $game = $this->game_api->getGame($id);

        if ($post_attributes = $this->getPostAttributes($game)) {
            if ($wp_id = $this->insertGame($game, $post_attributes)) {
                return $wp_id;
            }
        }

        return false;

    }

    public function contains($str, array $arr)
    {
        foreach ($arr as $a) {
            if (strpos($a, $str) !== false) return true;
        }
        return false;
    }

    public function getPlatformTitle($platforms){
        foreach ($platforms->Platforms->Platform as $platform) {
            if ($platform->id == get_option($this->pluginPrefix . '-platform')) {
                $platform_title = $platform->name;
            }
        }

        return $platform_title;
    }

    public function getPlatforms() {
        // Get any existing copy of our transient data
        if (false === ($platforms = get_transient('marctv-tgdb-plattforms'))) {
            // It wasn't there, so regenerate the data and save the transient

            $call = wp_remote_get('http://thegamesdb.net/api/GetPlatformsList.php');
            $body = wp_remote_retrieve_body($call);

            $xmlbody = simplexml_load_string($body);
            $json = json_encode($xmlbody);
            $platforms = json_decode($json);

            set_transient('marctv-tgdb-plattforms', $platforms, 48 * HOUR_IN_SECONDS);
        }

        return $platforms;
    }

    public function getPostAttributes($game)
    {
        if (isset($game->Game->id)) {
            $game_id = $game->Game->id;
        } else {
            $this->log(0, 'error', 'no id.');
            return false;
        }

        /* check if post/game id exists */
        if ($this->post_exists($game_id)) {
            $this->log($game_id, 'error', 'already exists.');

            return false;
        }

        if (isset ($game->Game->GameTitle)) {
            $game_title = $game->Game->GameTitle;
        } else {
            $this->log($game_id, 'error', 'no title.');
            return false;
        }

        if (isset ($game->Game->ReleaseDate)) {
            $release_date = date("Y-m-d H:i:s", strtotime($game->Game->ReleaseDate) + 43200); // release date plus 12 hours.
        } else {
            $this->log($game_id, 'error', 'no release date.');

            return false;
        }

        /* check if image is present */
        if (!$this->contains($this->image_type, $this->getTreeLeaves($game->Game->Images))) {
            $this->log($game_id, 'error', 'no ' . $this->image_type . ' image.');
            return false;
        }

        if (isset($game->Game->Platform)) {
            $game_platform = $game->Game->Platform;
        } else {
            $this->log($game_id, 'error', 'no platform.');
            return false;
        }

        /* check if game already exists */
        if ($id = $this->post_exists_by_title(($game_title))) {
            $this->log($id, 'notice', $game_title . ' already exists! Adding platform.');
            $this->addTerms($id, $game_platform, 'platform');

            return false;
        }

        $post_attributes = array_merge($this->post_defaults, array(
            'post_content' => '',
            'post_title' => $game_title,
            'post_date' => $release_date, //[ Y-m-d H:i:s ]
            'import_id' => $game_id
        ));

        return $post_attributes;
    }

    private function log($id = 0, $type, $msg = '')
    {
        error_log($type . ': ' . 'id ' . $id . ' ' . $msg);

        if ($type != 'error') {
            echo '<span class="tgdb-' . $type . '">' . $type . '</span>: <a href="http://shortscore.local/wp-admin/post.php?post=' . $id . '&action=edit">id ' . $id . '</a> ' . $msg . '</br>';

        } else {
            echo '<span class="tgdb-' . $type . '">' . $type . '</span>: id ' . $id . ' ' . $msg . '</br>';

        }
    }

    public function insertGame($game, $post_attributes)
    {

        // Insert the post into the database
        if ($wp_id = wp_insert_post($post_attributes)) {

            if ($wp_id != $post_attributes['import_id']) {
                $this->log($wp_id, 'error', 'collusion');
            }

            if (isset($game->Game->Developer)) {
                $this->addCustomField($wp_id, 'Developer', $game->Game->Developer);
            }

            if (isset($game->Game->Publisher)) {
                $this->addCustomField($wp_id, 'Publisher', $game->Game->Publisher);
            }

            if (isset($game->Game->ESRB)) {
                $this->addCustomField($wp_id, 'ESRB', $game->Game->ESRB);
            }

            if (isset($game->Game->Youtube)) {
                $this->addCustomField($wp_id, 'Youtube', $game->Game->Youtube);
            }

            if (isset($game->Game->{'Co-op'})) {
                $this->addCustomField($wp_id, 'Co-op', $game->Game->{'Co-op'});
            }

            if (isset($game->Game->Players)) {
                $this->addCustomField($wp_id, 'Players', $game->Game->Players);
            }

            if (isset($game->Game->Overview)) {
                $this->addCustomField($wp_id, 'Overview', $game->Game->Overview);
            }

            if (isset($game->Game->Genres->genre)) {
                $this->addTerms($wp_id, $game->Game->Genres->genre, 'genre');
            }

            if (isset($game->Game->Platform)) {
                $this->addTerms($wp_id, $game->Game->Platform, 'platform');
            }

            if (isset($game->Game->Images)) {
                $this->savePostImage($wp_id, $game, $post_attributes['post_title'], $post_attributes['post_date']);
            }

            $this->log($wp_id, 'success', $post_attributes['post_title'] . ' has been created!');
            return $wp_id;

        } else {

            return false;

        }

    }


    private function dump($stuff)
    {
        echo '<pre>';
        var_dump($stuff);
        echo '</pre>';
    }

    public function getTreeLeaves($object)
    {
        $return = array();
        $iterator = new RecursiveArrayIterator($object);

        while ($iterator->valid()) {

            if ($iterator->hasChildren()) {
                $return = array_merge($this->getTreeLeaves($iterator->getChildren()), $return);
            } else {
                $return[] = $iterator->current();
            }

            $iterator->next();
        }
        return $return;
    }

    public function strposa($haystack, $needles = array(), $offset = 0)
    {
        $chr = array();
        foreach ($needles as $needle) {
            $res = strpos($haystack, $needle, $offset);
            if ($res !== false) $chr[$needle] = $res;
        }
        if (empty($chr)) return false;
        return min($chr);
    }

    public function savePostImage($wp_id, $game, $title, $release_date)
    {
        $image_urls = $this->getTreeLeaves($game->Game->Images);

        foreach ($image_urls as $image_url) {
            $url = $game->baseImgUrl . $image_url;
            $path = explode('/', $image_url);
            $title = $title . ' - ' . $path[0];

            /* set upload directory structure to release date */
            $time = date("Y/m", strtotime($release_date));

            if ($attachment_id = $this->saveURLtoPostThumbnail($wp_id, $url, $title, $this->image_type, $time)) {
                return $attachment_id;
            }
        }

        return false;
    }


    public function saveURLtoPostThumbnail($wp_id, $url, $title = '', $include_csv = '', $time = null)
    {
        if (!empty($include_csv)) {
            $include_array = explode(',', $include_csv);

            if (!$this->strposa($url, $include_array)) {
                return false;
            }
        }

        $parent_post_id = $wp_id;
        $file = $url;
        $wp_filetype = wp_check_filetype(basename($file), null);
        $filename = strtolower(sanitize_file_name($title)) . '.' . $wp_filetype['ext'];

        if (!isset($title)) {
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
                set_post_thumbnail($parent_post_id, $attachment_id);
            }
            return $attachment_id;

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
        $games = $this->game_api->getGamesList($name);

        return $games;

    }

    public function getGamesByPlatform($id)
    {

        $games = $this->game_api->getPlatformGames($id);

        return $games;
    }

    public function import($id, $limit = 0)
    {
        $games = $this->getGamesByPlatform($id);
        if (count($games->Game) > 0) {
            $i = 0;
            foreach ($games->Game as $game) {
                $id = $game->id;
                $this->createGame($id);
                flush();
                if (++$i == $limit) break;
            }
        }
    }
}


/**
 * Initialize plugin.
 */
new MarcTVTGDBImporter();
