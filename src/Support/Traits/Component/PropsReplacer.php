<?php


namespace Kodix\Support\Traits\Component;


trait PropsReplacer
{
    public function replaceProps($element)
    {
        foreach($element as $code => $value) {

            $newKey = preg_replace("/^PROPERTY_([\w\d_]+)_VALUE$/i", "$1", $code);

            if($newKey != $code) {
                $element[$newKey] = $value;
            }
        }

        return $element;
    }
}