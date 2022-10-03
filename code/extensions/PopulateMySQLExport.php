<?php

namespace DNADesign\Populate;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DB;

/**
 * An extension for {@link Populate} which, when applied, exports the result of
 * the database to a file path on the server through mysqldump.
 *
 * This is useful for situations like unit tests where you want to import state
 * but want the speed of using direct mysql queries.
 *
 * Opt into this extension by adding this to your populate.yml configuration.
 *
 * <code>
 *    PopulateMySQLExportExtension:
 *      export_db_path: ~/path.sql
 *
 *    Populate:
 *      extensions
 *        - PopulateMySQLExportExtension
 * </code>
 *
 * @package populate
 */
class PopulateMySQLExportExtension extends Extension
{
    use Configurable;

    /**
     * @config
     */
    private static $export_db_path;

    public function getPath()
    {
        $path = Config::inst()->get(__CLASS__, 'export_db_path');

        if (!$path) {
            $path = Controller::join_links(TEMP_FOLDER . '/populate.sql');
        } else {
            $path = (substr($path, 0, 1) !== "/") ? Controller::join_links(BASE_PATH, $path) : $path;
        }

        return $path;
    }

    /**
     *
     */
    public function onAfterPopulateRecords()
    {
        $path = $this->getPath();

        DB::alteration_message("Saving populate state to $path", "success");
        $result = DB::query('SHOW TABLES');
        $tables = $result->column();
        $return = '';

        foreach ($tables as $table) {
            $return .= 'DROP TABLE IF EXISTS `' . $table . '`;';
            $row2 = DB::query("SHOW CREATE TABLE `$table`");
            $create = $row2->nextRecord();
            $create = str_replace("\"", "`", $create ?? '');
            $return .= "\n\n" . $create['Create Table'] . ";\n\n";

            $result = DB::query("SELECT * FROM `$table`");

            while ($row = $result->nextRecord()) {
                $return .= 'INSERT INTO ' . $table . ' VALUES(';

                foreach ($row as $k => $v) {
                    $v = addslashes($v);
                    $v = str_replace("\n", "\\n", $v ?? '');

                    if ($v) {
                        $return .= '"' . $v . '"';
                    } else {
                        $return .= '""';
                    }

                    $return .= ',';
                }

                $return = rtrim($return, ',');
                $return .= ");\n";
            }
        }

        $return .= "\n\n\n";

        $handle = fopen($path, 'w+');

        fwrite($handle, $return);
        fclose($handle);
    }
}
