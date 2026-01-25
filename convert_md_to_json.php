<?php
// convert_md_to_json.php

// Helper function to cleaner
function cleanText($text) {
    return trim($text);
}

// Map filenames to nice category names and file output names
$fileMap = [
    'akaid_sorulari.md' => ['title' => 'Akaid Soruları', 'category' => 'Akaid', 'out' => 'akaid.json'],
    'kelam_sorulari.md' => ['title' => 'Kelam Soruları', 'category' => 'Kelam', 'out' => 'kelam.json'],
    'hadis_sorulari.md' => ['title' => 'Hadis Soruları', 'category' => 'Hadis', 'out' => 'hadis.json'],
    'islam_tarihi1_sorulari.md' => ['title' => 'İslam Tarihi I Soruları', 'category' => 'İslam Tarihi I', 'out' => 'islam_tarihi_1.json'],
    'islam_tarihi2_sorulari.md' => ['title' => 'İslam Tarihi II Soruları', 'category' => 'İslam Tarihi II', 'out' => 'islam_tarihi_2.json'],
    'tefsir_sorulari.md' => ['title' => 'Tefsir Soruları', 'category' => 'Tefsir', 'out' => 'tefsir.json'],
    'fikih_sorulari.md' => ['title' => 'Fıkıh Soruları', 'category' => 'Fıkıh', 'out' => 'fikih.json'],
    'din_psikolojisi_sorulari.md' => ['title' => 'Din Psikolojisi Soruları', 'category' => 'Din Psikolojisi', 'out' => 'din_psikolojisi.json'],
    'din_sosyolojisi_sorulari.md' => ['title' => 'Din Sosyolojisi Soruları', 'category' => 'Din Sosyolojisi', 'out' => 'din_sosyolojisi.json'],
    'din_egitimi_sorulari.md' => ['title' => 'Din Eğitimi Soruları', 'category' => 'Din Eğitimi', 'out' => 'din_egitimi.json'],
    'temel_esaslar_sorulari.md' => ['title' => 'Temel Esaslar Soruları', 'category' => 'Temel Esaslar', 'out' => 'temel_esaslar.json'],
    'arapca_sorulari.md' => ['title' => 'Arapça Soruları', 'category' => 'Arapça', 'out' => 'arapca.json'],
];

$sourceDir = __DIR__ . '/Sorular/İslamiİlimler';
$targetDir = __DIR__ . '/Sorular/İslami İlimler'; // With space for display

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

echo "Starting conversion...\n";

foreach ($fileMap as $filename => $info) {
    $filePath = $sourceDir . '/' . $filename;
    
    if (!file_exists($filePath)) {
        echo "Skipping $filename (not found)\n";
        continue;
    }

    echo "Processing $filename...\n";
    
    $content = file_get_contents($filePath);
    
    // Normalize newlines
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // Split by questions (### Soru X)
    // Regex to match "### Soru" followed by anything until next "### Soru" or end
    $parts = preg_split('/### Soru \d+/', $content);
    
    // Remove the first part which is usually header/intro
    array_shift($parts);
    
    $questions = [];
    $idCounter = 1;

    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;

        // Extract answer
        $answer = '';
        if (preg_match('/\*\*Cevap:\*\*\s*([A-E])/', $part, $matches)) {
            $answer = $matches[1];
        }
        
        // Remove the answer line and separator from content
        $part = preg_replace('/(\n)?\*\*Cevap:\*\*\s*[A-E](\n)?/', '', $part);
        $part = preg_replace('/(---\s*)+$/', '', $part);
        
        // Extract options
        $optionsArr = [];
        $optionKeys = ['A', 'B', 'C', 'D', 'E'];
        
        // Iterate through options to find where they start
        $lines = explode("\n", $part);
        $questionTextLines = [];
        $foundOptions = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Check if line starts with A), B), etc.
            if (preg_match('/^([A-E])\)\s+(.*)/', $line, $matches)) {
                $foundOptions = true;
                $key = $matches[1];
                $val = $matches[2];
                $optionsArr[$key] = $val;
            } else {
                if (!$foundOptions) {
                    $questionTextLines[] = $line;
                }
            }
        }
        
        $questionText = implode(' ', $questionTextLines);
        
        // Construct Question Object
        $qObj = [
            'id' => $idCounter++,
            'question' => $questionText,
            'options' => $optionsArr,
            'correct_answer' => $answer,
            'explanation' => '',
            'difficulty' => 1,
            'points' => 1
        ];
        
        $questions[] = $qObj;
    }
    
    // Create final JSON structure
    $jsonOutput = [
        'category' => $info['category'],
        'title' => $info['title'],
        'questions' => $questions
    ];
    
    // Write JSON
    $jsonPath = $targetDir . '/' . $info['out'];
    file_put_contents($jsonPath, json_encode($jsonOutput, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "Converted " . count($questions) . " questions to $jsonPath\n";
}

echo "Done.\n";
?>
