<?php
/**
 * Roulette Color Utility
 * 
 * Utility functions for roulette color mapping and validation.
 */

class RouletteColor {
    // Red numbers on a standard roulette wheel
    private static $redNumbers = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];
    
    /**
     * Get the color of a roulette number
     */
    public static function getColor($number) {
        $number = (int)$number;
        
        if ($number === 0) {
            return "green";
        } elseif (in_array($number, self::$redNumbers)) {
            return "red";
        } else {
            return "black";
        }
    }
    
    /**
     * Validate if a number is a valid roulette number
     */
    public static function isValidNumber($number) {
        $number = (int)$number;
        return $number >= 0 && $number <= 36;
    }
    
    /**
     * Get all numbers of a specific color
     */
    public static function getNumbersByColor($color) {
        switch (strtolower($color)) {
            case "red":
                return self::$redNumbers;
            case "green":
                return [0];
            case "black":
                $allNumbers = range(1, 36);
                return array_diff($allNumbers, self::$redNumbers);
            default:
                return [];
        }
    }
    
    /**
     * Get color statistics for an array of spins
     */
    public static function getColorStats($spins) {
        $stats = ["red" => 0, "black" => 0, "green" => 0];
        
        foreach ($spins as $number) {
            $color = self::getColor($number);
            $stats[$color]++;
        }
        
        return $stats;
    }
}
?>