<?php

namespace DNADesign\Populate;

use Exception;
use InvalidArgumentException;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FixtureBlueprint;
use SilverStripe\Dev\FixtureFactory;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

use function basename;
use function dirname;
use function file_get_contents;
use function hash_equals;
use function is_array;
use function is_string;
use function sha1;
use function str_replace;

class PopulateFactory extends FixtureFactory
{
    /**
     * List of fixtures that failed to be created due to YAML fixture lookup failures (e.g. because of a dependency that
     * isn't met at the time of creation). We re-try creation of these after all other fixtures have been created.
     *
     * @var list<array{class: string, id: string, data: array<string, mixed>|null}>
     */
    private array $failedFixtures = [];

    /**
     * Creates the object in the database as the original object will be wiped.
     *
     * @param array<string, mixed>|null $data Map of properties. Overrides default data
     * @return DataObject|bool|null
     * @phpstan-impure
     */
    public function createObject($name, $identifier, $data = null): DataObject|bool|null
    {
        if (!is_string($name) || !is_string($identifier)) {
            throw new InvalidArgumentException('Fixture class name and identifier must be strings');
        }

        if ($data !== null && !is_array($data)) {
            throw new InvalidArgumentException('Fixture data must be an array or null');
        }

        $fixtureData = is_array($data) ? $data : null;

        DB::alteration_message("Creating $identifier ($name)", "created");

        if ($fixtureData !== null) {
            foreach ($fixtureData as $k => $v) {
                if (is_array($v) || !is_string($v)) {
                    continue;
                }

                if (preg_match('/^`(.)*`;$/', $v)) {
                    $str = substr($v, 1, -2);
                    $pv = null;

                    eval("\$pv = $str;");

                    $fixtureData[$k] = $pv;
                }
            }
        }

        // for files copy the source dir if the image has a 'PopulateFileFrom'
        // Follows silverstripe/asset-admin logic, see AssetAdmin::apiCreateFile()
        if ($fixtureData !== null && isset($fixtureData['PopulateFileFrom'])) {
            $file = $this->populateFile($fixtureData);

            if ($file instanceof File) {
                // Skip the rest of this method (populateFile sets all other
                // values on the object), just return the created file
                $this->rememberFixtureIdentifier($name, $identifier, (int) $file->ID);

                return $file;
            }

            if ($file === true) {
                return true;
            }
        }

        // if any merge labels are defined then we should create the object
        // from that
        $lookup = null;

        if ($fixtureData !== null && isset($fixtureData['PopulateMergeWhen'])) {
            $when = $fixtureData['PopulateMergeWhen'];
            if (!is_string($when)) {
                throw new InvalidArgumentException('PopulateMergeWhen must be a string SQL fragment');
            }

            $lookup = DataList::create($name)->where($when);

            unset($fixtureData['PopulateMergeWhen']);
        } elseif ($fixtureData !== null && isset($fixtureData['PopulateMergeMatch'])) {
            $mergeMatch = $fixtureData['PopulateMergeMatch'];
            if (!is_array($mergeMatch)) {
                throw new InvalidArgumentException('PopulateMergeMatch must be a list of field names');
            }

            $filter = [];

            foreach ($mergeMatch as $field) {
                if (!is_string($field)) {
                    throw new InvalidArgumentException('PopulateMergeMatch must contain only string field names');
                }

                if (!array_key_exists($field, $fixtureData)) {
                    throw new InvalidArgumentException(sprintf('PopulateMergeMatch references missing field %s', $field));
                }

                $filter[$field] = $fixtureData[$field];
            }

            if ($filter === []) {
                throw new Exception('Not a valid PopulateMergeMatch filter');
            }

            $lookup = DataList::create($name)->filter($filter);

            unset($fixtureData['PopulateMergeMatch']);
        } elseif ($fixtureData !== null && isset($fixtureData['PopulateMergeAny'])) {
            $lookup = DataList::create($name);

            unset($fixtureData['PopulateMergeAny']);
        }

        $obj = null;

        if ($lookup !== null && $lookup->count() > 0) {
            $existing = $lookup->first();
            if (!$existing instanceof DataObject) {
                throw new Exception('Populate merge lookup returned no DataObject');
            }

            foreach ($lookup as $old) {
                if ($old->ID === $existing->ID) {
                    continue;
                }

                if ($old->hasExtension(Versioned::class)) {
                    $versionedOnOld = $old->getExtensionInstance(Versioned::class);
                    if ($versionedOnOld instanceof Versioned) {
                        foreach ($versionedOnOld->getVersionedStages() as $stage) {
                            if (!is_string($stage)) {
                                continue;
                            }

                            $old->deleteFromStage($stage);
                        }
                    }
                }

                $old->delete();
            }

            $blueprint = new FixtureBlueprint($name);
            $created = $blueprint->createObject($identifier, $fixtureData, $this->fixtures);
            $latest = $created->toMap();

            unset($latest['ID']);

            $existing->update($latest);
            $existing->write();

            $created->delete();

            $this->rememberFixtureIdentifier($name, $identifier, (int) $existing->ID);

            $obj = $existing;
            $obj->flushCache();
        } else {
            try {
                $obj = parent::createObject($name, $identifier, $fixtureData);
            } catch (InvalidArgumentException $e) {
                $this->failedFixtures[] = [
                    'class' => $name,
                    'id' => $identifier,
                    'data' => $fixtureData,
                ];

                DB::alteration_message(sprintf('Exception: %s', $e->getMessage()), 'error');

                DB::alteration_message(
                    sprintf('Failed to create %s (%s), queueing for later', $identifier, $name),
                    'error'
                );

                return null;
            }
        }

        if ($obj->hasExtension(Versioned::class)) {
            if (Populate::config()->get('enable_publish_recursive')) {
                $obj->publishRecursive();
            } else {
                $obj->publishSingle();
            }

            $obj->flushCache();
        }

        return $obj;
    }

    /**
     * @param bool $recurse Marker for whether we are recursing - should be false when calling from outside this method
     * @throws Exception
     */
    public function processFailedFixtures(bool $recurse = false): void
    {
        if ($this->failedFixtures === []) {
            DB::alteration_message('No failed fixtures to process', 'created');

            return;
        }

        DB::alteration_message('');
        DB::alteration_message('');
        DB::alteration_message(sprintf('Processing %s failed fixtures', count($this->failedFixtures)), 'created');

        $failed = $this->failedFixtures;

        // Reset $this->failedFixtures so that continual failures can be re-attempted
        $this->failedFixtures = [];

        foreach ($failed as $fixture) {
            // createObject returns null if the object failed to create
            // This also re-populates $this->failedFixtures so we can re-compare
            $obj = $this->createObject($fixture['class'], $fixture['id'], $fixture['data']);

            if ($obj === null) {
                DB::alteration_message(
                    sprintf('Further attempt to create %s (%s) still failed', $fixture['id'], $fixture['class']),
                    'error'
                );
            }
        }

        if (count($this->failedFixtures) > 0 && count($failed) > count($this->failedFixtures)) {
            // We made some progress because there are less failed fixtures now than there were before, so run again
            $this->processFailedFixtures(true);
        }

        // Our final run gets here - either we made no progress on object creation, or there were some fixtures with
        // broken or circular relations that can't be resolved - list these at the end.
        if (!$recurse && count($this->failedFixtures) > 0) {
            $message = sprintf("Some fixtures (%d) couldn't be created:", count($this->failedFixtures));
            DB::alteration_message("");
            DB::alteration_message("");
            DB::alteration_message($message, "error");

            foreach ($this->failedFixtures as $fixture) {
                DB::alteration_message(sprintf('%s (%s)', $fixture['id'], $fixture['class']));
            }
        }
    }

    /**
     * Store a fixture identifier mapping on {@link FixtureFactory::$fixtures} in a type-safe way.
     */
    private function rememberFixtureIdentifier(string $class, string $identifier, int $fixtureId): void
    {
        $fixtures = $this->fixtures;
        if (!is_array($fixtures)) {
            $fixtures = [];
        }

        $bucket = [];
        if (isset($fixtures[$class]) && is_array($fixtures[$class])) {
            $bucket = $fixtures[$class];
        }

        $bucket[$identifier] = $fixtureId;
        $fixtures[$class] = $bucket;
        $this->fixtures = $fixtures;
    }

    /**
     * @param array<string, mixed> $data
     * @return File|bool The created (or updated) File object, or true if the file already existed
     * @throws Exception If anything is missing and the file can't be processed
     */
    private function populateFile(array $data): File|bool
    {
        if (!isset($data['Filename'], $data['PopulateFileFrom'])) {
            throw new Exception(sprintf(
                'When passing "PopulateFileFrom", you must also pass "Filename" with the path that you want to ' .
                'file to be stored at (e.g. assets/test.jpg)',
            ));
        }

        $populateFileFrom = $data['PopulateFileFrom'];
        $filenameConfig = $data['Filename'];

        if (!is_string($populateFileFrom) || !is_string($filenameConfig)) {
            throw new InvalidArgumentException('PopulateFileFrom and Filename must be strings');
        }

        $fixtureFilePath = BASE_PATH . '/' . $populateFileFrom;
        $filenameWithoutAssets = str_replace('assets/', '', $filenameConfig);

        // Find the existing object (if one exists)
        $existingObj = File::find($filenameWithoutAssets);

        if ($existingObj !== null && $existingObj->exists()) {
            $file = $existingObj;

            // If the file hashes match, and the file already exists, we don't need to update anything.
            $hash = $existingObj->File->getHash();
            if (!is_string($hash)) {
                throw new Exception('Could not determine hash for existing file');
            }

            $fixtureContents = file_get_contents($fixtureFilePath);
            if (!is_string($fixtureContents)) {
                throw new Exception(sprintf('Could not read fixture file at %s', $fixtureFilePath));
            }

            if (hash_equals($hash, sha1($fixtureContents))) {
                return true;
            }
        } else {
            // Create instance of file data object based on the extension of the fixture file
            $fileClass = File::get_class_for_file_extension(File::get_file_extension($fixtureFilePath));
            $file = Injector::inst()->create($fileClass);
            if (!$file instanceof File) {
                throw new Exception(sprintf('Could not create file instance for class %s', $fileClass));
            }
        }

        $fileFolder = dirname($filenameWithoutAssets);
        $filename = basename($filenameWithoutAssets);
        $folder = null;

        // Create a folder if the YML configuration indicates that the file should be created within a folder
        if ($fileFolder !== '.') {
            $folder = Folder::find_or_make($fileFolder);
            if ($folder === null) {
                throw new Exception(sprintf('Could not create or resolve folder %s', $fileFolder));
            }

            $fileFolder = $folder->getFilename();
        }

        // We could just use $data['Filename'], but we need to allow for filsystem abstraction
        $filePath = File::join_paths($fileFolder, $filename);

        $fileCfg = [
            // if there's a filename conflict we've got new content so overwrite it.
            'conflict' => AssetStore::CONFLICT_OVERWRITE,
            'visibility' => AssetStore::VISIBILITY_PUBLIC,
        ];

        // Set any other attributes that the file may need (e.g. Title)
        foreach ($data as $k => $v) {
            if (in_array($k, ['PopulateFileFrom', 'Filename'], true)) {
                continue;
            }

            $file->$k = $v;
        }

        $file->setFromString(file_get_contents($fixtureFilePath) ?: '', $filePath, null, null, $fileCfg);
        // Setting ParentID needs to come after setFromString() as (at least sometimes) setFromString() resets the
        // file Parent back to the "Uploads" folder
        $file->ParentID = $folder instanceof Folder ? $folder->ID : 0;
        $file->write();
        $file->publishRecursive();

        return $file;
    }
}
