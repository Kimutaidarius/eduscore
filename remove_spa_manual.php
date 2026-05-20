<?php
/**
 * Quick fix for classes.php missing endif
 */

$file = 'classes.php';

if (!file_exists($file)) {
    die("File not found: $file\n");
}

$content = file_get_contents($file);

// Find the pattern and fix it
$pattern = '/(<div class="classes-table-container">.*?<\\/div>\\s*\\n\\s*<\\/div>)/s';
$replacement = '$1<?php endif; ?>' . "\n    </div>";

// More precise fix - add endif before the closing div
$content = str_replace(
    '            </div>
        
    </div>',
    '            </div>
        <?php endif; ?>
    </div>',
    $content
);

// Also fix the add class button missing closing tag
$content = str_replace(
    '                    <button class="add-class-btn" id="addClassBtn">
                        <i class="fas fa-plus"></i>
                        Add Class
                    </button>
                
            </div>',
    '                    <button class="add-class-btn" id="addClassBtn">
                        <i class="fas fa-plus"></i>
                        Add Class
                    </button>
                <?php endif; ?>
            </div>',
    $content
);

// Write the fixed content
file_put_contents($file, $content);

echo "✅ Fixed classes.php - added missing endif tags\n";
echo "Please refresh the page now.\n";