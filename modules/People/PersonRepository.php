<?php

namespace Sitchco\Modules\People;

use Sitchco\Repository\PostRepository;

class PersonRepository extends PostRepository
{
    protected string $model_class = PersonPost::class;
}
