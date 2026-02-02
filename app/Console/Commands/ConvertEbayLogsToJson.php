<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConvertEbayLogsToJson extends Command
{
    protected $signature = 'ebay:convert-logs {--date= : Specific date (Y-m-d) to convert, or "all" for all files}';
    protected $description = 'Convert eBay log files from text format to JSON format';

    public function handle()
    {
        $date = $this->option('date');
        $logsPath = storage_path('logs');

        if ($date && $date !== 'all') {
            // Convert specific date
            $files = ["{$logsPath}/ebay-{$date}.log"];
        } else {
            // Find all ebay log files
            $files = glob("{$logsPath}/ebay-*.log");
        }

        if (empty($files)) {
            $this->error('No eBay log files found.');
            return 1;
        }

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->warn("File not found: {$file}");
                continue;
            }

            $this->info("Converting: {$file}");
            $this->convertLogFile($file);
        }

        $this->info('Conversion complete!');
        return 0;
    }

    protected function convertLogFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        $entries = [];
        $currentEntry = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Check if this is a new log entry (starts with timestamp pattern)
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/', $line, $matches)) {
                // Save previous entry if exists
                if ($currentEntry) {
                    $entries[] = $currentEntry;
                }

                // Parse the log entry
                $timestamp = $matches[1];
                $channel = $matches[2];
                $level = $matches[3];
                $message = $matches[4];

                // Try to extract JSON context from message
                $context = [];
                if (preg_match('/^(.*?) (\{.*\}|\[.*\])$/', $message, $msgMatches)) {
                    $message = trim($msgMatches[1]);
                    $jsonStr = $msgMatches[2];
                    $decoded = json_decode($jsonStr, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $context = $decoded;
                    } else {
                        $context = ['raw' => $jsonStr];
                    }
                }

                // Check if message itself is JSON
                if (empty($context) && (str_starts_with($message, '{') || str_starts_with($message, '['))) {
                    $decoded = json_decode($message, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $context = $decoded;
                        $message = 'JSON Data';
                    }
                }

                $currentEntry = [
                    'timestamp' => $timestamp,
                    'channel' => $channel,
                    'level' => $level,
                    'message' => $message,
                    'context' => $context,
                ];
            } else {
                // This is a continuation of the previous entry (multi-line JSON, etc.)
                if ($currentEntry) {
                    // Try to append to context or message
                    if (str_starts_with($line, '{') || str_starts_with($line, '[') || str_starts_with($line, '"') || str_starts_with($line, '<')) {
                        // This might be JSON or XML content
                        if (isset($currentEntry['_raw_continuation'])) {
                            $currentEntry['_raw_continuation'] .= "\n" . $line;
                        } else {
                            $currentEntry['_raw_continuation'] = $line;
                        }
                    } else {
                        // Regular continuation
                        if (isset($currentEntry['_raw_continuation'])) {
                            $currentEntry['_raw_continuation'] .= "\n" . $line;
                        } else {
                            $currentEntry['_raw_continuation'] = $line;
                        }
                    }
                }
            }
        }

        // Don't forget the last entry
        if ($currentEntry) {
            $entries[] = $currentEntry;
        }

        // Process raw continuations (parse multi-line JSON or XML)
        foreach ($entries as &$entry) {
            if (isset($entry['_raw_continuation'])) {
                $raw = $entry['_raw_continuation'];

                // Try to parse as JSON first
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $entry['context'] = array_merge($entry['context'] ?? [], $decoded);
                } else {
                    // Check if it's XML and convert to JSON
                    $xmlData = $this->tryParseXml($raw);
                    if ($xmlData !== null) {
                        $entry['xml_data'] = $xmlData;
                    } else {
                        $entry['raw_data'] = $raw;
                    }
                }
                unset($entry['_raw_continuation']);
            }

            // Also check if context contains XML strings and convert them
            if (isset($entry['context']) && is_array($entry['context'])) {
                $entry['context'] = $this->convertXmlFieldsInContext($entry['context']);
            }

            // Check raw_data for XML
            if (isset($entry['raw_data']) && is_string($entry['raw_data'])) {
                $xmlData = $this->tryParseXml($entry['raw_data']);
                if ($xmlData !== null) {
                    $entry['xml_data'] = $xmlData;
                    unset($entry['raw_data']);
                }
            }
        }

        // Create output file
        $outputPath = str_replace('.log', '.json', $filePath);
        $jsonContent = json_encode([
            'source_file' => basename($filePath),
            'converted_at' => now()->toIso8601String(),
            'total_entries' => count($entries),
            'entries' => $entries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($outputPath, $jsonContent);

        $this->info("  -> Created: {$outputPath}");
        $this->info("  -> Total entries: " . count($entries));
    }

    /**
     * Try to parse XML string and convert to array
     */
    protected function tryParseXml(string $content): ?array
    {
        $content = trim($content);

        // Handle multiple levels of escaping from JSON-encoded logs
        // First, check for double-escaped quotes (\\") and unescape them
        if (str_contains($content, '\\"') || str_contains($content, '\\\\')) {
            // Handle \\\" -> \"  -> "
            $content = str_replace('\\"', '"', $content);
            // Handle remaining escapes
            $content = str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $content);
        }

        // Remove trailing "} that may be left from truncated JSON
        $content = preg_replace('/"\s*\}\s*$/', '', $content);
        $content = trim($content);

        // Check if it looks like XML
        if (!str_starts_with($content, '<') && !str_contains($content, '<?xml')) {
            return null;
        }

        // Clean the XML (remove SOAP namespaces)
        $cleanedXml = $this->cleanSoapXml($content);

        // Try to parse
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($cleanedXml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if ($xml === false) {
            return null;
        }

        // Convert to array
        return $this->xmlToArray($xml);
    }

    /**
     * Clean SOAP XML by removing namespace prefixes
     */
    protected function cleanSoapXml(string $xmlContent): string
    {
        // Remove XML declaration if present
        $cleanedXml = preg_replace('/<\?xml[^>]*\?>/', '', $xmlContent);

        // Remove all namespace declarations
        $cleanedXml = preg_replace('/\s+xmlns(:[a-zA-Z0-9_-]+)?="[^"]*"/', '', $cleanedXml);

        // Remove namespace prefixes from element names
        $cleanedXml = preg_replace('/<(\/?)([a-zA-Z0-9_-]+):([a-zA-Z0-9_-]+)/', '<$1$3', $cleanedXml);

        // Remove namespace prefixes from attribute names
        $cleanedXml = preg_replace('/\s+[a-zA-Z0-9_-]+:([a-zA-Z0-9_-]+)=/', ' $1=', $cleanedXml);

        // Remove namespaced attributes entirely
        $cleanedXml = preg_replace('/\s+[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+="[^"]*"/', '', $cleanedXml);

        // Add XML declaration back
        $cleanedXml = '<?xml version="1.0" encoding="UTF-8"?>' . trim($cleanedXml);

        return $cleanedXml;
    }

    /**
     * Convert SimpleXMLElement to array
     */
    protected function xmlToArray($node): array|string
    {
        $result = [];

        // Get attributes
        foreach ($node->attributes() as $attrName => $attrValue) {
            $result['@' . $attrName] = (string) $attrValue;
        }

        // Get child elements
        $children = $node->children();

        if ($children->count() === 0) {
            $text = trim((string) $node);
            if (!empty($result)) {
                if (!empty($text)) {
                    $result['@value'] = $text;
                }
                return $result;
            }
            return $text;
        }

        // Process children
        $childArray = [];
        foreach ($children as $childName => $childNode) {
            $childValue = $this->xmlToArray($childNode);

            if (isset($childArray[$childName])) {
                if (!is_array($childArray[$childName]) || !isset($childArray[$childName][0])) {
                    $childArray[$childName] = [$childArray[$childName]];
                }
                $childArray[$childName][] = $childValue;
            } else {
                $childArray[$childName] = $childValue;
            }
        }

        return array_merge($result, $childArray);
    }

    /**
     * Recursively check context array for XML strings and convert them
     */
    protected function convertXmlFieldsInContext(array $context): array
    {
        foreach ($context as $key => &$value) {
            if (is_string($value)) {
                // Check for common XML field names or XML content
                if (in_array($key, ['raw_xml', 'raw_content', 'xml', 'xml_data', 'raw_content_preview']) ||
                    (strlen($value) > 10 && (str_starts_with(trim($value), '<') || str_contains($value, '<?xml')))) {
                    $xmlData = $this->tryParseXml($value);
                    if ($xmlData !== null) {
                        $value = $xmlData;
                    }
                }
            } elseif (is_array($value)) {
                $value = $this->convertXmlFieldsInContext($value);
            }
        }

        return $context;
    }
}
