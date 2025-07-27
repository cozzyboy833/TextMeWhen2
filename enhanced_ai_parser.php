<?php
// enhanced_ai_parser.php - Auto-generated with smart corrections
// Generated on: 2025-07-28 00:26:06
// Based on 4 automatic corrections

class EnhancedAIParser {
    private $gameEndPatterns;
    private $gameStartPatterns;
    private $entityPatterns;
    
    public function __construct() {
        $this->gameEndPatterns = array (
);
        $this->gameStartPatterns = array (
  0 => 
  array (
    'team' => 'yankees',
    'keywords' => 
    array (
      0 => 'start',
      1 => 'begin',
      2 => 'kick off',
      3 => 'first pitch',
      4 => 'opening',
    ),
    'confidence' => 0.9,
  ),
  1 => 
  array (
    'team' => 'yankees',
    'keywords' => 
    array (
      0 => 'start',
      1 => 'begin',
      2 => 'kick off',
      3 => 'first pitch',
      4 => 'opening',
    ),
    'confidence' => 0.9,
  ),
);
        $this->entityPatterns = array (
  0 => 
  array (
    'text' => 'message me when the yankees game starts tomorrow on 7/25',
    'type' => 'sports_game_start',
    'entity' => 'Yankees',
    'condition' => 'game_starts',
  ),
  1 => 
  array (
    'text' => 'message me at the time when the yankees game starts tomorrow, 7/25',
    'type' => 'sports_game_start',
    'entity' => 'Yankees',
    'condition' => 'game_starts',
  ),
  2 => 
  array (
    'text' => 'message me  when the stock market opens and say "make that money you dirty dirty boy"',
    'type' => 'stock_price',
    'entity' => 'STOCK',
    'condition' => 'price_above',
  ),
  3 => 
  array (
    'text' => 'text me when it hits 83 degrees tomorrow',
    'type' => 'weather',
    'entity' => 'temperature',
    'condition' => 'temperature_reaches',
  ),
);
    }
    
    public function parseReminder($text) {
        $originalText = $text;
        $text = strtolower($text);
        
        $result = [
            'event_type' => 'generic',
            'entity' => 'unknown',
            'condition' => 'event_occurs',
            'confidence' => 0.5,
            'target_time' => null,
            'keywords' => explode(' ', $text),
            'reasoning' => 'Default classification'
        ];
        
        // PRIORITY 1: Enhanced Sports Game Detection (fixes your Yankees issue!)
        foreach ($this->gameEndPatterns as $pattern) {
            if (stripos($text, $pattern['team']) !== false) {
                foreach ($pattern['keywords'] as $keyword) {
                    if (stripos($text, $keyword) !== false) {
                        $result['event_type'] = 'sports_game_end';
                        $result['entity'] = ucfirst($pattern['team']);
                        $result['condition'] = 'game_over';
                        $result['confidence'] = $pattern['confidence'];
                        $result['reasoning'] = "Game END detected: {$pattern['team']} + {$keyword}";
                        return $result;
                    }
                }
            }
        }
        
        foreach ($this->gameStartPatterns as $pattern) {
            if (stripos($text, $pattern['team']) !== false) {
                foreach ($pattern['keywords'] as $keyword) {
                    if (stripos($text, $keyword) !== false) {
                        $result['event_type'] = 'sports_game_start';
                        $result['entity'] = ucfirst($pattern['team']);
                        $result['condition'] = 'game_starts';
                        $result['confidence'] = $pattern['confidence'];
                        $result['reasoning'] = "Game START detected: {$pattern['team']} + {$keyword}";
                        return $result;
                    }
                }
            }
        }
        
        // PRIORITY 2: Time-based parsing (should already work well)
        if (preg_match('/\bin (\d+) (minute|minutes|hour|hours?)\b/i', $text, $matches)) {
            $amount = intval($matches[1]);
            $unit = strtolower($matches[2]);
            $minutes = strpos($unit, 'hour') === 0 ? $amount * 60 : $amount;
            
            $result['event_type'] = 'time_relative';
            $result['entity'] = 'timer';
            $result['target_time'] = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
            $result['confidence'] = 0.95;
            $result['reasoning'] = "Relative time: {$amount} {$unit}";
            return $result;
        }
        
        if (preg_match('/when it is (\d{1,2}):?(\d{2})?/i', $text, $matches)) {
            $targetHour = intval($matches[1]);
            $targetMinute = isset($matches[2]) ? intval($matches[2]) : 0;
            $targetTime = date('Y-m-d') . ' ' . sprintf('%02d:%02d:00', $targetHour, $targetMinute);
            
            if ($targetTime <= date('Y-m-d H:i:s')) {
                $targetTime = date('Y-m-d', strtotime('+1 day')) . ' ' . sprintf('%02d:%02d:00', $targetHour, $targetMinute);
            }
            
            $result['event_type'] = 'time_absolute';
            $result['target_time'] = $targetTime;
            $result['confidence'] = 0.9;
            $result['reasoning'] = "Absolute time: {$targetHour}:{$targetMinute}";
            return $result;
        }
        
        // PRIORITY 3: Smart pattern matching from corrections
        foreach ($this->entityPatterns as $pattern) {
            $patternWords = explode(' ', $pattern['text']);
            $matchCount = 0;
            $totalWords = count($patternWords);
            
            foreach ($patternWords as $word) {
                if (strlen($word) > 2 && stripos($text, $word) !== false) {
                    $matchCount++;
                }
            }
            
            $matchRatio = $totalWords > 0 ? $matchCount / $totalWords : 0;
            
            // If enough words match, use this pattern
            if ($matchRatio >= 0.4 || $matchCount >= 2) {
                $result['event_type'] = $pattern['type'];
                $result['entity'] = $pattern['entity'];
                $result['condition'] = $pattern['condition'];
                $result['confidence'] = 0.7 + ($matchRatio * 0.2);
                $result['reasoning'] = "Pattern match: {$matchCount}/{$totalWords} words from training";
                return $result;
            }
        }
        
        return $result;
    }
}
?>