<?php

$file = 'd:\Programs\C-DATA\app\Filament\Resources\ExternalCalResultResource.php';
$content = file($file);

$startLine = -1;
$endLine = -1;

// Find start of the block
foreach ($content as $i => $line) {
    if (strpos($line, '->afterStateUpdated(function (Set $set, ?string $state) {') !== false) {
        $startLine = $i;
        break;
    }
}

if ($startLine === -1) {
    die("Start line not found.\n");
}

// Find end of the block - looking for strict closing indentation
// The block ends around line 142 with '                                }),'
for ($i = $startLine; $i < count($content); $i++) {
    // We look for the closing brace and parenthesis with significant indentation
    if (trim($content[$i]) === '}),') {
        // Double check indentation depth if multiple exist, but here searching forward from start should work
        // The indentation is likely 32 spaces.
        if (strlen($content[$i]) - strlen(ltrim($content[$i])) >= 32) {
             $endLine = $i;
             break;
        }
    }
}

if ($endLine === -1) {
    die("End line not found.\n");
}

echo "Replacing lines " . ($startLine + 1) . " to " . ($endLine + 1) . "\n";

// Construct new content
$newEncodedBlock = <<<PHP
                                ->afterStateUpdated(function (Set \$set, ?string \$state) {
                                    self::updateInstrumentDetails(\$set, \$state);
                                })
                                ->afterStateHydrated(function (Set \$set, ?string \$state) {
                                    self::updateInstrumentDetails(\$set, \$state);
                                }),

PHP;

// Splice array
array_splice($content, $startLine, ($endLine - $startLine + 1), $newEncodedBlock);

// Write back
file_put_contents($file, implode("", $content));

echo "File patched successfully.\n";
