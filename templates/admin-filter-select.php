<?php
/**
 * Expected
 * @var array $filter
 */

$id = 'column-filter-' . $filter['id']; ?>
<label for="<?= $id ?>" class="screen-reader-text">
    <?= $filter['options'][0]['label'] ?>
</label>
<select name="<?= $filter['id'] ?>" id="<?= $id ?>" class="postform">
    <?php foreach ($filter['options'] as $option): ?>
        <option value="<?= $option['value'] ?>"<?php selected($option['selected']); ?>>
            <?= $option['label'] ?>
        </option>
    <?php endforeach; ?>
</select>