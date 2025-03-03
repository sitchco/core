<?php

namespace Sitchco\Integration;

use Sitchco\Framework\Core\Module;
use Sitchco\Support\DateTime;
use Sitchco\Utils\Template;

class Stream extends Module
{

    public function init(): void
    {
        add_filter('wp_stream_settings_option_fields', [$this, 'filterDefaultMax']);
        add_action('admin_menu', [$this, 'addOptionsPage'], 99);
    }

    public function filterDefaultMax($defaults): array
    {
        if (! empty($defaults['general']['fields'])) {
            foreach ($defaults['general']['fields'] as $index => $field) {
                if ($field['name'] === 'records_ttl') {
                    $defaults['general']['fields'][$index]['default'] = 90;
                }
            }
        }

        return $defaults;
    }

    public function addOptionsPage(): void
    {
        if (function_exists('wp_stream_get_instance')) {
            add_submenu_page(
                'wp_stream',
                'Stream Summary',
                'Summary',
                'manage_options',
                'wp_stream_summary',
                [$this, 'summaryPageContent']
            );
        }
    }

    public function summaryPageContent(): void
    {
        if (function_exists('wp_stream_get_instance')) {
            $start = $_GET['start'] ?? false;
            $date = $start;
            $args = ['records_per_page' => 1000];
            if ($start) {
                $args['date_after'] = $start;
                try {
                    $date = DateTime::createFromTimeString($start);
                } catch (\Exception $e) {
                    $date = false;
                }
            }
            $stream = wp_stream_get_instance();
            $records = $stream->db->get_records($args);
            $report = [];
            $records = array_filter($records, function ($record) {
                $record = (array)$record;
                if ($record['action'] === 'login') {
                    return false;
                }

                return true;
            });
            foreach ($records as $record) {
                $record = (array)$record;
                $type = $record['connector'] . '/' . $record['context'];
                if (empty($report[$type])) {
                    $report[$type] = [
                        'actions' => [],
                        'summary' => []
                    ];
                }
                $actions = array_unique(array_merge($report[$type]['actions'], [$record['action']]));
                $summary = array_unique(array_merge($report[$type]['summary'], [$record['summary']]));
                $report[$type]['actions'] = $actions;
                $report[$type]['summary'] = $summary;
            }
            echo Template::getTemplateScoped(SITCHCO_CORE_TEMPLATES_DIR . '/stream-activity-report.php', compact('report', 'date'));
        }
    }
}