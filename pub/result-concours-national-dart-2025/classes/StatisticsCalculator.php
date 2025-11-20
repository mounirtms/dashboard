<?php
/**
 * Statistics Calculator Class
 * Handles calculation of various statistics for the form data
 */

class StatisticsCalculator {
    
    /**
     * Calculate all statistics from processed answers
     */
    public function calculateStatistics($processedAnswers) {
        // Calculate statistics
        $totalEntries = count($processedAnswers);
        $wilayaStats = [];
        $sourceStats = [];
        $ratingStats = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        
        foreach ($processedAnswers as $answer) {
            // Count by wilaya
            $wilaya = $answer['wilaya'];
            if (!isset($wilayaStats[$wilaya])) {
                $wilayaStats[$wilaya] = 0;
            }
            $wilayaStats[$wilaya]++;
            
            // Count by source
            $source = $answer['source_concours'];
            if (!isset($sourceStats[$source])) {
                $sourceStats[$source] = 0;
            }
            $sourceStats[$source]++;
            
            // Count ratings
            $rating = $answer['rating'] > 0 ? $answer['rating'] : 0;
            if ($rating > 0) {
                $ratingStats[$rating]++;
            }
        }
        
        // Sort statistics
        arsort($wilayaStats);
        arsort($sourceStats);
        
        return [
            'totalEntries' => $totalEntries,
            'wilayaStats' => $wilayaStats,
            'sourceStats' => $sourceStats,
            'ratingStats' => $ratingStats
        ];
    }
    
    /**
     * Get top N items from statistics
     */
    public function getTopItems($stats, $limit = 5) {
        return array_slice($stats, 0, $limit, true);
    }
    
    /**
     * Calculate percentage for a specific value in statistics
     */
    public function calculatePercentage($value, $total) {
        return $total > 0 ? ($value / $total * 100) : 0;
    }
}