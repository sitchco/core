<?php

namespace Sitchco\Framework\Config;

abstract class PhpConfigLoader extends ConfigLoader
{

    protected function loadFile(string $file): array
    {
        return include_once($file);
    }
}