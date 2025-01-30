<?php

namespace Sitchco\Model;

use Sitchco\Utils\Acf;
use Sitchco\Utils\Method;
use Sitchco\Utils\Str;
use Timber\Post;
use Timber\Timber;
use \WP_Post;

/**
 * class PostBase
 * @package Sitchco\Model
 * @property string $post_title
 *
 */
class PostBase extends Post
{
    const POST_TYPE = '';

    private array $_local_meta_reference = [];
    private array $_local_terms_reference = [];

    public function __get($field)
    {
        $value = parent::__get($field);
        $this->_local_meta_reference[$field] =& $this->$field;
        return $value;
    }

    public function __set($name, $value)
    {
        $this->__get($name);
        $this->$name = $value;
    }

    public function terms($query_args = [], $options = []): array
    {
        $post_merge = false;
        if ($options['merge'] ?? false) {
            $options['merge'] = false;
            $post_merge = true;
        }
        $terms_by_taxonomy = parent::terms($query_args, $options);
        foreach ($terms_by_taxonomy as $taxonomy => $terms) {
            $this->_local_terms_reference[$taxonomy] =& $terms;
        }
        if ($post_merge) {
            return array_merge(...array_values($terms_by_taxonomy));
        }

        return $terms_by_taxonomy;
    }

    public function wp_object(): ?\WP_Post
    {
        // Account for PostBase::create() scenario
        if (empty($this->wp_object)) {
            return $this->wp_object = new \WP_Post((object)['ID' => null, 'post_type' => static::POST_TYPE]);
        }
        return parent::wp_object();
    }

    public function getLocalMetaReference(): array
    {
        return $this->_local_meta_reference;
    }

    public function getLocalTermsReference(): array
    {
        return $this->_local_terms_reference;
    }

    public function refresh($fetch = false): void
    {
        $this->_wp_object = $this->_permalink = null;
        $this->_local_meta_reference = $this->_terms = [];
        Acf::clearPostStore($this->ID);
        if ($fetch) {
            $this->wp_object();
            $this->fields();
            // TODO: still need to flesh this out
//            $this->allTermIdsByTaxonomy();
            $this->link();
        }
    }

    public static function create(): PostBase
    {
        return new static();
    }
}