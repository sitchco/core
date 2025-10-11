<?php
/**
 *Expected:
 * @var array    $report
 * @var \Sitchco\Support\DateTime $date
 */
?>
<style>
    .activity-report {
    }

    .activity-report table {
    }

    .activity-report h1 {
        margin-bottom: 30px;
    }

    .activity-report h2 {
        margin: -20px 0 30px;
        color: #5C5C5C
    }

    .activity-report .min-width {
        width: 1%;
        white-space: nowrap
    }

    .activity-report .activity-summary-col.hide span:first-child {
        display: block;
    }

    .activity-report .activity-summary-col.hide span {
        display: none;
    }

    .activity-report .activity-summary-col span {
        display: block;
    }

    .activity-report .activity-summary-col span.remove {
        display: none;
    }

    .activity-report button.toggle {
        cursor: pointer;
        background: #2271b1;
        border: none;
        outline: none;
        border-radius: 4px;
        color: white;
        width: 20px;
        padding: 3px 0 4px;
        line-height: 1;
        transition: 250ms opacity;
    }

    .activity-report button.toggle:hover {
        opacity: 0.8;
    }

    .activity-report button.toggle .hide {
        display: none;
    }
</style>
<div class="wrap activity-report">
    <h1>Stream Summary</h1>
    <?php if ($date): ?>
        <h2>From: <?= $date->format('m/d/Y g:i a') ?></h2>
    <?php endif; ?>
    <div style="margin-bottom: 20px;">
        <label for="filter-include">Include Only</label>
        <input type="text" id="filter-include" name="filter-include" class="filter-input" data-type="include" placeholder="eg: talent, video">
        <label style="margin-left: 10px;" for="filter-out">Exclude All</label>
        <input type="text" id="filter-out" name="filter-out" class="filter-input" data-type="exclude" placeholder="eg: event">
    </div>

    <table class="wp-list-table widefat striped table-view-list" style="width: 100%;">
        <thead>
        <tr>
            <th class="min-width">Type</th>
            <th class="min-width">Actions</th>
            <th>Summary</th>
            <th class="min-width">Collapse</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($report as $type => $item): ?>
            <tr class="activity-row">
                <td class="min-width"><?= $type ?></td>
                <td class="min-width"><?= implode(',', $item['actions']) ?></td>
                <td class="activity-summary-col"><?= implode(
                    '',
                    array_map(function ($summary) {
                        return '<span>' . $summary . '</span>';
                    }, $item['summary']),
                ) ?></td>
                <td class="min-width">
                    <button class="toggle">
                        <span class="minus">-</span>
                        <span class="plus hide">+</span>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
    jQuery(function ($) {
        function onToggle() {
            var $this = $(this);
            $this.find('span').toggleClass('hide');
            $this.parents('.activity-row').find('.activity-summary-col').toggleClass('hide');
        }

        var $filterInputs = $('.filter-input');

        function onFilterBlur() {
            var values = {};
            $filterInputs.each(function () {
                var $this = $(this);
                values[$this.data('type')] = $this.val().split(',').map(function (e) {
                    return e.trim().toLowerCase();
                }).filter(Boolean);
            });
            if (values.include.length || values.exclude.length) {
                $('.activity-row span').each(function () {
                    var $line = $(this);
                    var lineText = $line.text().toLowerCase();
                    var show = true;
                    if (values.include.length) {
                        show = false;
                        values.include.forEach(function (val) {
                            if (lineText.indexOf(val) > -1) {
                                show = true;
                            }
                        });
                    }
                    values.exclude.forEach(function (val) {
                        if (lineText.indexOf(val) > -1) {
                            show = false;
                        }
                    });
                    $line.toggleClass('remove', !show);
                });
            } else {
                $('.activity-row span').removeClass('remove');
            }
        }

        $('.activity-report .toggle').on('click', onToggle);
        $filterInputs.on('blur', onFilterBlur);
    });
</script>