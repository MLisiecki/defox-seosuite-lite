<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\StructuredData\Renderer;

/**
 * JSON-LD renderer for structured data
 * 
 * This class is responsible for rendering structured data arrays into valid JSON-LD format.
 */
class JsonLd
{
    /**
     * Render structured data as JSON-LD script tag
     *
     * @param array $data Single structured data item or array of items
     * @return string
     */
    public function render(array $data): string
    {
        if (empty($data)) {
            return '';
        }
        
        // Check if we have a single item or multiple items
        $jsonData = $this->prepareData($data);
        
        // Encode to JSON with proper formatting
        $json = json_encode($jsonData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        if ($json === false) {
            return '';
        }
        
        // Wrap in script tag
        return sprintf(
            '<script type="application/ld+json">%s</script>',
            $json
        );
    }
    
    /**
     * Render multiple structured data items as separate JSON-LD script tags
     *
     * @param array $items Array of structured data items
     * @return string
     */
    public function renderMultiple(array $items): string
    {
        if (empty($items)) {
            return '';
        }
        
        $output = [];
        
        foreach ($items as $item) {
            if (is_array($item) && !empty($item)) {
                $rendered = $this->render($item);
                if ($rendered) {
                    $output[] = $rendered;
                }
            }
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Prepare data for JSON encoding
     *
     * @param array $data
     * @return array
     */
    private function prepareData(array $data): array
    {
        // If the array has numeric keys, it's a list of items
        if (isset($data[0])) {
            // Multiple items - check if we should use @graph
            if (count($data) > 1) {
                return [
                    '@context' => 'https://schema.org',
                    '@graph' => $data
                ];
            } else {
                // Single item in array - ensure @context is first
                $singleItem = $data[0];
                return $this->ensureContextFirst($singleItem);
            }
        }
        
        // Single item - ensure @context is first
        return $this->ensureContextFirst($data);
    }
    
    /**
     * Ensure @context is the first element in the array
     *
     * @param array $data
     * @return array
     */
    private function ensureContextFirst(array $data): array
    {
        // If @context doesn't exist, add it
        if (!isset($data['@context'])) {
            $data['@context'] = 'https://schema.org';
        }
        
        // Reorder array to put @context first
        $context = $data['@context'];
        unset($data['@context']);
        
        return ['@context' => $context] + $data;
    }
    
    /**
     * Validate JSON-LD structure
     *
     * @param array $data
     * @return bool
     */
    public function validate(array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        
        // Check for required @type field
        if (isset($data[0])) {
            // Multiple items
            foreach ($data as $item) {
                if (!isset($item['@type'])) {
                    return false;
                }
            }
        } else {
            // Single item
            if (!isset($data['@type'])) {
                return false;
            }
        }
        
        // Try to encode to JSON to check if it's valid
        $json = json_encode($data);
        
        return $json !== false;
    }
    
    /**
     * Minify JSON-LD output
     *
     * @param string $jsonLd
     * @return string
     */
    public function minify(string $jsonLd): string
    {
        // Extract JSON from script tag
        if (preg_match('/<script[^>]*>(.*?)<\/script>/s', $jsonLd, $matches)) {
            $json = $matches[1];
            
            // Decode and re-encode without pretty print
            $data = json_decode($json, true);
            if ($data !== null) {
                $minified = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($minified !== false) {
                    return sprintf(
                        '<script type="application/ld+json">%s</script>',
                        $minified
                    );
                }
            }
        }
        
        return $jsonLd;
    }
}
