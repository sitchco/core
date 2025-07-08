<?php

use Sitchco\Tests\Support\ModuleTester;

return [
    'modules' => [
        ModuleTester::class => [
            'featureOne' => true,
            'featureTwo' => true,
            'featureThree' => false,
        ],
    ],
];
