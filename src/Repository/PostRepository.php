<?php

namespace Sitchco\Repository;

//use Illuminate\Support\Collection;
use InvalidArgumentException;
use Sitchco\Model\PostBase;
use Sitchco\Repository\Support\Repository;

/**
 * class PostRepository
 * @package Sitchco\Repository
 */
class PostRepository implements Repository
{
    protected string $model_class = PostBase::class;
//    protected bool $exclude_current_singular_post = true;
//    protected string $collection_class = Collection::class;

    public function find($query) {}
    public function findAll() {}
    public function findById($id) {}
    public function findOne($query) {}

    public function add($object): true|\WP_Error|int
    {
        /** @var PostBase $object */
        $post_arr = get_object_vars($object->wp_object());
        $post_id = $object->ID ? wp_update_post($post_arr, true) : wp_insert_post($post_arr, true);

        // update custom fields
        if (!empty($fields = $object->getLocalMetaReference())) {
            foreach ($fields as $key => $value) {
                if ($key === 'thumbnail_id') {
                    set_post_thumbnail($post_id, $value);
                } else {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }

        $object->ID = $object->id = $post_id;

        if (!empty($local_terms = $object->getLocalTermsReference())) {
            foreach ($local_terms as $taxonomy => $term_ids) {
                wp_set_object_terms($object->ID, $term_ids, $taxonomy);
            }
        }

        $object->refresh(true);
        return true;
    }

    public function remove($object): bool
    {
        $this->checkBoundModelType($object);
        $result = wp_delete_post($object->ID);
        return !empty($result);
    }

    protected function checkBoundModelType(PostBase $post): void
    {
        if (!is_a($post, $this->model_class)) {
            throw new InvalidArgumentException('Model Class is not an instance of :' . $this->model_class);
        }
    }

    // TODO: clean the below up!

//    public function findById($id)
//    {
//        if (empty($id)) {
//            return null;
//        }
//        return $this->findOne(['p' => (int) $id]);
//    }
//
//    public function findOne(array $query)
//    {
//        $query['posts_per_page'] = 1;
//        $posts = $this->find($query);
//        return $posts->first() ?: null;
//    }

//    public function findOneBySlug($name)
//    {
//        return $this->findOne(compact('name'));
//    }
//
//    public function findOneByAuthor($author)
//    {
//        if (is_object($author)) $author = $author->ID;
//        return $this->findOne(compact('author'));
//    }
//
//    public function findAllByAuthor($author)
//    {
//        if (is_object($author)) $author = $author->ID;
//        return $this->find(compact('author'));
//    }
//
//    public function findAll($any_status = false)
//    {
//        $status = $any_status ? 'any' : 'publish';
//        return $this->find(['posts_per_page' => -1, 'post_status' => $status]);
//    }

//    public function findAllDrafts()
//    {
//        return $this->find(['posts_per_page' => -1, 'post_status' => 'draft']);
//    }
//
//    public function find(array $query): Collection
//    {
//        $model_class = $this->model_class;
//        $query = $this->maybeExcludeCurrentSingularPost($query);
//        $posts = array_filter($model_class::getPosts($query));
//        $collection_class = $this->collection_class;
//        return new $collection_class($posts);
//    }

//    public function findWithIds(array $post_ids, int $count = 10)
//    {
//        if (empty($post_ids)) {
//            return [];
//        }
//        return $this->find([
//            'posts_per_page' => $count,
//            'post__in' => $post_ids,
//            'orderby' => 'post__in'
//        ]);
//    }

//    /**
//     * @param array $term_ids
//     * @param int $count
//     * @param string $taxonomy
//     * @param array $excluded_post_ids
//     * @return array
//     */
//    public function findWithTermIds(array $term_ids, string $taxonomy = 'category', $count = 10, array $excluded_post_ids = [])
//    {
//        if (empty($term_ids)) {
//            return [];
//        }
//        return $this->find([
//            'posts_per_page' => $count,
//            'post__not_in' => $excluded_post_ids,
//            'tax_query' => [
//                [
//                    'taxonomy' => $taxonomy,
//                    'terms' => $term_ids,
//                    'field' => 'term_id',
//                    'compare' => 'IN'
//                ]
//            ]
//        ]);
//    }

//    protected function attachThumbnail(PostBase $post): void
//    {
//        $thumbnail_id = $post->thumbnail_id();
//        if ($thumbnail_id) {
//            set_post_thumbnail($post->ID, $thumbnail_id);
//        } else {
//            delete_post_thumbnail($post->ID);
//        }
//    }

//    protected function addFields(PostBase $post): void
//    {
//        foreach ($post->fields(false) as $key => $value) {
//            $field_ids = $this->getFieldIds($post);
//            $value = $this->prepareFieldValue($key, $value);
//            if (isset($field_ids[$key])) {
//                update_field($field_ids[$key], $value, $post->ID);
//            } else {
//                update_post_meta($post->ID, $key, $value);
//            }
//        }
//    }

//    protected function getFieldIds(PostBase $post)
//    {
//        if (empty($this->field_ids)) {
//            foreach (acf_get_field_groups(['post_type' => $post::POST_TYPE]) as $group) {
//                foreach (acf_get_fields($group) as $field) {
//                    $this->field_ids[$field['name']] = $field['key'];
//                }
//            }
//        }
//        return $this->field_ids;
//    }

//    protected function prepareFieldValue($key, $value)
//    {
//        if (method_exists($this, "prepare_$key")) {
//            $value = $this->{"prepare_$key"}($value);
//        }
//        if ($value instanceof Field) {
//            $value = $value->getValue();
//        }
//        return $value;
//    }

//    protected function maybeExcludeCurrentSingularPost($query)
//    {
//        global $wp_query;
//        if (!$wp_query) {
//            return $query;
//        }
//        $model_class = $this->model_class;
//        $post_obj = $wp_query->get_queried_object();
//        if (
//            $this->exclude_current_singular_post &&
//            $wp_query->is_singular &&
//            $post_obj && $post_obj->post_type == $model_class::POST_TYPE
//        ) {
//            if (empty($query['post__not_in'])) {
//                $query['post__not_in'] = [];
//            }
//            if (!is_array($query['post__not_in'])) {
//                $query['post__not_in'] = [$query['post__not_in']];
//            }
//            $query['post__not_in'][] = get_the_ID();
//        }
//        return $query;
//    }

}