<?php

/*
Plugin Name: MarcTV The GameDatabase Importer
Plugin URI: http://marctv.de/blog/marctv-wordpress-plugins/
Description:
Version:  0.7
Author:  Marc Tönsing
Author URI: marctv.de
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

require_once('classes/game-api.php');

class MarcTVTGDBImporter
{
    private $pluginUrl = '';
    private $updatedSeconds = 86400;
    private $supported_platforms = array(
        'PC',
        'Microsoft Xbox One',
        'Microsoft Xbox 360',
        'Microsoft Xbox',
        'Sony Playstation 4',
        'Sony Playstation 3',
        'Sony Playstation 2',
        'Sony Playstation',
        'Sony Playstation Vita',
        'Sony PSP',
        'Sega Dreamcast',
        'Sega Master System',
        'Sega Genesis',
        'NeoGeo',
        'Nintendo Game Boy',
        'Nintendo Game Boy Advance',
        'Nintendo Game Boy Color',
        'Nintendo DS',
        'Nintendo 3DS',
        'Super Nintendo (SNES)',
        'Nintendo 64',
        'Nintendo Wii',
        'Nintendo Wii U'
    );
    private $image_type = 'front';
    private $pluginPrefix = 'marctv-tgdb';
    private $logfile = 'tgdbimport.log'; // should be writable in wp-content
    private $post_defaults = '';
    private $post_type = 'game';
    private $game_api;
    private $instance;

    public function __construct()
    {
        $this->pluginUrl = plugins_url(false, __FILE__);

        $this->post_defaults = array(
            'post_status' => 'publish',
            'post_type' => $this->post_type,
            'post_author' => 1,
            'ping_status' => get_option('default_ping_status'),
            'post_parent' => 0,
            'menu_order' => 0,
            'post_password' => '',
            'post_content_filtered' => '',
            'post_excerpt' => ''
        );

        $this->game_api = new gameDB();

        $this->initDataStructures();

        $this->initBackend();

        $this->addCron();
    }

    /**
     * Aktion bei der Aktivierung des Plugins
     *
     * @since   0.1
     * @change  2.4
     */

    public static function activated()
    {
        $class = new MarcTVTGDBImporter();
        $class->log('activated');
    }

    public static function deactivated()
    {
        $class = new MarcTVTGDBImporter();
        $class->log('dactivated');
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
            self::load_files();
        }
        return self::$instance;
    }

    public function initDataStructures()
    {
        // add_filter( 'pre_get_posts', array($this, 'my_get_posts' ));
        add_action('init', array($this, 'create_post_type_game'));
        add_action('init', array($this, 'create_platform_taxonomy'));
        add_action('init', array($this, 'create_genre_taxonomy'));
        add_action('init', array($this, 'create_developer_taxonomy'));
        add_action('init', array($this, 'create_publisher_taxonomy'));
    }

    public function create_post_type_game()
    {
        register_post_type('game',
            array(
                'labels' => array(
                    'name' => __('Games'),
                    'singular_name' => __('Game')
                ),
                'public' => true,
                'taxonomies' => array(),
                'has_archive' => true,
                'yarpp_support' => true,
                'supports' => array('title', 'editor', 'thumbnail', 'comments', 'custom-fields', 'post-formats')
            )
        );
    }


    public function create_genre_taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'genre',
            $this->post_type,
            array(
                'label' => __('Genre'),
                'rewrite' => array(
                    'slug' => 'genre'
                ),
            )
        );
    }

    public function create_publisher_taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'publisher',
            $this->post_type,
            array(
                'label' => __('Publisher'),
                'rewrite' => array(
                    'slug' => 'publisher'
                ),
            )
        );
    }

    public function create_developer_taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'developer',
            $this->post_type,
            array(
                'label' => __('Developer'),
                'rewrite' => array(
                    'slug' => 'developer'
                ),
            )
        );
    }

    public function create_platform_taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'platform',
            $this->post_type,
            array(
                'label' => __('Platform'),
                'rewrite' => array(
                    'slug' => 'platform',
                    'hierarchical' => true
                ),

            )
        );
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
        //add_action('admin_head-' . $hook_suffix, array($this, 'tgdb_admin_head'));
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
        $sql_obj = $wpdb->get_row("SELECT * FROM wp_posts WHERE post_title = '" . esc_sql($title_str) . "' AND wp_posts.post_type = 'game'", 'ARRAY_A');

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

    public function getPlatformTitle($platforms)
    {
        foreach ($platforms->Platforms->Platform as $platform) {
            if ($platform->id == get_option($this->pluginPrefix . '-platform')) {
                $platform_title = $platform->name;
            }
        }

        return $platform_title;
    }

    public function getPlatforms()
    {
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

    private function writeLog($msg)
    {
        $upload_dir = wp_upload_dir();

        $file = $upload_dir['basedir'] . '/' . $this->logfile;

        file_put_contents($file, strip_tags($msg) . PHP_EOL, FILE_APPEND);
    }


    public function getPostAttributes($game)
    {
        if (isset($game->Game->id)) {
            $game_id = $game->Game->id;
        } else {
            $this->log('no id.','error');
            return false;
        }

        if (isset ($game->Game->GameTitle)) {
            $game_title = $game->Game->GameTitle;
        } else {
            $this->log('no title.','error' ,0 , $game_id );
            return false;
        }

        if (isset ($game->Game->ReleaseDate)) {
            $release_date = date("Y-m-d H:i:s", strtotime($game->Game->ReleaseDate) + 43200); // release date plus 12 hours.
        } else {
            $this->log($game_title . ' has no release date.','error', 0, $game_id);

            return false;
        }

        $args = array(
            'meta_key' => 'tgdb_id',
            'meta_value' => $game_id,
            'post_type' => 'game',
        );
        $query = new WP_Query($args);

        if (isset($query->posts[0]->ID)) {
            $wpid = $query->posts[0]->ID;
        }

        if ($query->have_posts()) {
            $this->log($game_title . ' already exists.','notice' , $wpid, $game_id );
            return false;
        }

        /* check if image is present */
        if (!$this->contains($this->image_type, $this->getTreeLeaves($game->Game->Images))) {
            $this->log('no ' . $this->image_type . ' image.', 'error', 0, $game_id);
            return false;
        }

        if (isset($game->Game->Platform)) {
            $game_platform = $game->Game->Platform;
        } else {
            $this->log($game_title . ' no platform.','error', 0, $game_id);
            return false;
        }


        if (!in_array($game_platform, $this->supported_platforms)) {
            $this->log($game_title . ': Platform ' . $game_platform . ' not supported.','error',0, $game_id);
            return false;
        }


        /* check if game already exists */
        if ($wpid = $this->post_exists_by_title(($game_title))) {
            $this->log($game_title . ' already exists! Adding platform.','notice', $wpid, $game_id);
            $this->addCustomField($wpid, 'tgdb_id', $game_id);
            $this->addTerms($wpid, $game_platform, 'platform');

            if (isset($game->Game->Developer)) {
                $this->addTerms($wpid, $game->Game->Developer, 'developer');
            }

            if (isset($game->Game->Publisher)) {
                $this->addTerms($wpid, $game->Game->Publisher, 'publisher');
            }

            if (isset($game->Game->Genres->genre)) {
                $this->addTerms($wpid, $game->Game->Genres->genre, 'genre');
            }

            return false;
        }

        $post_attributes = array_merge($this->post_defaults, array(
            'post_content' => '',
            'post_title' => $game_title,
            'post_date' => $release_date, //[ Y-m-d H:i:s ]
        ));

        return $post_attributes;
    }

    public function log($msg = '',  $type = 'notice', $wpid = 0, $tgdbid = 0 )
    {
        $timestamp = ' @'. date("Y-m-d H:i:s");
        $id_link = '';
        $tgdb_link = '';

        if($tgdbid != 0) {
            $tgdb_link = '<a href="http://thegamesdb.net/api/GetGame.php?id=' . $tgdbid . '">G:' . $tgdbid . '</a> ';
        }

        if ($wpid != 0) {
            $id_link = '<a href="' . get_site_url() . '/wp-admin/post.php?post=' . $wpid . '&action=edit">W:' . $wpid . '</a> ';
        }


        $logmsg =  '<tr class="logline"><td class="tgdb-type tgdb-' . $type . '">' . $type . '</td> ' . '<td class="wpid">'.$id_link .'</td>'. '<td class="tgdbid">'.$tgdb_link .'</td>'.'<td>'. $msg .'</td><td>'. $timestamp . '</td></tr>';


        echo $logmsg;

        self::writeLog($logmsg);
    }

    public function insertGame($game, $post_attributes)
    {

        // Insert the post into the database
        if ($wp_id = wp_insert_post($post_attributes)) {

            $this->addCustomField($wp_id, 'score_value', 0);

            if (isset($game->Game->id)) {
                $this->addCustomField($wp_id, 'tgdb_id', $game->Game->id);
            }

            if (isset($game->Game->Developer)) {
                $this->addTerms($wp_id, $game->Game->Developer, 'developer');
            }

            if (isset($game->Game->Publisher)) {
                $this->addTerms($wp_id, $game->Game->Publisher, 'publisher');
            }

            if (isset($game->Game->Genres->genre)) {
                $this->addTerms($wp_id, $game->Game->Genres->genre, 'genre');
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

            if (isset($game->Game->Platform)) {
                $this->addTerms($wp_id, $game->Game->Platform, 'platform');
            }

            if (isset($game->Game->Images)) {
                $this->savePostImage($wp_id, $game, $post_attributes['post_title'], $post_attributes['post_date']);
            }

            $this->log($post_attributes['post_title'] . ' has been created!', 'success', $wp_id, $game->Game->id );
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

            /* set upload directory structure to release date */
            $time = date("Y/m", strtotime($release_date));
            if (strpos($image_url, $this->image_type)) {
                if ($attachment_id = $this->saveURLtoPostThumbnail($wp_id, $url, $title, $time)) {
                    return $attachment_id;
                }
            }
        }

        return false;
    }


    public function saveURLtoPostThumbnail($wp_id, $url, $title, $time = null)
    {
        $parent_post_id = $wp_id;
        $file = $url;
        $wp_filetype = wp_check_filetype(basename($file), null);
        $filename = strtolower(sanitize_file_name($title)) . '.' . $wp_filetype['ext'];

        $upload_file = wp_upload_bits($filename, null, file_get_contents($file), $time);

        if (!$upload_file['error']) {

            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_parent' => $parent_post_id,
                'post_title' => $title,
                'post_content' => '',
                'post_status' => 'inherit'
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

    public function addCustomField($wp_id, $key, $value = '')
    {
        if (isset ($value)) {
            add_post_meta($wp_id, $key, $value) || update_post_meta($wp_id, $key, $value);
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

    public function addCron()
    {

        add_action('wp', array($this, 'prefix_setup_schedule'));
        add_action('games_import', array($this, 'updateGames'));
    }

    public function prefix_setup_schedule()
    {

        if (!wp_next_scheduled('games_import')) {
            wp_schedule_event(time(), 'hourly', 'games_import');
        }
    }

    public function updateGames()
    {
        self::$instance = new self;
        $this->log('cron started', 'notice');
        $games = $this->game_api->getUpdatedGames($this->updatedSeconds);

        foreach ($games->Game as $id) {
            $this->createGame($id);
        }
    }

    public function import($id, $limit = 0)
    {
        $games = $this->getGamesByPlatform($id);

        if (isset($games->Game)) {
            $i = 0;
            foreach ($games->Game as $game) {

                $id = $game->id;
                $this->createGame($id);
                flush();
                if (++$i == $limit) break;
            }
        } else {
            $this->log('thegamedb.net seems to be offline.','error');
        }
    }
}


/**
 * Initialize plugin.
 */
new MarcTVTGDBImporter();

register_activation_hook(__FILE__, array('MarcTVTGDBImporter', 'activated'));
register_deactivation_hook(__FILE__, array('MarcTVTGDBImporter', 'deactivated'));
