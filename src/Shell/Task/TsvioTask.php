<?php
namespace Tsvio\Shell\Task;

use Cake\Cache\Cache;
use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Core\ConventionsTrait;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

/**
 * Base class for Bake Tasks.
 *
 */
class TsvioTask extends Shell
{
    use ConventionsTrait;

    public $path;

    /**
     * The db connection being used for baking
     *
     * @var string
     */
    public $connection = null;

    /**
     * Disable caching and enable debug for baking.
     * This forces the most current database schema to be used.
     *
     * @return void
     */
    public function startup()
    {
        Configure::write('debug', true);
        Cache::disable();
    }

    /**
     * Initialize hook.
     *
     * Populates the connection property, which is useful for tasks of tasks.
     *
     * @return void
     */
    public function initialize()
    {
        if (empty($this->connection) && !empty($this->params['connection'])) {
            $this->connection = $this->params['connection'];
        }

        $this->path = ROOT."/config/Fixtures";
        if (! is_dir($this->path)){ mkdir($this->path); }
    }

    /**
     * Base execute method parses some parameters and sets some properties on the bake tasks.
     * call when overriding execute()
     *
     * @return void
     */
    public function main()
    {
        if (isset($this->params['connection'])) {
            $this->connection = $this->params['connection'];
        }
    }

    /**
     * Outputs the a list of possible models or controllers from database
     *
     * @return array
     */
    public function listAll()
    {
        if (!empty($this->_tables)) {
            return $this->_tables;
        }

        $this->_modelNames = [];
        $this->_tables = $this->_getAllTables();
        foreach ($this->_tables as $table) {
            $this->_modelNames[] = $this->_camelize($table);
        }
        return $this->_tables;
    }

    /**
     * Get an Array of all the tables in the supplied connection
     * will halt the script if no tables are found.
     *
     * @return array Array of tables in the database.
     * @throws \InvalidArgumentException When connection class
     *   does not have a schemaCollection method.
     */
    protected function _getAllTables()
    {
        $db = ConnectionManager::get($this->connection);
        if (!method_exists($db, 'schemaCollection')) {
            $this->err(
                'Connections need to implement schemaCollection() to be used with bake.'
            );
            return $this->_stop();
        }
        $schema = $db->schemaCollection();
        $tables = $schema->listTables();
        $tables = array_filter($tables, function($v){ return $v !== 'phinxlog'; });
        if (empty($tables)) {
            $this->err('Your database does not have any tables.');
            return $this->_stop();
        }
        sort($tables);
        return $tables;
    }

    /**
     * Get the table name for the model being baked.
     *
     * Uses the `table` option if it is set.
     *
     * @param string $name Table name
     * @return string
     */
    public function getTable($name)
    {
        if (isset($this->params['table'])) {
            return $this->params['table'];
        }
        return Inflector::underscore($name);
    }

    /**
     * Get a model object for a class name.
     *
     * @param string $className Name of class you want model to be.
     * @param string $table Table name
     * @return \Cake\ORM\Table Table instance
     */
    public function getTableObject($className, $table)
    {
        if (TableRegistry::exists($className)) {
            return TableRegistry::get($className);
        }
        return TableRegistry::get($className, [
            'name' => $className,
            'table' => $table,
            'connection' => ConnectionManager::get($this->connection)
        ]);
    }

    /**
     * Get the display field from the model or parameters
     *
     * @param \Cake\ORM\Table $model The model to introspect.
     * @return string
     */
    public function getDisplayField($model)
    {
        if (!empty($this->params['display-field'])) {
            return $this->params['display-field'];
        }
        return $model->displayField();
    }

    /**
     * Get the primary key field from the model or parameters
     *
     * @param \Cake\ORM\Table $model The model to introspect.
     * @return array The columns in the primary key
     */
    public function getPrimaryKey($model)
    {
        if (!empty($this->params['primary-key'])) {
            $fields = explode(',', $this->params['primary-key']);
            return array_values(array_filter(array_map('trim', $fields)));
        }
        return (array)$model->primaryKey();
    }

    /**
     * Get the fields from a model.
     *
     * Uses the fields and no-fields options.
     *
     * @param \Cake\ORM\Table $model The model to introspect.
     * @return array The columns to make accessible
     */
    public function getFields($model)
    {
        if (!empty($this->params['no-fields'])) {
            return [];
        }
        if (!empty($this->params['fields'])) {
            $fields = explode(',', $this->params['fields']);
            return array_values(array_filter(array_map('trim', $fields)));
        }
        $schema = $model->schema();
        $columns = $schema->columns();
        $primary = $this->getPrimaryKey($model);
        $exclude = array_merge($primary, ['created', 'modified', 'updated']);

        $associations = $model->associations();
        foreach ($associations->keys() as $assocName) {
            $columns[] = $associations->get($assocName)->property();
        }
        return array_values(array_diff($columns, $exclude));
    }
}
