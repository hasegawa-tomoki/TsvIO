<?php
namespace Tsvio\Shell\Task;

use Cake\Console\Shell;
use Cake\Core\Configure;

class ImportTask extends TsvioTask
{
    public function main()
    {
        parent::main();

        $tables = [];
        if (count($this->args) == 1 and $this->args[0] == 'all'){
            $listAll = $this->listAll();
            foreach ($listAll as $table){
                $fileName = sprintf("%s/%s.txt", $this->path, $table);
                if (is_file($fileName)){ $tables[] = $table; }
            }
        } else {
            $tables = $this->args;
        }

        foreach ($tables as $table){
            $this->_import($table);
        }
    }

    public function _import($name){
        $table = $this->getTable($name);
        $model = $this->getTableObject($name, $table);

        $this->out('<info>Importing table:</info>');
        $this->out($table, 0);

        $fp = fopen(sprintf("%s/%s.txt", $this->path, $table), 'r');

        // Disable foreign key checks
        $model->connection()->execute('set FOREIGN_KEY_CHECKS = 0;');
        // Begin transaction
        $model->connection()->begin();

        // Truncate table
        $model->deleteAll([]);

        // Read header
        $fields = explode("\t", trim(fgets($fp)));

        // Write records
        $idx = 0;
        while ($line = fgets($fp)){
            $cols = explode("\t", trim($line));
            $record = $model->newEntity();
            foreach ($fields as $field){ $record->$field = array_shift($cols); }
            $model->save($record);
            if ($idx++ % 10 == 0){ $this->out('.', 0); }
        }

        // Enable foreign key checks
        $model->connection()->execute('set FOREIGN_KEY_CHECKS = 1;');
        // Commit transaction
        $model->connection()->commit();

        fclose($fp);

        $this->out(' done');

        $this->out();
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();

        $parser->description('Import table records from tsv file. Put one / more table names or all ending of command line.');

        return $parser;
    }
}
