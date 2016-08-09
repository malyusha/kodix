<?php


namespace Kodix\Support\Debug;

use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;

class Dumper
{
    /**
     * Dumps a value
     *
     * @param $var
     * @return void
     */
    public function dump($var)
    {
        if(class_exists('\Symfony\Component\VarDumper\Dumper\CliDumper')) {
            $dumper = PHP_SAPI === 'sli' ? new CliDumper : new HtmlDumper;
            $dumper->dump((new VarCloner)->cloneVar($var));
        } else {
            var_dump($var);
        }
    }
}