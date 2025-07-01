<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap\Validator;

/**
 * Validation result data object
 * 
 * Holds comprehensive validation results with errors, warnings, and performance metrics
 */
class ValidationResult
{
    /**
     * @var bool
     */
    private bool $valid = true;

    /**
     * @var array
     */
    private array $errors = [];

    /**
     * @var array
     */
    private array $warnings = [];

    /**
     * @var array
     */
    private array $info = [];

    /**
     * @var array
     */
    private array $performance = [];

    /**
     * @var array
     */
    private array $seoMetrics = [];
    
    /**
     * @var array
     */
    private array $metadata = [];

    /**
     * Add error
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function addError(string $message, array $context = []): void
    {
        $this->valid = false;
        $this->errors[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => time()
        ];
    }

    /**
     * Add warning
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function addWarning(string $message, array $context = []): void
    {
        $this->warnings[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => time()
        ];
    }

    /**
     * Add info
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function addInfo(string $message, array $context = []): void
    {
        $this->info[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => time()
        ];
    }

    /**
     * Set performance metric
     *
     * @param string $metric
     * @param mixed $value
     * @return void
     */
    public function setPerformanceMetric(string $metric, $value): void
    {
        $this->performance[$metric] = $value;
    }

    /**
     * Set SEO metric
     *
     * @param string $metric
     * @param mixed $value
     * @return void
     */
    public function setSeoMetric(string $metric, $value): void
    {
        $this->seoMetrics[$metric] = $value;
    }
    
    /**
     * Add metadata
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function addMetadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
    }
    
    /**
     * Get metadata
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Is valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get warnings
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get info messages
     *
     * @return array
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Get performance metrics
     *
     * @return array
     */
    public function getPerformance(): array
    {
        return $this->performance;
    }
    
    /**
     * Get performance metrics (alias for getPerformance)
     *
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        return $this->getPerformance();
    }

    /**
     * Get SEO metrics
     *
     * @return array
     */
    public function getSeoMetrics(): array
    {
        return $this->seoMetrics;
    }
    
    /**
     * Get overall score
     *
     * @return float
     */
    public function getScore(): float
    {
        $performanceScore = $this->calculatePerformanceScore();
        $seoScore = $this->calculateSeoScore();
        
        // Calculate weighted average
        return ($performanceScore * 0.4 + $seoScore * 0.6);
    }

    /**
     * Get summary
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'valid' => $this->valid,
            'errors_count' => count($this->errors),
            'warnings_count' => count($this->warnings),
            'info_count' => count($this->info),
            'performance_score' => $this->calculatePerformanceScore(),
            'seo_score' => $this->calculateSeoScore()
        ];
    }

    /**
     * Calculate performance score
     *
     * @return float
     */
    private function calculatePerformanceScore(): float
    {
        $score = 100.0;
        
        // Reduce score based on file size
        if (isset($this->performance['file_size_mb'])) {
            $sizeMb = $this->performance['file_size_mb'];
            if ($sizeMb > 10) {
                $score -= min(30, ($sizeMb - 10) * 2);
            }
        }
        
        // Reduce score based on URL count
        if (isset($this->performance['url_count'])) {
            $urlCount = $this->performance['url_count'];
            if ($urlCount > 30000) {
                $score -= min(20, ($urlCount - 30000) / 1000);
            }
        }
        
        // Reduce score for errors and warnings
        $score -= count($this->errors) * 10;
        $score -= count($this->warnings) * 2;
        
        return max(0, $score);
    }

    /**
     * Calculate SEO score
     *
     * @return float
     */
    private function calculateSeoScore(): float
    {
        $score = 100.0;
        
        // Check for SEO best practices
        if (!isset($this->seoMetrics['has_lastmod'])) {
            $score -= 10;
        }
        
        if (!isset($this->seoMetrics['has_priority'])) {
            $score -= 5;
        }
        
        if (!isset($this->seoMetrics['has_changefreq'])) {
            $score -= 5;
        }
        
        if (isset($this->seoMetrics['broken_urls_percent'])) {
            $score -= $this->seoMetrics['broken_urls_percent'] * 2;
        }
        
        return max(0, $score);
    }

    /**
     * To array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'summary' => $this->getSummary(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'info' => $this->info,
            'performance' => $this->performance,
            'seo_metrics' => $this->seoMetrics,
            'metadata' => $this->metadata
        ];
    }
}
