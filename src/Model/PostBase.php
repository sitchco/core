<?php

namespace Sitchco\Model;

use Sitchco\Utils\Acf;
use Sitchco\Utils\Method;
use Timber\Post;
use \WP_Post;

/**
 * class PostBase
 * @package Sitchco\Model
 *
 * @property string $post_title
 */
class PostBase extends Post
{
    const POST_TYPE = '';

    private ?WP_Post $_wp_object;
    private array $_fields = [];
    private array $_terms = [];

    protected array $_date_field_names = [];
    protected string $_default_date_format = 'Y-m-d h:i:s';

    public function __get($field)
    {
        if ($method_name = Method::getMethodName($this, $field)) {
            if (!isset($this->_fields[$field])) {
                $value = $this->{$method_name}();
                $this->_fields[$field] = $value;
            }
            return $this->_fields[$field];
        }

        if (isset($this->_fields[$field]) && !empty($this->_fields[$field])) {
            return $this->_fields[$field];
        }

//        $wp_object = $this->wp_object();
//        if (property_exists($wp_object, $field)) return $wp_object->{$field}; // might not need this
//        if ($terms = $this->terms($this->getTaxonomyFromProperty($field))) return $terms;
        return parent::__get($field);
    }

    public function __set($name, $value)
    {
        if ($method_name = Method::getMethodName($this, $name, 'set')) {
            $this->{$method_name}($value);
            // should automatically invoke any custom get methods here
            $value = $this->$name;
        }

//         TODO: get this integrated!
//        if ($taxonomy = $this->getTaxonomyFromProperty($name)) {
////            $this->_terms[$taxonomy] = array_map(function($value) use ($taxonomy) {
////                return is_string($value) ?
////                    get_term_by('slug', $value, $taxonomy) :
////                    get_term($value, $taxonomy);
////            }, (array) $value);
////            return '';
////        }
            $this->_fields[$name] = $value;
    }

    public function wp_object(): ?\WP_Post
    {
        if (empty($this->_wp_object)) {
            $this->_wp_object = !is_null(parent::wp_object()) ? parent::wp_object() : new \WP_Post((object)['ID' => null, 'post_type' => static::POST_TYPE]);
        }
        return $this->_wp_object;
    }

    public function field($field)
    {
        if (empty($this->_fields[$field])) {
             $field = get_field($field, $this->wp_object()->ID);
            if (in_array($field, $this->_date_field_names)) {
                $field = $field ? new \DateTime($field, $this->_default_date_format) : null;
            }
            $this->_fields[$field] = $field;
        }

        return $this->_fields[$field];
    }

    public function fields($fetch = true): array
    {
        if ($fetch) {
             $fields = get_fields($this->wp_object());
            foreach ((array) $fields as $key => $value) {
                $this->$key;
            }
        }

        return $this->_fields;
    }

    public function refresh($fetch = false): void
    {
        $this->_wp_object = $this->_permalink = null;
        $this->_fields = $this->_terms = [];
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

//    public function allTermIdsByTaxonomy(): array
//    {
//        $taxonomies = get_object_taxonomies($this->type(), 'objects');
//        foreach ($taxonomies as $name => &$term_ids) {
//            $term_ids = array_map(function(\WP_Term $term) {
//                return $term->term_id;
//            }, $this->terms($name));
//        }
//        return $taxonomies;
//    }

//    private function getTaxonomyFromProperty($name): false|string
//    {
//        if (taxonomy_exists($name)) return $name;
//        $singular_name = Str::singular($name);
//        return taxonomy_exists($singular_name) ? $singular_name : false;
//    }
}