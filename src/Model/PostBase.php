<?php

namespace Sitchco\Model;

use Sitchco\Utils\Acf;
use Sitchco\Utils\Method;
use Sitchco\Utils\Str;
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
    // TODO: flesh out $_terms
    private array $_terms = [];

    protected array $_date_field_names = [];
    protected string $_default_date_format = 'Y-m-d h:i:s';

    public function wp_object(): ?\WP_Post
    {
        if (empty($this->_wp_object)) {
            $this->_wp_object = parent::wp_object();
        }
        return $this->_wp_object;
    }

    public function __get($field)
    {
        if ($method_name = Method::getMethodName($this, $field)) {
            if (!isset($this->_fields[$field])) {
                $value = $this->{$method_name}();
                $this->_fields[$field] = $value;
            }
            return $this->_fields[$field];
        }

//        $wp_object = $this->wp_object();
//        if (property_exists($wp_object, $field)) return $wp_object->{$field}; // might not need this
//        if ($terms = $this->terms($this->getTaxonomyFromProperty($field))) return $terms;
        return parent::__get($field);
    }

    public function __set($name, $value): void
    {
        if ($method_name = Method::getMethodName($this, $name, 'set')) {
            $value = $this->{$method_name}($value);
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

    public function field($selector)
    {
        if (empty($this->_fields[$selector])) {
             $field = get_field($selector, $this->wp_object()->ID);
            if (in_array($selector, $this->_date_field_names)) {
                $field = $field ? new \DateTime($field, $this->_default_date_format) : null;
            }
            $this->_fields[$selector] = $field;
        }

        return $this->_fields[$selector];
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

//    private function getTaxonomyFromProperty($name): false|string
//    {
//        if (taxonomy_exists($name)) return $name;
//        $singular_name = Str::singular($name);
//        return taxonomy_exists($singular_name) ? $singular_name : false;
//    }

    public function refresh($reload = false): void
    {
        $this->_wp_object = $this->_permalink = null;

        // TODO: still need to flesh out $_terms
        $this->_fields = $this->_terms = [];
        Acf::clearPostStore($this->wp_object()->ID);
        if ($reload) {
            $this->fields();
            // TODO: still need to flesh this out
//            $this->allTermIdsByTaxonomy();
            $this->link();
        }
    }

    /**
     * Used for Testing Purposes Only.
     * TODO: create another class in test dir, register separately for testing, then dump these methods in there
     */
    public function setSomeCustomValue(string $value): string
    {
        // TODO update this! $this->some_custom_value = ""
        return "Custom Setter: {$value}";
    }

    /**
     * Used for Testing Purposes Only.
     */
    public function getTestCustomValue(): string
    {
        return "Custom Getter: Test Custom Value";
    }
}