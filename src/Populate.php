<?php

namespace DNADesign\Populate;

use Exception;
use InvalidArgumentException;
use SilverStripe\Assets\File;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\YamlFixture;
use SilverStripe\ORM\Connect\DatabaseException;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

class Populate
{
    use Configurable;
    use Extensible;

    private static array $include_yaml_fixtures = [];

    /**
     * An array of classes to clear from the database before importing. While
     * populating SiteTree it may be worth clearing the 'SiteTree' table.
     */
    private static array $truncate_objects = [];

    private static array $truncate_tables = [];

    /**
     * Flag to determine if we're already run for this session (i.e to prevent
     * parent calls invoking {@link requireRecords} twice).
     */
    private static bool $ran = false;

    /**
     * Used internally to not truncate multiple tables multiple times
     */
    private static array $clearedTables = [];

    /**
     * @param bool $force - allows you to bypass the ran check to run this multiple times
     * @throws Exception
     */
    public static function requireRecords(bool $force = false): bool
    {
        if (self::config()->get('ran') && !$force) {
            return true;
        }

        self::config()->set('ran', true);

        if (!self::canBuildOnEnvironment()) {
            throw new Exception(
                'requireRecords can only be run in dev or test (unless allow_build_on_live is true)'
            );
        }

        /** @var PopulateFactory $factory */
        $factory = Injector::inst()->create(PopulateFactory::class);

        foreach (self::stringListFromConfig(self::config()->get('truncate_objects'), 'truncate_objects') as $className) {
            self::truncateObject($className);
        }

        foreach (self::stringListFromConfig(self::config()->get('truncate_tables'), 'truncate_tables') as $table) {
            self::truncateTable($table);
        }

        foreach (self::stringListFromConfig(self::config()->get('include_yaml_fixtures'), 'include_yaml_fixtures') as $fixtureFile) {
            DB::alteration_message(sprintf('Processing %s', $fixtureFile), 'created');
            $fixture = YamlFixture::create($fixtureFile);
            $fixture->writeInto($factory);
        }

        $factory->processFailedFixtures();

        $populate = Injector::inst()->create(Populate::class);
        $populate->extend('onAfterPopulateRecords');

        return true;
    }

    /**
     * Delete all the associated tables for a class
     */
    private static function truncateObject(string $className): void
    {
        if (in_array($className, ClassInfo::subclassesFor(File::class))) {
            foreach (DataList::create($className) as $obj) {
                /** @var File $obj */
                $obj->deleteFile();
            }
        }

        $tables = [];

        // All ancestors or children with tables
        $withTables = array_filter(
            array_merge(
                ClassInfo::ancestry($className),
                ClassInfo::subclassesFor($className)
            ),
            function ($next) {
                return DataObject::getSchema()->classHasTable($next);
            }
        );

        $classTables = [];

        foreach ($withTables as $withTableClass) {
            $tableName = DataObject::getSchema()->tableName($withTableClass);
            if (!is_string($tableName) || $tableName === '') {
                continue;
            }

            $classTables[$withTableClass] = $tableName;
        }

        // Establish tables which store object data that needs to be truncated
        foreach ($classTables as $withTableClass => $baseTable) {
            $obj = Injector::inst()->get($withTableClass);
            if (!$obj instanceof DataObject) {
                continue;
            }

            // Include base tables
            $tables[$baseTable] = $baseTable;

            $versioned = $obj->getExtensionInstance(Versioned::class);
            if (!$versioned instanceof Versioned) {
                // No versioned tables to clear
                continue;
            }

            foreach ($versioned->getVersionedStages() as $stage) {
                if (!is_string($stage)) {
                    continue;
                }

                $stagedTable = $versioned->stageTable($baseTable, $stage);

                // Include staged table(s)
                $tables[$stagedTable] = $stagedTable;
            }

            $versionedTable = "{$baseTable}_Versions";

            // Include versions table
            $tables[$versionedTable] = $versionedTable;
        }

        $populate = Injector::inst()->create(Populate::class);
        $populate->extend('updateTruncateObjectTables', $tables, $className, $classTables);

        if (!is_array($tables)) {
            return;
        }

        $schema = DB::get_schema();
        if ($schema === null) {
            return;
        }

        foreach ($tables as $table) {
            if ($table === '') {
                continue;
            }

            if (!$schema->hasTable($table)) {
                // No table to clear
                continue;
            }

            self::truncateTable($table);
        }
    }

    /**
     * Attempts to truncate a table. Outputs messages to indicate if table has
     * already been truncated or cannot be truncated
     */
    private static function truncateTable(string $table): void
    {
        $stored = self::config()->get('clearedTables');
        $clearedTables = is_array($stored) ? $stored : [];

        if (array_key_exists($table, $clearedTables)) {
            DB::alteration_message("$table already truncated", "deleted");

            return;
        }

        DB::alteration_message("Truncating table $table", "deleted");

        $conn = DB::get_conn();
        if ($conn === null) {
            return;
        }

        try {
            $conn->clearTable($table);
        } catch (DatabaseException $databaseException) {
            DB::alteration_message("Couldn't truncate table $table as it doesn't exist", "deleted");
        }

        $clearedTables[$table] = true;
        self::config()->set('clearedTables', $clearedTables);
    }

    /**
     * @return list<string>
     */
    private static function stringListFromConfig(mixed $value, string $configKey): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a list of strings', $configKey));
        }

        $strings = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException(sprintf('%s must contain only strings', $configKey));
            }

            $strings[] = $item;
        }

        return $strings;
    }

    private static function canBuildOnEnvironment(): bool
    {
        // Populate (by default) is allowed to run on dev and test environments
        if (Director::isDev() || Director::isTest()) {
            return true;
        }

        // Check if developer/s have specified that Populate can run on live
        return (bool) self::config()->get('allow_build_on_live');
    }
}
