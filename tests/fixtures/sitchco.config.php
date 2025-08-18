<?php

use Sitchco\Tests\Fakes\ModuleTester;

return [
    'modules' => [
        ModuleTester::class => [
            'featureOne' => true,
            'featureTwo' => true,
            'featureThree' => false,
        ],
    ],
];
