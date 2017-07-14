<?php

/*
Plugin Name: The GameDatabase Importer
Plugin URI: http://marc.tv/blog/marctv-wordpress-plugins/
Description: Imports games from TheGameDatabase API as "game" post types.
Version:  1.3
Author:  Marc Tönsing
Author URI: https://marc.tv
License URI: http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: mtoensing/wp-tgdb-importer
*/

require_once('classes/game-api.php');

class MarcTVTGDBImporter
{
    private $pluginUrl = '';
    private $updatedSeconds = 172800;
    private $releaseDelaySeconds = 60;
    private $supported_platforms = array(
        '3DO',
        'Android',
        'Arcade',
        'Amiga',
        'Commodore 64',
        'iOS',
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
        'Sega Game Gear',
        'Sega Master System',
        'Sega Mega Drive',
        'Sega Saturn',
        'NeoGeo',
        'Nintendo Game Boy',
        'Nintendo Game Boy Advance',
        'Nintendo Game Boy Color',
        'Nintendo DS',
        'Nintendo 3DS',
        'Super Nintendo (SNES)',
        'Nintendo Entertainment System (NES)',
        'Nintendo 64',
        'Nintendo Wii',
        'Nintendo Wii U',
        'Nintendo Switch'
    );
    private $image_type = 'front';
    private $pluginPrefix = 'marctv-tgdb';
    private $logfile = 'tgdbimport.log'; // should be writable in wp-content
    private $post_defaults = '';
    private $post_type = 'game';
    private $game_api;

    /**
     *
     */
    public function __construct()
    {
        $this->pluginUrl = plugins_url(false, __FILE__);

        // post defaults for new games post types
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

        // get instance of GameDB API
        $this->game_api = new gameDB();

        $this->initDataStructures();

        $this->initBackend();

        // add wp cron for updates
        $this->addCron();
    }


    /**
     * register taxonomies, post type etc.
     */
    public function initDataStructures()
    {
        add_action('init', array($this, 'create_post_type_game'));
        add_action('init', array($this, 'create_platform_taxonomy'));
        add_action('init', array($this, 'create_genre_taxonomy'));
        add_action('init', array($this, 'create_developer_taxonomy'));
        add_action('init', array($this, 'create_publisher_taxonomy'));
        add_action('init', array($this, 'create_coop_taxonomy'));
        add_action('init', array($this, 'create_players_taxonomy'));
        add_action('init', array($this, 'create_fps_taxonomy'));
    }

    /**
     *
     */
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
                'show_in_rest' => true,
                'supports' => array(
                    'title',
                    'auhor',
                    'editor',
                    'publicize',
                    'thumbnail',
                    'comments',
                    'custom-fields',
                    'post-formats')
            )
        );
    }


    /**
     *
     */
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

    /**
     *
     */
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

    /**
     *
     */
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

    /**
     *
     */
    public function create_fps_taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'fps',
            $this->post_type,
            array(
                'label' => __('FPS'),
                'rewrite' => array(
                    'slug' => 'fps'
                ),
            )
        );
    }

    public function create_taxonomy($name)
    {
        // create a new taxonomy
        register_taxonomy(
            strtolower($name),
            $this->post_type,
            array(
                'label' => __($name),
                'rewrite' => array(
                    'slug' => strtolower($name)
                ),
            )
        );
    }

    /**
     *
     */
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

    /**
     *
     */
    public function create_coop_taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'coop',
            $this->post_type,
            array(
                'label' => __('Co-op'),
                'rewrite' => array(
                    'slug' => 'coop',
                    'hierarchical' => false
                ),

            )
        );
    }

    /**
     *
     */
    public function create_players_taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'players',
            $this->post_type,
            array(
                'label' => __('Players'),
                'rewrite' => array(
                    'slug' => 'players',
                    'hierarchical' => false
                ),

            )
        );
    }


    /**
     *
     */
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
        register_setting($this->pluginPrefix . '-settings-group', $this->pluginPrefix . '-startcsvimport');


    }

    /**
     * add settings pages
     */
    public function tgdb_import_menu()
    {
        add_options_page('TGDB Import', 'TGDB Import', 'manage_options', $this->pluginPrefix, array($this, 'tgdb_import_options'));
    }

    /**
     * include settings page
     */
    public function tgdb_import_options()
    {
        require_once('pages/settings.php');
    }

    /**
     * Check if post id exists
     * @param $id
     * @return bool
     */
    private function post_exists($id)
    {
        return is_string(get_post_status($id));
    }

    /**
     * Does a post exist by title
     * @param $title_str
     * @return bool
     */
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

    /**
     * create a game and write it to the db
     * @param $id
     * @return bool|int|WP_Error
     */
    public function createGame($id)
    {
        $game_data = $this->game_api->getGame($id);

        if ($post_attributes = $this->getPostAttributes($game_data)) {
            if ($wp_id = $this->insertGame($game_data, $post_attributes)) {

                return $wp_id;
            }
        }

        return false;
    }

    /**
     * Is this string in an array
     * @param $str
     * @param array $arr
     * @return bool
     */
    public function isStringinArray($str, array $arr)
    {
        foreach ($arr as $a) {
            if (strpos($a, $str) !== false) return true;
        }

        return false;
    }

    /**
     * get platform title from options
     * @param $platforms
     * @return bool
     */
    public function getPlatformTitle($platforms)
    {
        foreach ($platforms->Platforms->Platform as $platform) {
            if ($platform->id == get_option($this->pluginPrefix . '-platform')) {
                $platform_title = $platform->name;
                return $platform_title;
            }
        }

        return false;
    }

    /**
     * Get array of platform from cache or api
     * @return array|mixed
     */
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

            $i = 0;
            foreach ($platforms->Platforms->Platform as $platform) {
                if (!in_array($platform->name, $this->supported_platforms)) {
                    unset($platforms->Platforms->Platform[$i]);
                }
                $i++;
            }

            set_transient('marctv-tgdb-plattforms', $platforms, 60);
        }

        return $platforms;
    }

    /**
     * write to local log file
     * @param $msg
     */
    private function writeLog($msg)
    {
        $upload_dir = wp_upload_dir();

        $file = $upload_dir['basedir'] . '/' . $this->logfile;

        file_put_contents($file, strip_tags($msg) . PHP_EOL, FILE_APPEND);
    }

    private function validateDate($date)
    {
        $d = DateTime::createFromFormat('m/d/Y', $date);
        return $d && $d->format('m/d/Y') == $date;
    }

    /**
     *
     * get the post attributes with checks for each game.
     * returns false if game is not valid
     *
     * @param $game
     * @return array|bool
     */
    public function getPostAttributes($game)
    {
        if (isset($game->Game->id)) {
            $game_id = $game->Game->id;
        } else {
            $this->log('no id.', 'error');
            return false;
        }

        if (isset($game->Game->GameTitle)) {
            $game_title = $game->Game->GameTitle;
        } else {
            $this->log('no title.', 'error', 0, $game_id);
            return false;
        }

        if (isset($game->Game->ReleaseDate)) {
            if ($this->validateDate($game->Game->ReleaseDate)) {
                $release_date = date("Y-m-d H:i:s", strtotime($game->Game->ReleaseDate) + $this->releaseDelaySeconds); // release date plus 12 hours.
            } else {
                $this->log($game_title . ' wrong release date format.', 'error', 0, $game_id);

                return false;
            }
        } else {
            $this->log($game_title . ' has no release date.', 'error', 0, $game_id);

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
            if (empty($wpid)) {
                $wpid = 1;
            }
            $this->log($game_title . ' already exists.', 'notice', $wpid, $game_id);
            return false;
        }

        /* check if image is present */
        if (!$this->isStringinArray($this->image_type, $this->getTreeLeaves($game->Game->Images))) {
            $this->log($game_title . 'has no ' . $this->image_type . ' image.', 'error', 0, $game_id);
            return false;
        }

        if (isset($game->Game->Platform)) {
            $game_platform = $game->Game->Platform;
        } else {
            $this->log($game_title . ' no platform.', 'error', 0, $game_id);
            return false;
        }

        if (!in_array($game_platform, $this->supported_platforms)) {
            $this->log($game_title . ': Platform ' . $game_platform . ' not supported.', 'error', 0, $game_id);
            return false;
        }

        /* check if game already exists */
        if ($wpid = $this->post_exists_by_title(($game_title))) {
            $this->log($game_title . ' already exists! Adding platform.', 'notice', $wpid, $game_id);
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

    /**
     * write messages to admin screen and logs if not cron
     *
     * @param string $msg
     * @param string $type
     * @param int $wpid
     * @param int $tgdbid
     */
    public function log($msg = '', $type = 'notice', $wpid = 0, $tgdbid = 0)
    {
        $timestamp = ' @' . date("Y-m-d H:i:s");
        $id_link = '';
        $tgdb_link = '';

        if ($tgdbid != 0) {
            $tgdb_link = '<a href="http://thegamesdb.net/api/GetGame.php?id=' . $tgdbid . '">G:' . $tgdbid . '</a> ';
        }

        if ($wpid != 0) {
            $id_link = '<a href="' . get_site_url() . '/wp-admin/post.php?post=' . $wpid . '&action=edit">W:' . $wpid . '</a> ';
        }


        $logmsg = '<tr class="logline"><td class="tgdb-type tgdb-' . $type . '">' . $type . '</td> ' . '<td class="wpid">' . $id_link . '</td>' . '<td class="tgdbid">' . $tgdb_link . '</td>' . '<td>' . $msg . '</td><td>' . $timestamp . '</td></tr>';

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen->base == 'settings_page_marctv-tgdb') {
                echo $logmsg;
            }
        }

        $this->writeLog($logmsg);
    }

    /**
     * insert game into wordpress db.
     *
     * @param $game
     * @param $post_attributes
     * @return bool|int|WP_Error
     */
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
                $this->addTerms($wp_id, $game->Game->{'Co-op'}, 'coop');
            }

            if (isset($game->Game->Players)) {
                $this->addTerms($wp_id, $game->Game->Players, 'players');
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

            $this->log($post_attributes['post_title'] . ' has been created!', 'success', $wp_id, $game->Game->id);
            return $wp_id;

        } else {

            return false;

        }

    }


    /**
     *
     * dump stuff with pre tags.
     * helper function
     * @param $stuff
     */
    private function dump($stuff)
    {
        echo '<pre>';
        var_dump($stuff);
        echo '</pre>';
    }

    public function importCSV($array, $write = false)
    {
        $markup = '<table class="tgdb-log">';
        $tax = array();
        $r = 0;
        $logline = '';
        foreach ($array as $row) {

            $markup .= "<tr>";

            $c = 0;
            $title = '';
            foreach ($row as $column) {

                if ($c == 0) {
                    $id = $this->post_exists_by_title($column);
                    $title = $column;
                }

                if ($r == 0) {
                    $markup .= "<th>";
                } else {
                    if ($id > 0) {
                        $markup .= '<td class="in">';
                    } else {
                        $markup .= "<td>";
                    }
                }

                if ($r == 0 && $c > 0) {
                    $tax[] = $column;
                }

                $markup .= $column;
                if ($id > 0 && $c > 0 && $r > 0) {
                    $taxonomy = $tax[$c - 1];
                    $value = $column;

                    if(taxonomy_exists($taxonomy) ){
                        if($value != ''){
                            $logline .= 'Adding taxonomy <strong>' . $tax[$c - 1] . '</strong> with value "<em>' . $value . '</em>" to <a href="/wp-admin/post.php?post=' . $id . '&action=edit">' . $title . '</a></br>';

                            if ($write == true) {
                                $this->addTerms($id, $value, $tax[$c - 1]);
                            }

                        } else {
                            $logline .= 'Value is empty.</br>';
                        }

                    } else {
                        $logline .= 'Taxonomy: <strong>' . $taxonomy . '</strong> does not exists! </br>';
                    }

                }

                if ($r == 0) {
                    $markup .= "</th>";
                } else {
                    $markup .= "</td>";
                }
                $c++;
            }
            $r++;
            $markup .= "</tr>";
        }
        $markup .= "</table>";
        $markup .= "<pre>" . $logline . "</pre>";

        if ($write != true) {
            echo "<h3><strong>Nothing written! Check write to database option.</strong></h3>";
        } else {
            echo "<h3><strong>Changes saved to database!</strong></h3>";
        }

        return $markup;
    }

    /**
     *
     * returns all children als strings of an object tree in an single dimensional array
     *
     * @param $object
     * @return array
     */
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

    /**
     * checks if string is somewhere in an array.
     *
     * @param $haystack
     * @param array $needles
     * @param int $offset
     * @return bool|mixed
     */
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

    /**
     * save an image to a post
     *
     * @param $wp_id
     * @param $game
     * @param $title
     * @param $release_date
     * @return bool|int
     */
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


    /**
     * saves an image url as an attachment.
     * return the attachment id if successful.
     *
     * @param $wp_id
     * @param $url
     * @param $title
     * @param null $time
     * @return bool|int
     */
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

    /**
     * Adds a custom field to a post id.
     *
     * @param $wp_id
     * @param $key
     * @param string $value
     */
    public function addCustomField($wp_id, $key, $value = '')
    {
        if (isset ($value)) {
            add_post_meta($wp_id, $key, $value) || update_post_meta($wp_id, $key, $value);
        }
    }

    /**
     * Adds a term to a taxonomy
     *
     * @param $wp_id
     * @param $term_obj
     * @param $taxonomy_string
     */
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

    /**
     * retrieve games by platform id
     *
     * @param $id
     * @return array|mixed
     */
    public function getGamesByPlatform($id)
    {
        $games = $this->game_api->getPlatformGames($id);

        return $games;
    }

    /**
     * adds the wp cron event.
     */
    public function addCron()
    {
        add_action('wp', array($this, 'prefix_setup_schedule'));
        add_action('games_import', array($this, 'tgdb_import_updates'));
    }

    /**
     * check if cron action is already scheduled.
     */
    public function prefix_setup_schedule()
    {
        if (!wp_next_scheduled('games_import')) {
            wp_schedule_event(time(), 'twicedaily', 'games_import');
        }
    }

    /**
     * get updated games from api and write them to database.
     * initiated by wp cron.
     */
    public function tgdb_import_updates()
    {
        $this->log('cron: started.', 'notice');
        $games = $this->game_api->getUpdatedGames($this->updatedSeconds);

        if (isset($games->Game)) {
            foreach ($games->Game as $id) {
                $this->createGame($id);
            }
        } else {
            $this->log('cron: no new game updates.', 'notice');
        }
    }

    /**
     * Import games by platform id
     * @param $id
     * @param int $limit
     */
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
            $this->log('thegamedb.net seems to be offline.', 'error');
        }
    }

    public function get2DArrayFromCsv($file, $delimiter)
    {
        if (($handle = fopen($file, "r")) !== FALSE) {
            $i = 0;
            while (($lineArray = fgetcsv($handle, 4000, $delimiter)) !== FALSE) {
                for ($j = 0; $j < count($lineArray); $j++) {
                    $data2DArray[$i][$j] = $lineArray[$j];
                }
                $i++;
            }
            fclose($handle);
        }
        return $data2DArray;
    }
}


/**
 * Initialize plugin.
 */
new MarcTVTGDBImporter();
