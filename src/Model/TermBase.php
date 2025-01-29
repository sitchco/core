<?php

namespace Sitchco\Model;

use Timber\Term;

/**
 * class TermBase
 * @package Sitchco\Model
 */
class TermBase extends Term
{
//    const TAXONOMY = null;
//    protected array $_fields = [];

    // TODO: leverage $_wp_object
//    private ?WP_Term $_wp_term;

//    public function taxonomy(): string
//    {
//        return static::TAXONOMY ?: $this->term()->taxonomy;
//    }

//    public function field($field)
//    {
//        if (empty($this->_fields[$field])) {
//            $this->_fields[$field] = get_field($field, $this->getIdForField());
//        }
//
//        return $this->_fields[$field];
//    }
//
//    public function fields(): array
//    {
//        $this->_fields = get_fields($this->getIdForField());
//        return $this->_fields;
//    }

//    /**
//     * @return WP_Term
//     */
//    public function term(): WP_Term
//    {
//        if (empty($this->_wp_term)) {
//            $this->_term = get_term($this->term_id, static::TAXONOMY);
//        }
//
//        return $this->_wp_term;
//    }

//    protected function getIdForField(): string
//    {
//        return $this->taxonomy() . '_' . $this->term()->term_id;
//    }
}