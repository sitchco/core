<?php

namespace Sitchco\Repository;

use Sitchco\Model\Post;

/**
 * class PostRepository
 * @package Sitchco\Repository
 */
class PostRepository extends RepositoryBase
{
    protected string $model_class = Post::class;
}