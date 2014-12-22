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
        $sql_obj = $wpdb->get_row("SELECT * FROM wp_posts WHERE post_title = '" . mysql_real_escape_string($title_str) . "'", 'ARRAY_A');

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


        /* check if post/game id exists */
        if ($this->post_exists($game->Game->id)) {
            return '<p>ID ' . $game->Game->id . ' with the title <a href="/wp-admin/post.php?post=' . $game->Game->id . '&action=edit">' . get_the_title($game->Game->id) . '</a> already exists! </p>';
            error_log('ID ' . $game->Game->id . ' already exists!');

            return false;
        }

        /* check if game already exists */
        if ($double_title = $this->post_exists_by_title(($game->Game->GameTitle))) {
            echo 'Title ' . $game->Game->GameTitle . ' already exists! Adding platform.</p>';
            error_log('Title ' . $game->Game->GameTitle . ' already exists! Adding platform.');
            /* if this is the case add the platforms */
            $this->addTerms($double_title, $game->Game->Platform, 'platform');

            return false;
        }

        if (isset($game->Game->Overview)) {
            $overview = $game->Game->Overview;
        }

        if (isset ($game->Game->GameTitle)) {
            $game_title = $game->Game->GameTitle;
        } else {
            return false;
        }

        if (isset ($game->Game->ReleaseDate)) {
            $release_date = date("Y-m-d H:i:s", strtotime($game->Game->ReleaseDate) + 43200); // release date plus 12 hours.
        }

        $post_attributes = array_merge($this->post_defaults, array(
            'post_content' => $overview,
            'post_title' => $game_title,
            'post_date' => $release_date, //[ Y-m-d H:i:s ]
            'import_id' => $game->Game->id
        ));

        // Insert the post into the database
        if ($wp_id = wp_insert_post($post_attributes)) {
            echo '<p>Successfully created <a href="/wp-admin/post.php?post=' . $wp_id . '&action=edit">' . $game_title . '</a></p>';

            $this->addCustomField($wp_id, 'Developer', $game->Game->Developer);

            $this->addCustomField($wp_id, 'Publisher', $game->Game->Publisher);

            $this->addCustomField($wp_id, 'ESRB', $game->Game->ESRB);

            $this->addCustomField($wp_id, 'Youtube', $game->Game->Youtube);

            $this->addTerms($wp_id, $game->Game->Genres->genre, 'genre');

            $this->addTerms($wp_id, $game->Game->Platform, 'platform');


            if (isset ($game->Game->Images)) {
                foreach ($game->Game->Images as $image_slug) {
                    if (isset($image_slug->original)) {
                        $this->saveImage($wp_id, $game->baseImgUrl, $image_slug->original);
                    }
                }
            }
        }

    }


    public function saveImage($wp_id, $image_base, $image_slug)
    {
        $parent_post_id = $wp_id;
        $file = $image_base . $image_slug;
        $filename = basename($file);

        $upload_file = wp_upload_bits($filename, null, file_get_contents($file));
        if (!$upload_file['error']) {
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_parent' => $parent_post_id,
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $parent_post_id);
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
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
        $markup = '<ol>';
        if (count($games->Game) > 1) {

            foreach ($games->Game as $game) {
                $id = $game->id;
                $this->createGame($id);
                $markup .= '<li><a href="#">' . $game->Game->GameTitle . '</a></li>';
            }


        } else {
            //GET SINGLE GAME FROM LISTING

            $game = $games->Game;
            $id = $game->id;
            $this->createGame($id);

            $markup = '<li><a href="#">' . $game->Game->GameTitle . '</a></li>';

        }

        $markup .= '</ol>';

        return $markup;
    }

}


/**
 * Initialize plugin.
 */
new MarcTVTGDBImporter();
