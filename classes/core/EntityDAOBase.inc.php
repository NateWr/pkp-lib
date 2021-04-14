<?php
/**
 * @file classes/core/EntityDAOBase.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class core
 *
 * @brief A base class for entity DAOs
 */

namespace PKP\core;

use DataObject;
use Illuminate\Database\Capsule\Manager as Capsule;
use Services;
use stdClass;

abstract class EntityDAOBase
{
    /** @var string One of the SCHEMA_... constants */
    public const SCHEMA = '';

    /** @var string The name of the primary table for this object */
    public const TABLE = '';

    /** @var string The name of the settings table for this object */
    public const SETTINGS_TABLE = '';

    /** @var string The column name for the object id in primary and settings tables */
    public const PRIMARY_KEY_COLUMN = '';

    /** @var array Maps schema properties for the primary table to their column names */
    public const PRIMARY_TABLE_COLUMNS = [];

    /**
     * Get one object of the entity type by its ID
     */
    protected static function _get(int $id): DataObject
    {
        $row = Capsule::table(static::TABLE)
            ->where(static::PRIMARY_KEY_COLUMN, $id)
            ->first();
        return static::fromRow($row);
    }

    /**
     * Return a DataObject for one row in a query result
     */
    protected static function _fromRow(stdClass $row): DataObject
    {
        $schema = Services::get('schema')->get(static::SCHEMA);

        $object = static::newDataObject();

        foreach (static::PRIMARY_TABLE_COLUMNS as $propName => $column) {
            if (property_exists($row, $column)) {
                $object->setData(
                    $propName,
                    static::convertFromDb($row->{$column}, $schema->properties->{$propName}->type)
                );
            }
        }

        $rows = Capsule::table(static::SETTINGS_TABLE)
            ->where(static::PRIMARY_KEY_COLUMN, '=', $row->{static::PRIMARY_KEY_COLUMN})
            ->get();

        $rows->each(function ($row) use ($object, $schema) {
            if (!empty($schema->properties->{$row->setting_name})) {
                $object->setData(
                    $row->setting_name,
                    static::convertFromDB(
                        $row->setting_value,
                        $schema->properties->{$row->setting_name}->type
                    ),
                    empty($row->locale) ? null : $row->locale
                );
            }
        });

        return $object;
    }

    /**
     * Insert an object into the database
     *
     * @return integer
     */
    protected static function _insert(DataObject $object): int
    {
        $schemaService = Services::get('schema');
        $schema = $schemaService->get(static::SCHEMA);
        $sanitizedProps = $schemaService->sanitize(static::SCHEMA, $object->_data);

        $primaryDbProps = static::getPrimaryDbProps($object);

        if (empty($primaryDbProps)) {
            throw new \Exception('Tried to insert ' . get_class($object) . ' without any properties for the ' . static::TABLE . ' table.');
        }

        $object->setId(Capsule::table(static::TABLE)->insertGetId($primaryDbProps));

        // Add additional properties to settings table if they exist
        if (count($sanitizedProps) !== count($primaryDbProps)) {
            foreach ($schema->properties as $propName => $propSchema) {
                if (!isset($sanitizedProps[$propName]) || array_key_exists($propName, static::PRIMARY_TABLE_COLUMNS)) {
                    continue;
                }
                if (!empty($propSchema->multilingual)) {
                    foreach ($sanitizedProps[$propName] as $localeKey => $localeValue) {
                        Capsule::table(static::SETTINGS_TABLE)->insert([
                            static::PRIMARY_KEY_COLUMN => $object->getId(),
                            'locale' => $localeKey,
                            'setting_name' => $propName,
                            'setting_value' => static::convertToDB($localeValue, $schema->properties->{$propName}->type),
                        ]);
                    }
                } else {
                    Capsule::table(static::SETTINGS_TABLE)->insert([
                        static::PRIMARY_KEY_COLUMN => $object->getId(),
                        'setting_name' => $propName,
                        'setting_value' => static::convertToDB($sanitizedProps[$propName], $schema->properties->{$propName}->type),
                    ]);
                }
            }
        }

        return $object->getId();
    }

    /**
     * Update an object in the database
     *
     */
    protected static function _update(DataObject $object)
    {
        $schemaService = Services::get('schema');
        $schema = $schemaService->get(static::SCHEMA);
        $sanitizedProps = $schemaService->sanitize(static::SCHEMA, $object->_data);

        $primaryDbProps = static::getPrimaryDbProps($object);

        Capsule::table(static::TABLE)
            ->where(static::PRIMARY_KEY_COLUMN, '=', $object->getId())
            ->update($primaryDbProps);

        $deleteSettings = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (array_key_exists($propName, static::PRIMARY_TABLE_COLUMNS)) {
                continue;
            } elseif (!isset($sanitizedProps[$propName])) {
                $deleteSettings[] = $propName;
                continue;
            }
            if (!empty($propSchema->multilingual)) {
                foreach ($sanitizedProps[$propName] as $localeKey => $localeValue) {
                    // Delete rows with a null value
                    if (is_null($localeValue)) {
                        Capsule::table(static::SETTINGS_TABLE)
                            ->where(static::PRIMARY_KEY_COLUMN, '=', $object->getId())
                            ->where('setting_name', '=', $propName)
                            ->where('locale', '=', $localeKey)
                            ->delete();
                    } else {
                        Capsule::table(static::SETTINGS_TABLE)
                            ->updateOrInsert(
                                [
                                    static::PRIMARY_KEY_COLUMN => $object->getId(),
                                    'locale' => $localeKey,
                                    'setting_name' => $propName,
                                ],
                                [
                                    'setting_value' => static::convertToDB($localeValue, $schema->properties->{$propName}->type),
                                ]
                            );
                    }
                }
            } else {
                Capsule::table(static::SETTINGS_TABLE)
                    ->updateOrInsert(
                        [
                            static::PRIMARY_KEY_COLUMN => $object->getId(),
                            'locale' => '',
                            'setting_name' => $propName,
                        ],
                        [
                            'setting_value' => static::convertToDB($sanitizedProps[$propName], $schema->properties->{$propName}->type),
                        ]
                    );
            }
        }

        if (count($deleteSettings)) {
            Capsule::table(static::SETTINGS_TABLE)
                ->where(static::PRIMARY_KEY_COLUMN, '=', $object->getId())
                ->whereIn('setting_name', $deleteSettings)
                ->delete();
        }
    }

    /**
     * Delete an object from the database
     *
     */
    protected static function _delete(DataObject $object): bool
    {
        return static::deleteById($object->getId());
    }

    /**
     * Delete an object from the database by its id
     *
     * @param integer $id
     */
    protected static function _deleteById(int $id): bool
    {
        Capsule::table(static::TABLE)
            ->where(static::PRIMARY_KEY_COLUMN, '=', $id)
            ->delete();
        Capsule::table(static::SETTINGS_TABLE)
            ->where(static::PRIMARY_KEY_COLUMN, '=', $id)
            ->delete();
    }

    /**
     * Convert a stored type from the database
     *
     * @param mixed $value Value from DB
     * @param string $type Type from DB
     */
    protected static function convertFromDB($value, string $type)
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'number':
                return (float) $value;
            case 'object':
            case 'array':
                $decodedValue = json_decode($value, true);
                // FIXME: pkp/pkp-lib#6250 Remove after 3.3.x upgrade code is removed (see also pkp/pkp-lib#5772)
                if (!is_null($decodedValue)) {
                    return $decodedValue;
                } else {
                    return unserialize($value);
                }
                // no break
            case 'date':
                if ($value !== null) {
                    return strtotime($value);
                }
                break;
            case 'string':
            default:
                // Nothing required.
                break;
        }
        return $value;
    }

    /**
     * Convert a PHP variable into a string to be stored in the DB
     *
     * @param $value mixed
     * @param $type string
     *
     * @return string
     */
    protected static function convertToDB($value, &$type)
    {
        if ($type == null) {
            switch (gettype($value)) {
                case 'boolean':
                case 'bool':
                    $type = 'bool';
                    // no break
                case 'integer':
                case 'int':
                    $type = 'int';
                    // no break
                case 'double':
                case 'float':
                    $type = 'float';
                    // no break
                case 'array':
                case 'object':
                    $type = 'object';
                    // no break
                case 'string':
                default:
                    $type = 'string';
            }
        }

        switch ($type) {
            case 'object':
            case 'array':
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            case 'bool':
            case 'boolean':
                // Cast to boolean, ensuring that string
                // "false" evaluates to boolean false
                return ($value && $value !== 'false') ? 1 : 0;
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'number':
                return (float) $value;
            case 'date':
                if ($value !== null) {
                    if (!is_numeric($value)) {
                        $value = strtotime($value);
                    }
                    return strftime('%Y-%m-%d %H:%M:%S', $value);
                }
                break;
            case 'string':
            default:
                // do nothing.
        }

        return $value;
    }

    /**
     * A helper function to compile the key/value set for the primary table
     *
     * @param DataObject
     *
     * @return array
     */
    protected static function getPrimaryDbProps($object)
    {
        $schema = Services::get('schema')->get(static::schemaName);
        $sanitizedProps = Services::get('schema')->sanitize(static::schemaName, $object->_data);

        $primaryDbProps = [];
        foreach (static::PRIMARY_TABLE_COLUMNS as $propName => $columnName) {
            if ($propName !== 'id' && array_key_exists($propName, $sanitizedProps)) {
                $primaryDbProps[$columnName] = static::convertToDB($sanitizedProps[$propName], $schema->properties->{$propName}->type);
                // Convert empty string values for DATETIME columns into null values
                // because an empty string can not be saved to a DATETIME column
                if ($primaryDbProps[$columnName] === ''
                        && isset($schema->properties->{$propName}->validation)
                        && (
                            in_array('date_format:Y-m-d H:i:s', $schema->properties->{$propName}->validation)
                            || in_array('date_format:Y-m-d', $schema->properties->{$propName}->validation)
                        )
                ) {
                    $primaryDbProps[$columnName] = null;
                }
            }
        }

        return $primaryDbProps;
    }
}
