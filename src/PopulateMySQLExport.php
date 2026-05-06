<?php

namespace DNADesign\Populate;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Core\TempFolder;
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
 * @extends Extension<Populate>
 */
class PopulateMySQLExportExtension extends Extension
{
    use Configurable;

    /**
     * @config
     */
    private static ?string $export_db_path = null;

    public function getPath(): string
    {
        $path = Config::inst()->get(self::class, 'export_db_path');

        if (!is_string($path) || $path === '') {
            $path = Controller::join_links(TempFolder::getTempFolder(BASE_PATH), 'populate.sql');
        } elseif (substr($path, 0, 1) !== '/') {
            $path = Controller::join_links(BASE_PATH, $path);
        }

        return $path;
    }

    public function onAfterPopulateRecords(): void
    {
        $path = $this->getPath();

        DB::alteration_message("Saving populate state to $path", "success");
        $result = DB::query('SHOW TABLES');
        $tables = $result->column();
        $return = '';

        foreach ($tables as $table) {
            if (!is_string($table)) {
                continue;
            }

            $return .= 'DROP TABLE IF EXISTS `' . $table . '`;';
            $row2 = DB::query("SHOW CREATE TABLE `$table`");
            $createRow = $row2->record();

            $createSql = $createRow['Create Table'] ?? null;
            if (!is_string($createSql)) {
                continue;
            }

            $createSqlEscaped = str_replace('"', '`', $createSql);
            $return .= "\n\n" . $createSqlEscaped . ";\n\n";

            $selectResult = DB::query("SELECT * FROM `$table`");

            foreach ($selectResult as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $return .= 'INSERT INTO ' . $table . ' VALUES(';

                foreach ($row as $v) {
                    $cell = is_string($v) || is_numeric($v) ? (string) $v : '';
                    $cell = addslashes($cell);
                    $cell = str_replace("\n", "\\n", $cell);

                    if ($cell !== '') {
                        $return .= '"' . $cell . '"';
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
        if ($handle === false) {
            DB::alteration_message(sprintf('Could not open %s for writing', $path), 'error');

            return;
        }

        fwrite($handle, $return);
        fclose($handle);
    }
}
