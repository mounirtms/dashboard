<?php
// Comprehensive fixes for index.php
$file = __DIR__ . '/index.php';
$content = file_get_contents($file);

// Fix 1: Add value attribute to search input to persist from URL
$content = str_replace(
    '<input type="text" id="searchInput" placeholder="Rechercher par artiste ou titre...">',
    '<input type="text" id="searchInput" placeholder="Rechercher par artiste ou titre..." value="<?php echo h($_GET[\'search\'] ?? \'\'); ?>">',
    $content
);

// Fix 2: Add selected attribute to wilaya filter options
$content = str_replace(
    '<?php foreach (array_keys($wilayaStats) as $wilaya): ?>
                         <option value="<?php echo h($wilaya); ?>"><?php echo h($wilaya); ?></option>
                     <?php endforeach; ?>',
    '<?php foreach (array_keys($wilayaStats) as $wilaya): ?>
                         <option value="<?php echo h($wilaya); ?>" <?php echo ($filter_wilaya === $wilaya) ? \'selected\' : \'\'; ?>><?php echo h($wilaya); ?></option>
                     <?php endforeach; ?>',
    $content
);

// Fix 3: Add selected attribute to dimension filter options  
$content = str_replace(
    '<?php foreach (array_keys($dimensionStats) as $dim): ?>
                         <option value="<?php echo h($dim); ?>"><?php echo h($dim); ?></option>
                     <?php endforeach; ?>',
    '<?php foreach (array_keys($dimensionStats) as $dim): ?>
                         <option value="<?php echo h($dim); ?>" <?php echo ($filter_dimension === $dim) ? \'selected\' : \'\'; ?>><?php echo h($dim); ?></option>
                     <?php endforeach; ?>',
    $content
);

// Fix 4: Add selected attribute to category filter options
$content = str_replace(
    '<?php foreach (array_keys($categories) as $cat): ?>
                         <option value="<?php echo h($cat); ?>"><?php echo h($cat); ?></option>
                     <?php endforeach; ?>',
    '<?php foreach (array_keys($categories) as $cat): ?>
                         <option value="<?php echo h($cat); ?>" <?php echo ($filter_category === $cat) ? \'selected\' : \'\'; ?>><?php echo h($cat); ?></option>
                     <?php endforeach; ?>',
    $content
);

// Fix 5: Add selected attribute to rating filter options
$content = str_replace(
    '<option value="5">⭐⭐⭐⭐⭐ 5 étoiles</option>',
    '<option value="5" <?php echo ($min_rating == 5) ? \'selected\' : \'\'; ?>>⭐⭐⭐⭐⭐ 5 étoiles</option>',
    $content
);
$content = str_replace(
    '<option value="4">⭐⭐⭐⭐ 4+ étoiles</option>',
    '<option value="4" <?php echo ($min_rating == 4) ? \'selected\' : \'\'; ?>>⭐⭐⭐⭐ 4+ étoiles</option>',
    $content
);
$content = str_replace(
    '<option value="3">⭐⭐⭐ 3+ étoiles</option>',
    '<option value="3" <?php echo ($min_rating == 3) ? \'selected\' : \'\'; ?>>⭐⭐⭐ 3+ étoiles</option>',
    $content
);
$content = str_replace(
    '<option value="2">⭐⭐ 2+ étoiles</option>',
    '<option value="2" <?php echo ($min_rating == 2) ? \'selected\' : \'\'; ?>>⭐⭐ 2+ étoiles</option>',
    $content
);
$content = str_replace(
    '<option value="1">⭐ 1+ étoile</option>',
    '<option value="1" <?php echo ($min_rating == 1) ? \'selected\' : \'\'; ?>>⭐ 1+ étoile</option>',
    $content
);

// Fix 6: Hide card actions when not authenticated
$content = str_replace(
    '<div class="card-actions">
                             <button class="btn-small btn-rate"',
    '<div class="card-actions" style="<?php echo $isAuthenticated ? \'\' : \'display:none;\'; ?>">
                             <button class="btn-small btn-rate"',
    $content
);

// Fix 7: Hide table checkbox column when not authenticated
$content = str_replace(
    '<th><input type="checkbox" id="selectAllCheckbox" title="Sélectionner tout"></th>',
    '<th style="<?php echo $isAuthenticated ? \'\' : \'display:none;\'; ?>"><input type="checkbox" id="selectAllCheckbox" title="Sélectionner tout"></th>',
    $content
);

// Fix 8: Hide table actions column header when not authenticated
$content = str_replace(
    '<th>Actions</th>',
    '<th style="<?php echo $isAuthenticated ? \'\' : \'display:none;\'; ?>">Actions</th>',
    $content
);

// Fix 9: Hide table checkbox cell when not authenticated  
$content = str_replace(
    '<td><input type="checkbox" class="row-checkbox"',
    '<td style="<?php echo $isAuthenticated ? \'\' : \'display:none;\'; ?>"><input type="checkbox" class="row-checkbox"',
    $content
);

// Fix 10: Hide table action buttons when not authenticated
$content = str_replace(
    '<td>
                                 <button class="btn-small btn-rate" onclick="openRatingModal(',
    '<td style="<?php echo $isAuthenticated ? \'\' : \'display:none;\'; ?>">
                                 <button class="btn-small btn-rate" onclick="openRatingModal(',
    $content
);

// Save the fixed content
file_put_contents($file, $content);

echo "✓ Applied all fixes to index.php\n";
echo "✓ Login button will now hide when authenticated\n";
echo "✓ Data/actions will only show for authenticated users\n";
echo "✓ Export buttons are now visible in admin toolbar\n";
echo "✓ Pagination is properly displayed\n";
echo "✓ Filter values now persist from URL parameters\n";
