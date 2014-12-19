<?php

/*
Plugin Name: MarcTV The GameDatabase Importer
Plugin URI: http://marctv.de/blog/marctv-wordpress-plugins/
Description:
Version:  0.1
Author:  Marc Tönsing
Author URI: marctv.de
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class MarcTVTGDBImporter
{

    private $version = '0.1';
    private $pluginPrefix = 'marctv-tgdb-importer';
    private $taxonomy_mapping = array();

    public function __construct()
    {
        $this->initBackend();
    }


    public function initBackend() {
        add_action( 'admin_menu', array($this,'tgdb_import_menu'));
    }


    /** Step 1. */
    public function tgdb_import_menu() {
        add_submenu_page('tools.php', 'TGDB Import', 'TGDB Import', 'manage_options', $this->pluginPrefix, array($this,'tgdb_import_options') );
    }

    /** Step 3. */
    public function tgdb_import_options() {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        echo '<div class="wrap">';
        if( isset ($_POST['startimport'])) {
            $game = $this->exampleGetGame("Halo 3: ODST");
        }
        echo '<form method="post" action="tools.php?page=marctv-tgdb-importer">';
         submit_button("Import",'primary','startimport');
        echo '</form>';
        echo '</div>';
    }


    private function acme_post_exists($id)
    {
        return is_string(get_post_status($id));
    }

    public function createGame($id)
    {

        $gameAPI = new gameDB();
        $game = $gameAPI->getGame($id);

            if ($this->acme_post_exists($game->Game->id )) {
                echo '<p>ID ' . $game->Game->id . ' with the title <a href="/wp-admin/post.php?post='. $game->Game->id .'&action=edit">' . get_the_title( $game->Game->id ) . '</a> already exists! </p>';
                error_log('ID ' . $game->Game->id . ' already exists!');
                return false;
            }

            if (!isset($game->Game)) {
                return false;
            }

            if (isset($game->Game->Overview)) {
                $overview = $game->Game->Overview;
            } else {
                return false;
            }

            if (isset ($game->Game->GameTitle)) {
                $gametitle = $game->Game->GameTitle;
            } else {
                return false;
            }

            if (isset ($game->Game->ReleaseDate)) {
                $releasedate = date("Y-m-d H:i:s", strtotime($game->Game->ReleaseDate));
            }

            if (isset($game->Game->Genres)) {

                if (count($game->Game->Genres->genre) > 1) {
                    foreach ($game->Game->Genres->genre as $genre) {

                        if( ! term_exists($genre, 'genre') ) {

                            wp_insert_term($genre, 'genre', $args = array());
                        }
                    }
                } else {

                    $genre = $game->Game->Genres->genre;
                    if( ! term_exists($genre, 'genre') ) {

                        wp_insert_term($genre, 'genre', $args = array());
                    }
                }
            }

            $new_game = array(
                'post_status' => 'draft',
                'post_content' => $overview,
                'post_title' => $gametitle,
                'post_type' => 'game',
                'post_author' => 1,
                'post_date'      => $releasedate, //[ Y-m-d H:i:s ]
                'ping_status' => get_option('default_ping_status'),
                'post_parent' => 0,
                'menu_order' => 0,
                'to_ping' => '',
                'pinged' => '',
                'post_password' => '',
                'guid' => '',
                'post_content_filtered' => '',
                'post_excerpt' => '',
                //'post_category' => array(8,39),
                'import_id' => $game->Game->id
            );


            // Insert the post into the database
            if( $wp_id = wp_insert_post( $new_game ) ) {
                echo '<p>Successfully created <a href="/wp-admin/post.php?post='.$wp_id.'&action=edit">' . $gametitle . '</a></p>';
            }


        if (isset ($game->Game->Developer)) {
            add_post_meta($wp_id, 'Developer', $game->Game->Developer, true) || update_post_meta($wp_id, 'Developer', $game->Game->Developer);
        }
        if (isset ($game->Game->Publisher)) {

            add_post_meta($wp_id, 'Publisher', $game->Game->Publisher, true) || update_post_meta($wp_id, 'Publisher', $game->Game->Publisher);
        }

            //wp_redirect( get_permalink( $wp_id ));

    }

    public function exampleGetGame($name)
    {
        $gameAPI = new gameDB();
        $games = $gameAPI->getGamesList($name);
        $markup = '<ol>';
        if (count($games->Game) > 1) {

            foreach ($games->Game as $game) {
                $id = $game->id;
                $game = $gameAPI->getGame($id);
                //var_dump($game);
                $this->createGame($id);
                $markup .= '<li><a href="#">' . $game->Game->GameTitle . '</a></li>';
            }


        } else {
            //GET SINGLE GAME FROM LISTING

            $game = $games->Game;
            $id = $game->id;
            $game = $gameAPI->getGame($id);
            $title = $game->Game->GameTitle;
            //print_r($title);
            $markup = '<li><a href="#">' . $game->Game->GameTitle . '</a></li>';

        }

        $markup .= '</ol>';

        return $markup;
    }

    public function exampleGetSingleGame($name)
    {
        $gameAPI = new gameDB();
        $games = $gameAPI->getGamesList($name);
        $markup = '<ol>';
        if (count($games->Game) > 1) {

            foreach ($games->Game as $game) {
                $id = $game->id;
                $game = $gameAPI->getGame($id);
                //var_dump($game);

                $markup .= '<li><a href="#">' . $game->Game->GameTitle . '</a></li>';
                //print_r($title);
            }


        } else {
            //GET SINGLE GAME FROM LISTING

            $game = $games->Game;
            $id = $game->id;
            $game = $gameAPI->getGame($id);
            $title = $game->Game->GameTitle;
            //print_r($title);
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
