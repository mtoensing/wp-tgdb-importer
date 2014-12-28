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
    <style>

        .tgdb-log span {
            font-weight: 700;
            background: #fff;
            border-left: 4px solid #fff;
            -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
            margin: 5px 15px 2px;
            padding: 1px 12px;
        }

        .tgdb-error {
            color: darkred;
        }

        .tgdb-notice {
            color: darkorange;
        }

        .tgdb-success {
            color: darkgreen;
        }


    </style>
    <form method="post" action="tools.php?page=marctv-tgdb-importer">
        <?
        if (isset ($_GET['plattform_id'])) {

            $search_term = $_GET['plattform_id'];
            echo '<h2>log:</h2>';
            echo '<pre class="tgdb-log">';
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