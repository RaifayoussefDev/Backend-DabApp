<?php

namespace App\Services\MotorcycleImport;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Streams the rows of the first worksheet of an .xlsx file without loading the
 * whole workbook in memory (PhpSpreadsheet needs several GB for 43k rows x 100
 * columns). Only the shared-strings table is kept in memory.
 *
 * Yields [int $excelRowNumber, array<int, string> $cells] where $cells is keyed
 * by 0-based column index (gaps for empty cells are filled with '').
 */
class XlsxStreamReader
{
    private string $workDir;

    /** @var string[] */
    private array $sharedStrings = [];

    public function __construct(private string $path)
    {
    }

    /**
     * @return \Generator<int, array{0:int, 1:array<int,string>}>
     */
    public function rows(): \Generator
    {
        $sheetFile = $this->extract();

        try {
            $this->loadSharedStrings();

            $reader = new XMLReader();
            if (!$reader->open($sheetFile, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('Unable to open worksheet XML.');
            }

            $autoRow = 0;
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $rowNumber = (int) $reader->getAttribute('r') ?: $autoRow + 1;
                $autoRow = $rowNumber;

                $node = $reader->expand();
                if (!$node) {
                    continue;
                }

                $cells = [];
                $autoCol = -1;
                foreach ($node->childNodes as $c) {
                    if ($c->nodeType !== XML_ELEMENT_NODE || $c->localName !== 'c') {
                        continue;
                    }

                    $ref = $c->getAttribute('r');
                    $col = $ref !== '' ? self::columnIndex($ref) : $autoCol + 1;
                    $autoCol = $col;

                    $cells[$col] = $this->cellValue($c);
                }

                if ($cells) {
                    $max = max(array_keys($cells));
                    $cells += array_fill(0, $max + 1, '');
                    ksort($cells);
                }

                yield [$rowNumber, $cells];
            }

            $reader->close();
        } finally {
            $this->cleanup();
        }
    }

    private function cellValue(\DOMElement $c): string
    {
        $type = $c->getAttribute('t');

        if ($type === 'inlineStr') {
            return $this->textOf($c->getElementsByTagName('is')->item(0));
        }

        $v = $c->getElementsByTagName('v')->item(0);
        $raw = $v ? $v->textContent : '';

        return match ($type) {
            's'     => $this->sharedStrings[(int) $raw] ?? '',
            'b'     => $raw === '1' ? 'TRUE' : 'FALSE',
            default => $raw,
        };
    }

    /** Concatenates the <t> runs of a rich-text node, skipping phonetic hints (<rPh>). */
    private function textOf(?\DOMNode $node): string
    {
        if (!$node) {
            return '';
        }

        $text = '';
        foreach ($node->getElementsByTagName('t') as $t) {
            if ($t->parentNode && $t->parentNode->localName === 'rPh') {
                continue;
            }
            $text .= $t->textContent;
        }

        return $text;
    }

    private function loadSharedStrings(): void
    {
        $file = $this->workDir . '/sharedStrings.xml';
        if (!is_file($file)) {
            return;
        }

        $reader = new XMLReader();
        $reader->open($file, null, LIBXML_NONET | LIBXML_COMPACT);

        // Move to the first <si>, then hop sibling to sibling (next() skips the subtree).
        while ($reader->read() && !($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si')) {
        }
        while ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
            $this->sharedStrings[] = $this->textOf($reader->expand());
            if (!$reader->next('si')) {
                break;
            }
        }

        $reader->close();
    }

    /** Extracts the first worksheet + shared strings to a temp dir, returns the sheet path. */
    private function extract(): string
    {
        $zip = new ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new RuntimeException('The file is not a valid .xlsx archive.');
        }

        $sheetEntry = $this->firstSheetEntry($zip);

        $this->workDir = sys_get_temp_dir() . '/xlsx_' . bin2hex(random_bytes(6));
        mkdir($this->workDir, 0775, true);

        $this->copyEntry($zip, $sheetEntry, $this->workDir . '/sheet.xml');
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $this->copyEntry($zip, 'xl/sharedStrings.xml', $this->workDir . '/sharedStrings.xml');
        }

        $zip->close();

        return $this->workDir . '/sheet.xml';
    }

    private function copyEntry(ZipArchive $zip, string $entry, string $target): void
    {
        $in = $zip->getStream($entry);
        if (!$in) {
            throw new RuntimeException("Missing entry {$entry} in .xlsx file.");
        }
        $out = fopen($target, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }

    /** Resolves the first sheet in workbook order (not necessarily sheet1.xml). */
    private function firstSheetEntry(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook && $rels) {
            $wb = simplexml_load_string($workbook);
            $rl = simplexml_load_string($rels);
            if ($wb && $rl) {
                $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $sheets = $wb->xpath('//m:sheets/m:sheet');
                if ($sheets) {
                    $rid = (string) $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                    foreach ($rl->Relationship as $rel) {
                        if ((string) $rel['Id'] === $rid) {
                            $target = ltrim((string) $rel['Target'], '/');
                            $entry = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                            if ($zip->locateName($entry) !== false) {
                                return $entry;
                            }
                        }
                    }
                }
            }
        }

        if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }

        throw new RuntimeException('No worksheet found in .xlsx file.');
    }

    private function cleanup(): void
    {
        $this->sharedStrings = [];
        if (isset($this->workDir) && is_dir($this->workDir)) {
            foreach (glob($this->workDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->workDir);
        }
    }

    /** "AB12" -> 27 (0-based). */
    public static function columnIndex(string $ref): int
    {
        $index = 0;
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $ch = ord($ref[$i]);
            if ($ch < 65 || $ch > 90) {
                break;
            }
            $index = $index * 26 + ($ch - 64);
        }

        return $index - 1;
    }
}
