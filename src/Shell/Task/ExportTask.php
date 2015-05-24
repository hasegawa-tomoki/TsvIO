<?php
namespace TsvIO\Shell\Task;

use Cake\Console\Shell;
use Cake\Core\Configure;

class ExportTask extends TsvioTask
{
    public function main()
    {
        parent::main();

        foreach ($this->args as $table){
            $this->_export($table);
        }
    }

    public function _export($name){
        $table = $this->getTable($name);
        $model = $this->getTableObject($name, $table);
        $fields = $this->getFields($model);
        $primaryKey = $this->getPrimaryKey($model)[0];

        $this->out('<info>Exporting table:</info>');
        $this->out($table);

        $fp = fopen(sprintf("%s/%s.txt", $this->path, $table), 'w');

        // Write header
        $cols = [];
        $cols[] = $primaryKey;
        foreach ($fields as $field){ $cols[] = $field; }
        fwrite($fp, implode("\t", $cols)."\n");

        // Write records
        $records = $model->find('all', array('order' => array($primaryKey)));
        foreach ($records as $record){
            $cols = [];
            $cols[] = $record->$primaryKey;
            foreach ($fields as $field){ $cols[] = $record->$field; }
            fwrite($fp, implode("\t", $cols)."\n");
        }
        fclose($fp);

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

        $parser->description('Export table records to tsv file. Put one or more table names ending of command line.');

        return $parser;
    }
}
