<?php

namespace Sitchco\Modules\People;

use Sitchco\Repository\RepositoryBase;

class PersonRepository extends RepositoryBase
{
    protected string $model_class = PersonPost::class;
}
