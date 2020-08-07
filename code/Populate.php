<?php

namespace DNADesign\Populate;

use Exception;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\YamlFixture;
use SilverStripe\ORM\Connect\DatabaseException;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;

/**
 * @package populate
 */
class Populate
{
    use Configurable;
    use Extensible;

    /**
     * @config
     *
     * @var array
     */
    private static $include_yaml_fixtures = [];

    /**
     * @config
     *
     * An array of classes to clear from the database before importing. While
     * populating SiteTree it may be worth clearing the 'SiteTree' table.
     *
     * @var array
     */
    private static $truncate_classes = [];

    /**
     * @config
     *
     * @var array - Tables that will be truncated
     */
    private static $truncate_tables = [];

    /**
     * Flag to determine if we're already run for this session (i.e to prevent
     * parent calls invoking {@link requireRecords} twice).
     *
     * @var bool
     */
    private static $ran = false;

    /**
     * @var string[] - Used internally to not truncate multiple tables multiple times
     */
    private static $clearedTables = [];

    /**
     * @param bool $force - allows you to bypass the ran check to run this multiple times
     * @return bool
     * @throws Exception
     */
    public static function requireRecords($force = false): bool
    {
        if (self::$ran && !$force) {
            return true;
        }

        self::$ran = true;

        if (!(Director::isDev() || Director::isTest())) {
            throw new Exception('requireRecords can only be run in development or test environments');
        }

        $factory = Injector::inst()->create('DNADesign\Populate\PopulateFactory');

        foreach (self::config()->get('truncate_classes') as $class) {
            self::deleteClass($class);
        }

        foreach (self::config()->get('truncate_tables') as $table) {
            self::truncateTable($table);
        }

        foreach (self::config()->get('include_yaml_fixtures') as $fixtureFile) {
            $fixture = new YamlFixture($fixtureFile);
            $fixture->writeInto($factory);

            $fixture = null;
        }

        $populate = Injector::inst()->create(Populate::class);
        $populate->extend('onAfterPopulateRecords');

        return true;
    }

    /*
     * Delete all the associated tables for a class
     */
    private static function deleteClass(string $class): void
    {
        // First delete the base classes
        $tableClasses = ClassInfo::ancestry($class, true);
        foreach ($tableClasses as $tableClass) {
            $table = DataObject::getSchema()->tableName($tableClass);
            self::truncateTable($table);
        }

        /** @var DataObject|FluentExtension|Versioned $obj */
        $obj = Injector::inst()->get($class);

        $versionedTables = [];
        $hasVersionedExtension = $obj->hasExtension(Versioned::class);

        if ($hasVersionedExtension) {
            $baseTableName = Config::inst()->get($class, 'table_name');
            $stages = $obj->getVersionedStages();

            foreach ($stages as $stage) {
                $table = $obj->stageTable($baseTableName, $stage);
                self::truncateTable($table);
                $versionedTables[] = $table;
            }
        }

        if ($obj->hasExtension(FluentExtension::class)) {
            // Fluent passes back `['table_name' => ['arrayOfLocalisedFields']]`
            $localisedTables = array_keys($obj->getLocalisedTables());

            foreach ($localisedTables as $localisedTable) {
                $table = $obj->getLocalisedTable($localisedTable);
                self::truncateTable($table);

                if ($hasVersionedExtension) {
                    self::truncateTable($table . FluentVersionedExtension::SUFFIX_VERSIONS);
                }
            }

            if ($hasVersionedExtension) {
                foreach ($versionedTables as $versionedTable) {
                    $table = $obj->getLocalisedTable($versionedTable);
                    self::truncateTable($table);
                }
            }
        }

    }

    /*
     * Attempts to truncate a table if it hasn't already been truncated
     */
    private static function truncateTable(string $table): void
    {
        if (array_key_exists($table, self::$clearedTables)) {
            DB::alteration_message("$table already truncated", "deleted");

            return;
        }

        DB::alteration_message("Truncating table $table", "deleted");

        try {
            DB::get_conn()->clearTable($table);
        } catch (DatabaseException $databaseException) {
            DB::alteration_message("Couldn't truncate table $table as it doesn't exist", "deleted");
        }

        self::$clearedTables[$table] = true;
    }
}
