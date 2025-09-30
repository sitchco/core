<?php

use Sitchco\Tests\Fakes\ModuleTester\ModuleTester;

return [
    'modules' => [
        ModuleTester::class => [
            'featureOne' => true,
            'featureTwo' => true,
            'featureThree' => false,
        ],
    ],
];
