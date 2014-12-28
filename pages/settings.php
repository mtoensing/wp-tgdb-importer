<?php
if (!defined('ABSPATH')) {
    die(__('Cheatin&#8217; uh?'));
}
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

?>

<div id="wrap">
    <h1>The Game Database Importer</h1>

    <form method="post" action="tools.php?page=marctv-tgdb-importer">
        <?
        if (isset ($_GET['plattform_id'])) {

            $search_term = $_GET['plattform_id'];
            echo '<h2>log:</h2>';
            echo '<pre>';
            $this->import($search_term,10);
            echo '</pre>';
        }

        $xml = simplexml_load_file('http://thegamesdb.net/api/GetPlatformsList.php');

        echo '<h2>Select one of these platforms to start the import process.</h2>';
        foreach($xml->Platforms->Platform as $platform) {
            echo '<a href="tools.php?page=marctv-tgdb-importer&plattform_id=' . $platform->id  . '">' . $platform->name . '</a></br>';
        }

        ?>

    </form>
</div>