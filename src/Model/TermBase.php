<?php

namespace Sitchco\Model;

use Timber\Term;
use \WP_Term;

/**
 * class TermBase
 * @package Sitchco\Model
 */
class TermBase extends Term
{
    protected array $_fields = [];

    protected ?WP_Term $wp_object;

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