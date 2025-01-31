<?php

namespace Sitchco\Model;

use Sitchco\Utils\Acf;
use Timber\Factory\PostFactory;
use Timber\Post;
use Timber\Term;
use Timber\Timber;

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
    private array $_local_add_terms_reference = [];
    private array $_local_remove_terms_reference = [];

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

    public function addTerm(\WP_Term|Term|string|int $term, string $taxonomy = null): static
    {
        $term = $this->normalizeTerm($term, $taxonomy);
        $this->_local_add_terms_reference[$term->taxonomy][$term->slug] = $term;
        unset($this->_local_remove_terms_reference[$term->taxonomy][$term->slug]);
        return $this;
    }

    public function removeTerm(\WP_Term|Term|string|int $term, string $taxonomy = null): static
    {
        $term = $this->normalizeTerm($term, $taxonomy);
        $this->_local_remove_terms_reference[$term->taxonomy][$term->slug] = $term;
        unset($this->_local_add_terms_reference[$term->taxonomy][$term->slug]);
        return $this;
    }

    public function getLocalTaxonomies(): array
    {
        return array_merge(array_keys($this->_local_add_terms_reference), array_keys($this->_local_remove_terms_reference));
    }

    /**
     * @param string $taxonomy
     * @return Term[]
     */
    public function getMergedExistingAndLocalTerms(string $taxonomy): array
    {
        $existing_terms = $this->terms(compact('taxonomy'));
        $existing_terms_by_slug = array_combine(array_column($existing_terms, 'slug'), $existing_terms);
        foreach ($this->_local_add_terms_reference[$taxonomy] ?? [] as $slug => $term) {
            $existing_terms_by_slug[$slug] = $term;
        }
        foreach ($this->_local_remove_terms_reference[$taxonomy] ?? [] as $slug => $term) {
            unset($existing_terms_by_slug[$slug]);
        }
        return array_values($existing_terms_by_slug);
    }

    /**
     * Normalizes term input to a Timber Term object
     *
     * @param \WP_Term|Term|string|int $term
     * @param string|null $taxonomy
     * @return Term|null
     */
    private function normalizeTerm(\WP_Term|Term|string|int $term, string $taxonomy = null): ?Term
    {
        if ($term instanceof Term) {
            return $term;
        }
        if (is_string($term)) {
            if (!$taxonomy) {
                throw new \InvalidArgumentException('Taxonomy is required when supplying a term slug');
            }
            return Timber::get_term_by('slug', $term, $taxonomy);
        }
        return Timber::get_term($term);
    }

    public function wp_object(): ?\WP_Post
    {
        if (empty($this->wp_object)) {
            return $this->wp_object = new \WP_Post((object)['ID' => null, 'post_type' => static::POST_TYPE]);
        }
        return parent::wp_object();
    }

    public function getLocalMetaReference(): array
    {
        return $this->_local_meta_reference;
    }

    public function refresh(): void
    {
        $this->wp_object = $this->_permalink = null;
        $this->_local_meta_reference = $this->_local_add_terms_reference = $this->_local_remove_terms_reference = [];
        Acf::clearPostStore($this->ID);
        $wp_post = get_post($this->ID);
        $data = \get_object_vars($wp_post);
        $this->import($data);
    }

    public static function create(): PostBase
    {
        return new static();
    }
}