<?php

namespace Sitchco\Framework\Config;

abstract class JsonConfigLoader extends ConfigLoader
{
    protected function loadFile(string $file): array
    {
        return json_decode(file_get_contents($file), true);
    }
}