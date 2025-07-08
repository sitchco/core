<?php

namespace Sitchco\Repository;

use InvalidArgumentException;
use Sitchco\Collection;
use Sitchco\Model\PostBase;
use Sitchco\Support\Repository\Repository;
use Timber\Post;
use Timber\PostQuery;
use Timber\Timber;
use WP_Query;

/**
 * class RepositoryBase
 * @package Sitchco\Repository
 */
class RepositoryBase implements Repository
{
    protected string $model_class = PostBase::class;
    protected bool $exclude_current_singular_post = true;

    public function find(array $query = []): Collection
    {
        $model_class = $this->model_class;
        $query['post_type'] = $model_class::POST_TYPE;
        $query = $this->maybeExcludeCurrentSingularPost($query);

        return new Collection(new PostQuery(new WP_Query($query)));
    }

    public function findAll(array $query = []): Collection
    {
        $model_class = $this->model_class;
        $query['post_type'] = $model_class::POST_TYPE;
        $query['posts_per_page'] = -1;

        return new Collection(new PostQuery(new WP_Query($query)));
    }

    public function findById($id): ?Post
    {
        if (!$id) {
            return null;
        }

        return Timber::get_post($id);
    }

    public function findOne(array $query = []): ?PostBase
    {
        $query['posts_per_page'] = 1;
        $posts = $this->find($query);
        return $posts->first();
    }

    public function findOneBySlug($name): ?PostBase
    {
        if (!$name) {
            return null;
        }
        return $this->findOne(compact('name'));
    }

    public function findOneByAuthor($author): ?PostBase
    {
        if (!$author) {
            return null;
        }
        if (is_object($author)) {
            $author = $author->ID;
        }
        return $this->findOne(compact('author'));
    }

    public function findAllByAuthor($author): Collection
    {
        if (!$author) {
            return new Collection(new PostQuery(new WP_Query([])));
        }
        if (is_object($author)) {
            $author = $author->ID;
        }
        return $this->find(compact('author'));
    }

    public function findAllDrafts(): Collection
    {
        return $this->find(['posts_per_page' => -1, 'post_status' => 'draft']);
    }

    public function findWithIds(array $post_ids, int $count = 10): Collection
    {
        if (empty($post_ids)) {
            return new Collection(new PostQuery(new WP_Query([])));
        }
        return $this->find([
            'posts_per_page' => $count,
            'post__in' => $post_ids,
            'orderby' => 'post__in',
        ]);
    }

    public function findWithTermIds(
        array $term_ids,
        string $taxonomy = 'category',
        $count = 10,
        array $excluded_post_ids = []
    ): Collection {
        if (empty($term_ids)) {
            return new Collection(new PostQuery(new WP_Query([])));
        }
        return $this->find([
            'posts_per_page' => $count,
            'post__not_in' => $excluded_post_ids,
            'tax_query' => [
                [
                    'taxonomy' => $taxonomy,
                    'terms' => $term_ids,
                    'field' => 'term_id',
                    'compare' => 'IN',
                ],
            ],
        ]);
    }

    public function add($object): true|int
    {
        /** @var PostBase $object */
        $this->checkBoundModelType($object);
        $post_arr = get_object_vars($object->wp_object());
        $post_id = $object->ID ? wp_update_post($post_arr, true) : wp_insert_post($post_arr, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!empty(($fields = $object->getLocalMetaReference()))) {
            foreach ($fields as $key => $value) {
                if ($key === 'thumbnail_id') {
                    set_post_thumbnail($post_id, $value);
                } else {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }

        $object->ID = $object->id = $post_id;
        if (!empty(($local_taxonomies = $object->getLocalTaxonomies()))) {
            foreach ($local_taxonomies as $taxonomy) {
                $terms = $object->getMergedExistingAndLocalTerms($taxonomy);
                wp_set_object_terms($object->ID, array_column($terms, 'term_id'), $taxonomy);
            }
        }

        $object->refresh();
        return true;
    }

    public function remove($object): bool
    {
        $this->checkBoundModelType($object);
        $result = wp_delete_post($object->ID);
        return !empty($result);
    }

    protected function maybeExcludeCurrentSingularPost($query): array
    {
        global $wp_query;
        if (!$wp_query) {
            return $query;
        }
        $model_class = $this->model_class;
        $post_obj = $wp_query->get_queried_object();
        if (
            $this->exclude_current_singular_post &&
            $wp_query->is_singular &&
            $post_obj &&
            $post_obj->post_type == $model_class::POST_TYPE
        ) {
            if (empty($query['post__not_in'])) {
                $query['post__not_in'] = [];
            }
            if (!is_array($query['post__not_in'])) {
                $query['post__not_in'] = [$query['post__not_in']];
            }
            $query['post__not_in'][] = get_the_ID();
        }
        return $query;
    }

    protected function checkBoundModelType(PostBase $post): void
    {
        if (!is_a($post, $this->model_class)) {
            throw new InvalidArgumentException('Model Class is not an instance of :' . $this->model_class);
        }
    }
}
