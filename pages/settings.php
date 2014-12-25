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

        <? if (isset ($_POST['startimport']) && isset ($_POST[$this->pluginPrefix . '-searchterm'])) {

            $search_term = $_POST[$this->pluginPrefix . '-searchterm'];

            $this->import($search_term);
        } ?>

        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row"><label
                        for="<?php echo $this->pluginPrefix; ?>-searchterm">Platform ID for import </label></th>
                <td><input name="<?php echo $this->pluginPrefix; ?>-searchterm" type="text"
                           id="<?php echo $this->pluginPrefix; ?>-searchterm"
                           value=""
                           class="regular-text">

                    <p class="description"><a href="http://thegamesdb.net/api/GetPlatformsList.php">The Platform IDs</a></p>
                </td>
            </tr>

            </tbody>
        </table>

        <?php submit_button("Import", 'primary', 'startimport'); ?>

    </form>
</div>