<?php
// improved_ai_parser.php - Generated from simple_training_data.json
// Generated on: 2025-07-27 23:14:48

class ImprovedAIParser {
    private $patterns;
    
    public function __construct() {
        $this->patterns = array (
  0 => 
  array (
    'trigger' => 'yankees',
    'type' => 'sports_game',
    'entity' => 'Yankees',
    'confidence' => 0.85,
  ),
  1 => 
  array (
    'trigger' => 'when it is',
    'type' => 'time_absolute',
    'entity' => 'clock',
    'confidence' => 0.9,
  ),
  3 => 
  array (
    'trigger' => 'stock',
    'type' => 'stock_price',
    'entity' => 'market',
    'confidence' => 0.7,
  ),
  4 => 
  array (
    'trigger' => 'degree',
    'type' => 'weather',
    'entity' => 'temperature',
    'confidence' => 0.75,
  ),
  5 => 
  array (
    'trigger' => 'in',
    'type' => 'time_relative',
    'entity' => 'timer',
    'confidence' => 0.95,
  ),
);
    }
    
    public function parseReminder($text) {
        $text = strtolower($text);
        $result = [
            'event_type' => 'generic',
            'entity' => 'unknown',
            'condition' => 'event_occurs',
            'confidence' => 0.5,
            'target_time' => null,
            'keywords' => explode(' ', $text)
        ];
        
        // High-priority time parsing
        if (preg_match('/\bin (\d+) (minute|minutes|hour|hours?)\b/i', $text, $matches)) {
            $amount = intval($matches[1]);
            $unit = strtolower($matches[2]);
            $minutes = strpos($unit, 'hour') === 0 ? $amount * 60 : $amount;
            
            $result['event_type'] = 'time_relative';
            $result['entity'] = 'timer';
            $result['target_time'] = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
            $result['confidence'] = 0.95;
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
            return $result;
        }
        
        // Pattern-based parsing from training data
        foreach ($this->patterns as $pattern) {
            if (stripos($text, $pattern['trigger']) !== false) {
                $result['event_type'] = $pattern['type'];
                $result['entity'] = $pattern['entity'];
                $result['confidence'] = $pattern['confidence'];
                break;
            }
        }
        
        return $result;
    }
}
?>