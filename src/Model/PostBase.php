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
 * TODO: work in some better error handling around checking if POST_TYPE is correct, is a better place for this in the repository
 */
class PostBase extends Post
{
    // TODO: replace with __type (PostType object!)
    const POST_TYPE = '';

    protected ?WP_Post $_wp_object;
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

        if ($taxonomy = $this->getTaxonomyFromProperty($name)) {
            $this->_terms[$taxonomy] = array_map(function($value) use ($taxonomy) {
                return is_string($value) ?
                    Timber::get_term_by('slug', $value, $taxonomy) :
                    Timber::get_term($value);
            }, (array) $value);
        }

        $this->_fields[$name] = $value;
    }

    public function wp_object(): ?\WP_Post
    {
        // Account for PostBase::create() scenario
        if (empty($this->_wp_object) && !empty($this->ID)) {
            $this->_wp_object = get_post($this->ID);
        } else if (empty($this->_wp_object) && empty($this->ID)) {
            $this->_wp_object = !is_null(parent::wp_object()) ? parent::wp_object() : new \WP_Post((object)['ID' => null, 'post_type' => static::POST_TYPE]);
        }
        return $this->_wp_object;
    }

    public function field($field)
    {
        if (empty($this->_fields[$field])) {
             $field = get_field($field, $this->wp_object()->ID);
            if (in_array($field, $this->_date_field_names)) {
                // TODO: work in a try/catch here
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

    public function termsByTaxonomy(): array
    {
        // TODO: add if (empty($this->_terms)) check
        return $this->_terms;
    }

    private function getTaxonomyFromProperty($name): false|string
    {
        if (taxonomy_exists($name)) return $name;
        $singular_name = Str::singular($name);
        return taxonomy_exists($singular_name) ? $singular_name : false;
    }
}