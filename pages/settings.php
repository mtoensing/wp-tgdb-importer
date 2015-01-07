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
    <style type="text/css">

        table.tgdb-log {
            width: 100%;
        }
        .tgdb-type  {
            font-weight: 700;
        }

        table.tgdb-log th {
            text-align: left;
            font-weight: 700;;
            background: #333;
            color: #fff;
        }

        table.tgdb-log tr:nth-child(even) {
            background-color: #eee;
        }
        table.tgdb-log tr:nth-child(odd) {
            background-color: #fff;
        }
        table.tgdb-log th {
            color: white;
            background-color: black;
        }

        table.tgdb-log td,
        table.tgdb-log th {
            padding: 0.3em 0.3em;
        }

        td.wpid,td.tgdbid {
            text-align: center;
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
    <form method="post" action="options.php">

        <?php

        settings_fields($this->pluginPrefix . '-settings-group');
        do_settings_sections($this->pluginPrefix . '-settings-group');

        $platforms = $this->getPlatforms();
        $platform_title = $this->getPlatformTitle($platforms);

        if (get_option($this->pluginPrefix . '-startimport')) {
            update_option($this->pluginPrefix . '-startimport', false);
            $search_term = get_option($this->pluginPrefix . '-platform');

            $limit = 0;

            if (get_option($this->pluginPrefix . '-limit')) {
                $limit = 10;
            };

            echo '<h2>Importing games from ' . $platform_title . ' (Limit ' . $limit . ')</h2>';
            echo '<table class="tgdb-log">';
            echo '<tr>
                <th>Type</th>
                <th>WPID</th>
                <th>TGDBID</th>
                <th>Message</th>
                <th>Timestamp</th>
              </tr>';

            $this->import($search_term, $limit);
            echo '</table>';
        }

        ?>

        <table class="form-table">
            <tr valign="top">
                <th scope="row">Limit</th>
                <td>
                    <p><input id="marctv-tgdb-limit"
                              name="marctv-tgdb-limit" <?php checked(get_option($this->pluginPrefix . '-limit'), 'on'); ?>
                              type="checkbox"/> <label for="marctv-tgdb-limit">Limit the items of the import to
                            10</label>
                    </p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo __('Platform', $this->pluginPrefix); ?></th>
                <td>
                    <p>
                        <select name="marctv-tgdb-platform">
                            <?php
                            foreach ($platforms->Platforms->Platform as $platform) {
                                ?>
                                <option <?php selected(get_option($this->pluginPrefix . '-platform'), $platform->id); ?>
                                    value="<?php echo esc_attr($platform->id) ?>"><?php echo $platform->name; ?></option>
                            <?php } ?>
                        </select>
                    </p>
                    <p><label for="marctv-tgdb-platform">
                            <?php echo __('Select a platform.', 'marctv-galleria'); ?>
                        </label></p>
                    </fieldset>

                </td>
            </tr>
        </table>

        <input type="hidden" name="<?php echo $this->pluginPrefix; ?>-startimport" value="true">

        <?php submit_button('import'); ?>
    </form>


</div>