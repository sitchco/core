<?php
/**
 * Expected
 * @var array $filter
 */

$id = 'column-filter-' . $filter['id']; ?>
<label for="<?= esc_attr($id) ?>" class="screen-reader-text">
    <?= esc_html($filter['options'][0]['label']) ?>
</label>
<select name="<?= esc_attr($filter['id']) ?>" id="<?= esc_attr($id) ?>" class="postform">
    <?php foreach ($filter['options'] as $option): ?>
        <option value="<?= esc_attr($option['value']) ?>"<?php selected($option['selected']); ?>>
            <?= esc_html($option['label']) ?>
        </option>
    <?php endforeach; ?>
</select>