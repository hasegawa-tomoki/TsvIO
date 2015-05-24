<?php
namespace Tsvio\Shell;

use Cake\Cache\Cache;
use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Core\ConventionsTrait;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;

class TsvioShell extends Shell
{
    use ConventionsTrait;

    /**
     * The connection being used.
     *
     * @var string
     */
    public $connection = 'default';

    /**
     * Assign $this->connection to the active task if a connection param is set.
     *
     * @return void
     */
    public function startup()
    {
        parent::startup();
        Configure::write('debug', true);
        Cache::disable();

        $task = $this->_camelize($this->command);

        if (isset($this->{$task}) && !in_array($task, ['Project'])) {
            if (isset($this->params['connection'])) {
                $this->{$task}->connection = $this->params['connection'];
            }
            $this->{$task}->connection = $this->connection;
        }
        if (isset($this->params['connection'])) {
            $this->connection = $this->params['connection'];
        }
    }

    /**
     * Override main() to handle action
     *
     * @return mixed
     */
    public function main()
    {
        $connections = ConnectionManager::configured();
        if (empty($connections)) {
            $this->out('Your database configuration was not found.');
            $this->out('Add your database connection information to config/app.php.');
            return false;
        }
        $this->out('The following commands can be used to import or export tsv file.', 2);
        $this->out('<info>Available tsvio commands:</info>', 2);
        $names = [];
        foreach ($this->tasks as $task) {
            list(, $name) = pluginSplit($task);
            $names[] = Inflector::underscore($name);
        }
        sort($names);
        foreach ($names as $name) {
            $this->out('- ' . $name);
        }
        $this->out('');
        return false;
    }

    /**
     * Locate the tasks bake will use.
     *
     * Scans the following paths for tasks that are subclasses of
     * Cake\Shell\Task\BakeTask:
     *
     * - Cake/Shell/Task/
     * - App/Shell/Task/
     * - Shell/Task for each loaded plugin
     *
     * @return void
     */
    public function loadTasks()
    {
        $tasks = [];

        $tasks = $this->_findTasks($tasks, APP, Configure::read('App.namespace'));
        foreach (Plugin::loaded() as $plugin) {
            $tasks = $this->_findTasks(
                $tasks,
                Plugin::classPath($plugin),
                $plugin,
                $plugin
            );
        }

        $this->tasks = array_values($tasks);
        parent::loadTasks();
    }

    /**
     * Append matching tasks in $path to the $tasks array.
     *
     * @param array $tasks The task list to modify and return.
     * @param string $path The base path to look in.
     * @param string $namespace The base namespace.
     * @param string|null $prefix The prefix to append.
     * @return array Updated tasks.
     */
    protected function _findTasks($tasks, $path, $namespace, $prefix = null)
    {
        $path .= 'Shell/Task';
        if (!is_dir($path)) {
            return $tasks;
        }
        $candidates = $this->_findClassFiles($path, $namespace);
        $classes = $this->_findTaskClasses($candidates);
        foreach ($classes as $class) {
            list(, $name) = namespaceSplit($class);
            $name = substr($name, 0, -4);
            $fullName = ($prefix ? $prefix . '.' : '') . $name;
            $tasks[$name] = $fullName;
        }
        return $tasks;
    }

    /**
     * Find task classes in a given path.
     *
     * @param string $path The path to scan.
     * @param string $namespace Namespace.
     * @return array An array of files that may contain bake tasks.
     */
    protected function _findClassFiles($path, $namespace)
    {
        $iterator = new \DirectoryIterator($path);
        $candidates = [];
        foreach ($iterator as $item) {
            if ($item->isDot() || $item->isDir()) {
                continue;
            }
            $name = $item->getBasename('.php');
            $candidates[] = $namespace . '\Shell\Task\\' . $name;
        }
        return $candidates;
    }

    /**
     * Find bake tasks in a given set of files.
     *
     * @param array $files The array of files.
     * @return array An array of matching classes.
     */
    protected function _findTaskClasses($files)
    {
        $classes = [];
        foreach ($files as $className) {
            if (!class_exists($className)) {
                continue;
            }
            $reflect = new \ReflectionClass($className);
            if (!$reflect->isInstantiable()) {
                continue;
            }
            if (!$reflect->isSubclassOf('Tsvio\Shell\Task\TsvioTask')) {
                continue;
            }
            $classes[] = $className;
        }
        return $classes;
    }

    /**
     * Quickly bake the MVC
     *
     * @param string|null $name Name.
     * @return void
     */
    public function all($name = null)
    {
        $this->out('Bake All');
        $this->hr();

        if (!empty($this->params['connection'])) {
            $this->connection = $this->params['connection'];
        }

        if (empty($name)) {
            $this->Model->connection = $this->connection;
            $this->out('Possible model names based on your database:');
            foreach ($this->Model->listAll() as $table) {
                $this->out('- ' . $table);
            }
            $this->out('Run <info>`cake bake all [name]`</info> to generate skeleton files.');
            return false;
        }

        foreach (['Model', 'Controller', 'Template'] as $task) {
            $this->{$task}->connection = $this->connection;
        }

        $name = $this->_camelize($name);

        $this->Model->main($name);
        $this->Controller->main($name);
        $this->Template->main($name);

        $this->out('<success>Bake All complete.</success>', 1, Shell::QUIET);
        return true;
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();

        /*
        $bakeThemes = [];
        foreach (Plugin::loaded() as $plugin) {
            $path = Plugin::classPath($plugin);
            if (is_dir($path . 'Template' . DS . 'Bake')) {
                $bakeThemes[] = $plugin;
            }
        }
        */

        $parser->description('Import or export database table.')
            ->addSubcommand('export', ['help' => 'Export records to a tsv file.',])
            ->addSubcommand('import', ['help' => 'Import records from a tsv file.',]);

        foreach ($this->_taskMap as $task => $config) {
            $taskParser = $this->{$task}->getOptionParser();
            $parser->addSubcommand(Inflector::underscore($task), [
                'help' => $taskParser->description(),
                'parser' => $taskParser
            ]);
        }

        return $parser;
    }
}
