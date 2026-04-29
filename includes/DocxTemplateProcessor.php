<?php
/**
 * Lightweight DOCX template processor for RA 9048 / RA 10172 document automation.
 *
 * A .docx file is a ZIP archive whose `word/document.xml` contains the visible text.
 * This class:
 *   1. Copies the template to a temp file
 *   2. Reads `word/document.xml`
 *   3. Replaces `${placeholder}` tokens with provided values (XML-escaped)
 *   4. Supports row-cloning for table grids (e.g. corrections, supporting documents)
 *   5. Writes the result back into the zip and saves to a target path
 *
 * Why not PHPWord:
 *   - PHPWord 1.x requires Composer and pulls phpoffice/math + ~5MB of files.
 *   - We only need template-fill + row-clone, which is ~150 lines of PHP.
 *   - Keeps the project Composer-free, matching existing offline-NAS conventions.
 *
 * Placeholder syntax:
 *   ${field_name}                Single replacement
 *   ${row_field}  inside a table row that contains ${block_name__row}
 *                 marks the row as a clone-block — see cloneRowAndSetValues()
 */

class DocxTemplateProcessor
{
    /** @var string Path to the working copy of the .docx (a temp file we mutate) */
    private $tempPath;

    /** @var string Contents of word/document.xml */
    private $documentXml;

    /** @var string Original template path (for error messages) */
    private $templatePath;

    public function __construct(string $templatePath)
    {
        if (!is_file($templatePath)) {
            throw new RuntimeException("Template not found: {$templatePath}");
        }
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is required.');
        }

        $this->templatePath = $templatePath;
        $this->tempPath = tempnam(sys_get_temp_dir(), 'docx_');
        if ($this->tempPath === false) {
            throw new RuntimeException('Failed to create temp file for DOCX processing.');
        }
        if (!copy($templatePath, $this->tempPath)) {
            throw new RuntimeException("Failed to copy template to temp: {$templatePath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($this->tempPath) !== true) {
            throw new RuntimeException("Failed to open DOCX (zip): {$templatePath}");
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException("word/document.xml not found inside template: {$templatePath}");
        }
        $this->documentXml = $xml;
    }

    /**
     * Replace a single ${placeholder} with a value.
     * Handles the case where Word splits a placeholder across multiple <w:r> runs
     * by first un-splitting any ${...} that contains XML tags between the braces.
     */
    public function setValue(string $name, $value): void
    {
        $this->normalizeSplitPlaceholders();

        $needle = '${' . $name . '}';
        $haystack = $this->documentXml;
        $replacement = $this->xmlEscape((string) $value);
        $this->documentXml = str_replace($needle, $replacement, $haystack);
    }

    /**
     * Set many values at once.
     *
     * @param array<string, scalar|null> $values
     */
    public function setValues(array $values): void
    {
        $this->normalizeSplitPlaceholders();
        $search = [];
        $replace = [];
        foreach ($values as $name => $value) {
            $search[] = '${' . $name . '}';
            $replace[] = $this->xmlEscape((string) ($value ?? ''));
        }
        $this->documentXml = str_replace($search, $replace, $this->documentXml);
    }

    /**
     * Clone a table row N times, one per data entry, and substitute placeholders within each clone.
     *
     * The block is identified by a marker placeholder named `${blockName}` that appears anywhere
     * inside the row to be cloned. Within that row, other `${field}` placeholders are replaced
     * per data entry.
     *
     * Multiple occurrences of the same `${blockName}` are supported — each occurrence (in different
     * tables/sections of the same document) is independently expanded with the same data set. This
     * is needed for templates like the RA 9048 petition which renders the corrections grid twice
     * (once at the top, once again in the MCR's "ACTION TAKEN" section).
     *
     * @param string $blockName  The marker placeholder (without `${}`) identifying the row
     * @param array<int, array<string, scalar|null>> $rows  One associative array per output row
     */
    public function cloneRowAndSetValues(string $blockName, array $rows): void
    {
        $this->normalizeSplitPlaceholders();

        $marker = '${' . $blockName . '}';

        // Iterate as long as another occurrence of the marker exists. Each pass replaces ONE
        // enclosing <w:tr> with N clones (and the clones don't contain $marker anymore, so this
        // terminates after exactly one pass per template-row that the user marked).
        $guard = 0;
        while (($markerPos = strpos($this->documentXml, $marker)) !== false) {
            // Walk outward from the marker to find the enclosing <w:tr> ... </w:tr>.
            $rowStart = strrpos(substr($this->documentXml, 0, $markerPos), '<w:tr');
            if ($rowStart === false) {
                throw new RuntimeException("Block marker \${{$blockName}} is not inside a table row.");
            }
            $rowEndTagPos = strpos($this->documentXml, '</w:tr>', $markerPos);
            if ($rowEndTagPos === false) {
                throw new RuntimeException("Could not find row end for block \${{$blockName}}.");
            }
            $rowEnd = $rowEndTagPos + strlen('</w:tr>');
            $rowXml = substr($this->documentXml, $rowStart, $rowEnd - $rowStart);

            // Build the replacement: one row clone per data entry, with all ${marker} and field placeholders substituted.
            $clones = '';
            foreach ($rows as $row) {
                $clone = str_replace($marker, '', $rowXml);
                foreach ($row as $field => $value) {
                    $clone = str_replace('${' . $field . '}', $this->xmlEscape((string) ($value ?? '')), $clone);
                }
                $clones .= $clone;
            }

            // If the data set is empty, drop the template row entirely.
            $this->documentXml = substr($this->documentXml, 0, $rowStart) . $clones . substr($this->documentXml, $rowEnd);

            // Defensive infinite-loop guard.
            if (++$guard > 50) {
                throw new RuntimeException("Block marker \${{$blockName}} expansion exceeded safety limit (>50 occurrences).");
            }
        }
        return;
    }

    /**
     * Save the populated document to the given path.
     */
    public function saveAs(string $outputPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($this->tempPath) !== true) {
            throw new RuntimeException("Failed to reopen temp DOCX for writing.");
        }
        if ($zip->addFromString('word/document.xml', $this->documentXml) === false) {
            $zip->close();
            throw new RuntimeException('Failed to write document.xml back into DOCX.');
        }
        $zip->close();

        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create output directory: {$dir}");
        }
        if (!copy($this->tempPath, $outputPath)) {
            throw new RuntimeException("Failed to write output DOCX: {$outputPath}");
        }
    }

    public function __destruct()
    {
        if (is_string($this->tempPath) && is_file($this->tempPath)) {
            @unlink($this->tempPath);
        }
    }

    /**
     * Word may split a single placeholder across multiple <w:r> runs (e.g. when
     * the user typed it character-by-character or applied formatting). Collapse
     * any ${...} that contains XML tags inside the braces back to a clean form.
     */
    private function normalizeSplitPlaceholders(): void
    {
        $this->documentXml = preg_replace_callback(
            '/\$\{([^}]*)\}/s',
            function ($m) {
                $stripped = preg_replace('/<[^>]+>/', '', $m[1]);
                return '${' . $stripped . '}';
            },
            $this->documentXml
        );
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
