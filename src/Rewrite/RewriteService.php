<?php

declare(strict_types=1);

namespace Sitchco\Rewrite;

/**
 * class RewriteService
 * @package Sitchco\Rewrite
 */
class RewriteService
{
    protected array $rewriteRules = [];

    public function register(string $path, array $query = []): RewriteService
    {
        $this->rewriteRules[] = new QueryRewrite($path, $query);
        return $this;
    }

    public function registerAll(array $rules): RewriteService
    {
        foreach ($rules as $rule) {
            if (!isset($rule['path'], $rule['query'])) {
                throw new \InvalidArgumentException("Each rule must have 'path' and 'query' keys.");
            }
            $this->register($rule['path'], $rule['query']);
        }
        return $this;
    }

    public function execute(): RewriteService
    {
        add_action('init', function () {
            foreach ($this->rewriteRules as $rule) {
                $rule->addRewriteRule();
            }
        });

        add_filter('query_vars', function ($query_vars) {
            foreach ($this->rewriteRules as $rule) {
                $query_vars = $rule->setQueryVars($query_vars);
            }
            return $query_vars;
        });
        flush_rewrite_rules();
        return $this;
    }
}
