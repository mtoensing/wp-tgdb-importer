<?php
/**
 * Plugin Name: TheGamesDB Api Wrapper
 * Plugin URI: http://game-play.dk
 * Description: API Wrapper for TheGamesDB. Used to extract data.
 * Version: 1.0.0
 * Author: Lucas Dechow
 */

class gameDB{

    private $timeout = 30;

    protected $apiUrl = 'http://legacy.thegamesdb.net/api/';
    protected $game;

    public function __construct(){
        // Set Plugin Path
        $this->pluginPath = dirname(__FILE__);

        // Set Plugin URL
        $this->pluginUrl = WP_PLUGIN_URL . '/game-api';


    }

    private function convertXMLArray($xml){
        $xmlbody  = simplexml_load_string($xml);
        $json = json_encode($xmlbody);
        $array = json_decode($json);

        return $array;
    }


    /**
     * Converts the json to xml
     * @param  string $xml A full string of the xml to be parsed
     * @return [type]      [description]
     */
    private function convertXMLJSON($xml){
        $xmlbody  = simplexml_load_string($xml);
        $json = json_encode($xmlbody);

        return $json;
    }

    /**
     * Get the games from TheGamesDB Api
     * @param  string $game string that contains the game title
     * @return array       Return an xml->json based array that
     *                     contains the data information provided
     *                     by the API
     */
    public function getGames($game){
        $call = wp_remote_get($this->apiUrl . 'GetGame.php?name=' . $game, array( 'timeout' => $this->timeout, 'httpversion' => '1.1' ));
        $body = wp_remote_retrieve_body($call);

        return gameDB::convertXMLArray($body);

    }

    public function getGamesList($game){
        $call = wp_remote_get($this->apiUrl . 'GetGamesList.php?name=' . $game, array( 'timeout' => $this->timeout, 'httpversion' => '1.1' ));
        $body = wp_remote_retrieve_body($call);

        return gameDB::convertXMLArray($body);
    }

    public function getPlatformsList(){
        $call = wp_remote_get($this->apiUrl . 'GetPlatformsList.php', array( 'timeout' => $this->timeout, 'httpversion' => '1.1' ) );
        $body = wp_remote_retrieve_body($call);

        return gameDB::convertXMLArray($body);
    }

    public function getPlatformGames($id){
        $call = wp_remote_get($this->apiUrl . 'GetPlatformGames.php?platform=' . $id , array( 'timeout' => $this->timeout, 'httpversion' => '1.1' ));
        $body = wp_remote_retrieve_body($call);

        return gameDB::convertXMLArray($body);
    }

    public function getUpdatedGames($seconds){
        $call = wp_remote_get($this->apiUrl . 'Updates.php?time=' . $seconds, array( 'timeout' => $this->timeout, 'httpversion' => '1.1' ));
        $body = wp_remote_retrieve_body($call);

        return gameDB::convertXMLArray($body);
    }

    /**
     * Get specific game from the ID
     * @param  int $id id that contains the game specific ID
     * @return array     Return the array containing every element.
     */
    public function getGame($id){
        $call = wp_remote_get($this->apiUrl . 'GetGame.php?id=' . $id, array( 'timeout' => $this->timeout, 'httpversion' => '1.1' ));
        $body = wp_remote_retrieve_body($call);

        return gameDB::convertXMLArray($body);
    }
    /**
     * Get specific game box art from the ID
     * @param  int $id id that contains the specific game ID
     * @return string     Return the spefic URL for the game art.
     */
    public function getBoxArt($id){
        $call = wp_remote_get($this->apiUrl . 'GetArt.php?id=' . $id, array( 'timeout' => $this->timeout, 'httpversion' => '1.1' ));
        $body = wp_remote_retrieve_body($call);

        return gameDB::convertXMLArray($body);
    }

}



