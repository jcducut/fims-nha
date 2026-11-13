<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * LAA release request — the regional office's memorandum asking for the release
 * of a Letter of Advice of Allotment (MEMORANDUM / FOR / THRU / SUBJECT, "May we
 * respectfully request …"). A separate document from the LAA itself
 * (LaaPrintService) — model + HTML (letter paper, Arial 11pt, no footer line).
 *
 * The memo layout (html() / scripts/gen_laareq_docx.js) is shared: the FTLA
 * request (FtlaRequestPrintService) builds the same model shape — date, rows,
 * subject, paragraphs (runs), closing, signatory — and prints through it.
 *
 * Printed through PrintService::print('LAAREQ', …); DOCX: scripts/gen_laareq_docx.js.
 * Ported from the old REGIONAL gen_laa_docx.js.
 */
class LaaRequestPrintService
{
    /**
     * Model. Payload (all optional): date (Y-m-d or text; blank → today),
     * for_name / for_title, thru_name / thru_title (each row prints only with a
     * name), subject (default "Request for the Release of Letter of Advice of
     * Allotment (LAA)"), description (or project_description — "for the
     * proposed budget for <description>"), amount, sig_name / sig_title /
     * sig_office (the requesting officer), laa_number (file name only).
     */
    public function model(array $p): array
    {
        $amount = PrintService::amount($p['amount'] ?? null);
        $desc   = PrintService::text($p['description'] ?? $p['project_description'] ?? '');
        $laaNo  = PrintService::text($p['laa_number'] ?? $p['allocation_number'] ?? '');

        return [
            'date'      => PrintService::longDate($p['date'] ?? ''),
            'rows'      => self::memoRows($p),
            'subject'   => PrintService::text($p['subject'] ?? '') ?: 'Request for the Release of Letter of Advice of Allotment (LAA)',
            // justified paragraphs, each a list of runs [text, bold]
            'paragraphs' => [
                [
                    ['text' => 'May we respectfully request for the release of Letter of Advice of Allotment (LAA) for the proposed budget for '
                        . ($desc !== '' ? $desc . ' ' : '') . 'amounting to ', 'bold' => false],
                    ['text' => PrintService::amountInWords($amount) . ' (Php ' . PrintService::money($amount) . ')', 'bold' => true],
                    ['text' => '.', 'bold' => false],
                ],
                [['text' => 'For your consideration and appropriate action please.', 'bold' => false]],
            ],
            'closing'   => 'Thank you.',
            'signatory' => [
                'name'   => PrintService::text($p['sig_name'] ?? ''),
                'title'  => PrintService::text($p['sig_title'] ?? ''),
                'office' => PrintService::text($p['sig_office'] ?? ''),
            ],
            'header_parts' => null,
            'footer_text'  => '',
            'file_base'    => 'LAA-REQUEST' . ($laaNo !== '' ? '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $laaNo) : ''),
        ];
    }

    /** The memo's FOR / THRU rows from for_name / _title, thru_name / _title (each only with a name). */
    public static function memoRows(array $p): array
    {
        $rows = [];
        foreach (['for' => 'FOR', 'thru' => 'THRU'] as $key => $label) {
            $name = PrintService::text($p[$key . '_name'] ?? '');
            if ($name !== '') {
                $rows[] = ['label' => $label, 'name' => $name, 'title' => PrintService::text($p[$key . '_title'] ?? '')];
            }
        }

        return $rows;
    }

    /**
     * Letter paper (612 × 792pt) — or A4 (595.28 × 841.89pt) with model `paper` 'A4' —
     * 1in sides (468pt / 451.28pt text width), Arial 11pt.
     * Mirrors scripts/gen_laareq_docx.js. $media as CbaPrintService::html().
     *
     * Optional model keys (the CFA memo uses them; the LAA / FTLA requests don't):
     *   paper 'A4'; letterhead_first_page_only (the PDF letterhead on page 1 only —
     *   a later page's running header is PrintService's pdf_running_header);
     *   a paragraph { list: [[label, value], …] } — an indented "Label : value" list;
     *   certification [[runs], …] — lines on the right under the body (e.g.
     *   "Certified Funds Available: Php …"); signature_rows [{ label, signers:
     *   [{ name, title_lines }] }] — rows of side-by-side signatories in place of
     *   the single signatory; an empty closing prints nothing.
     * The memo request (MemoRequestPrintService) adds: a paragraph { block: 'item', label,
     *   label_bold, label_at, text_at (pt), runs, after (pt)[, aside, aside_at] } — a
     *   hanging-indent item ("I.", "a.", "1." or a continuation with no label);
     *   { block: 'table', header [runs], rows [{ kind row|group|total, span, cells [runs] }],
     *   widths [fractions], align [left|center|right], after } — a bordered table;
     *   a run's underline; a signature row's center (label and names centered).
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e    = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $runs = static function (array $runs) use ($e): string {
            $out = '';
            foreach ($runs as $run) {
                $text = $run['bold'] ? '<b>' . $e($run['text']) . '</b>' : $e($run['text']);
                $out .= !empty($run['underline']) ? '<u>' . $text . '</u>' : $text;
            }

            return $out;
        };

        $a4    = ($m['paper'] ?? 'letter') === 'A4';
        $tw    = $a4 ? 451.28 : 468;          // text width (pt)
        $pageW = $a4 ? '595.28' : '612';
        $pageH = $a4 ? '841.89' : '792';
        $css   = [];   // the optional parts' CSS, once each (in the order first used)

        $rows = '';
        foreach ($m['rows'] as $r) {
            $rows .= '<tr><td class="l">' . $e($r['label']) . '</td><td class="c">:</td><td><b>' . $e($r['name']) . '</b>'
                . '<div class="rt">' . ($e($r['title']) ?: '&nbsp;') . '</div></td></tr>';
        }
        $rows .= '<tr><td class="l">SUBJECT</td><td class="c">:</td><td><b>' . $e($m['subject']) . '</b></td></tr>';

        $paragraphs = '';
        foreach ($m['paragraphs'] as $p) {
            if (isset($p['list'])) {      // indented "Label : value" rows
                $paragraphs .= '<table class="list">';
                foreach ($p['list'] as [$label, $value]) {
                    $paragraphs .= '<tr><td class="ll"><u>' . $e($label) . '</u></td><td class="c">:</td><td>' . $e($value) . '</td></tr>';
                }
                $paragraphs .= '</table>';
                $css['list'] = '.list{border-collapse:collapse;margin:0 0 14pt 36pt;width:' . ($tw - 36) . 'pt;}'
                    . '.list td{padding:0 0 6pt;vertical-align:top;}.list td.ll{width:130pt;}.list td.c{width:21.6pt;}';
                continue;
            }
            if (($p['block'] ?? '') === 'item') {   // hanging-indent item: label at label_at, text at text_at
                $hang  = $p['text_at'] - $p['label_at'];
                $label = $p['label_bold'] ? '<b>' . $e($p['label']) . '</b>' : $e($p['label']);
                if (($p['aside'] ?? '') !== '') {   // with a right column (e.g. "– Annex "A"")
                    $paragraphs .= '<table class="itm" style="margin:0 0 ' . $p['after'] . 'pt ' . $p['label_at'] . 'pt;width:' . ($tw - $p['label_at']) . 'pt;">'
                        . '<tr><td style="width:' . $hang . 'pt;">' . $label . '</td><td style="width:' . ($p['aside_at'] - $p['text_at']) . 'pt;">'
                        . $runs($p['runs']) . '</td><td>' . $e($p['aside']) . '</td></tr></table>';
                    $css['itm'] = '.itm{border-collapse:collapse;}.itm td{padding:0;vertical-align:top;}';
                } else {
                    // the label floats into the left margin of the text (an inline-block sits high in Dompdf)
                    $paragraphs .= '<p class="j" style="margin:0 0 ' . $p['after'] . 'pt ' . $p['text_at'] . 'pt;">'
                        . ($hang > 0 ? '<span class="lbl" style="width:' . $hang . 'pt;margin-left:-' . $hang . 'pt;">' . $label . '</span>' : '') . $runs($p['runs']) . '</p>';
                    $css['lbl'] = '.lbl{float:left;}';
                }
                continue;
            }
            if (($p['block'] ?? '') === 'table') {   // bordered table: header, rows, one-cell group rows, total rows
                $cols  = count($p['header']);
                $align = static fn (int $j) => ['center' => ' class="ce"', 'right' => ' class="ri"'][$p['align'][$j] ?? ''] ?? '';
                $bold  = static fn (array $rs) => array_map(static fn ($x) => ['bold' => true] + $x, $rs);   // a total row's runs
                $html  = '<tr>';
                foreach ($p['header'] as $j => $h) {
                    $html .= '<td class="th" style="width:' . round($p['widths'][$j] * 100, 2) . '%;">' . $runs($h) . '</td>';
                }
                $html .= '</tr>';
                foreach ($p['rows'] as $r) {
                    $html .= '<tr>';
                    if ($r['kind'] === 'group') {
                        $html .= '<td colspan="' . $cols . '">' . $runs($r['cells'][0]) . '</td>';
                    } elseif ($r['kind'] === 'total') {
                        $html .= '<td colspan="' . $r['span'] . '" class="ri">' . $runs($bold($r['cells'][0])) . '</td>';
                        foreach (array_slice($r['cells'], 1) as $k => $c) {
                            $html .= '<td' . $align($r['span'] + $k) . '>' . $runs($bold($c)) . '</td>';
                        }
                    } else {
                        foreach ($r['cells'] as $j => $c) {
                            $html .= '<td' . $align($j) . '>' . $runs($c) . '</td>';
                        }
                    }
                    $html .= '</tr>';
                }
                $paragraphs .= '<table class="tbl" style="margin:0 0 ' . $p['after'] . 'pt;">' . $html . '</table>';
                $css['tbl'] = '.tbl{border-collapse:collapse;width:' . $tw . 'pt;}'
                    . '.tbl td{border:0.75pt solid #000;padding:1pt 4pt;vertical-align:top;font-size:10pt;}'
                    . '.tbl td.th,.tbl td.ce{text-align:center;}.tbl td.ri{text-align:right;}';
                continue;
            }
            $paragraphs .= '<p class="j">' . $runs($p) . '</p>';
        }

        $certification = '';
        if (!empty($m['certification'])) {
            foreach ($m['certification'] as $line) {
                $certification .= '<div>' . $runs($line) . '</div>';
            }
            $certification = '<div class="cert">' . $certification . '</div>';
            $css['cert'] = '.cert{margin:14pt 0 0 ' . round($tw / 2, 2) . 'pt;}';
        }

        if (!empty($m['signature_rows'])) {   // rows of side-by-side signatories
            $signatures = '';
            foreach ($m['signature_rows'] as $row) {
                $cells = '';
                $width = round(100 / max(1, count($row['signers'])), 2);
                $center = !empty($row['center']);   // e.g. the memo request's "Approved by:"
                foreach ($row['signers'] as $s) {
                    $cells .= '<td style="width:' . $width . '%;' . ($center ? 'text-align:center;' : '') . '"><b>' . ($e($s['name']) ?: '&nbsp;') . '</b>'
                        . implode('', array_map(static fn ($l) => '<br>' . htmlspecialchars($l, ENT_QUOTES, 'UTF-8'), $s['title_lines'])) . '</td>';
                }
                // One unbreakable block: a label ("Approved:") never stays behind at a page's foot.
                $signatures .= '<div class="sigblock">'
                    . ($row['label'] !== '' ? '<p class="slabel"' . ($center ? ' style="text-align:center;"' : '') . '>' . $e($row['label']) . '</p>' : '')
                    . '<table class="sigrow"><tr>' . $cells . '</tr></table></div>';
            }
            $css['sig'] = '.sigblock{page-break-inside:avoid;}'
                . '.slabel{margin:28pt 0 0;}.sigrow{border-collapse:collapse;width:' . $tw . 'pt;margin-top:30pt;}'
                . '.sigrow td{padding:0 10pt 0 0;vertical-align:top;}';
        } else {
            $sig = $m['signatory'];
            $signatures = '<div class="sig"><b>' . ($e($sig['name']) ?: '&nbsp;') . '</b><br>' . $e($sig['title'])
                . ($sig['office'] !== '' ? '<br>' . $e($sig['office']) : '') . '</div>';
        }

        // The letterhead repeats on every PDF page, unless it is page 1's only (then it is plain flow,
        // page 1 keeps a 36pt top margin and later pages clear PrintService's running header:
        // its lines + "Page X of Y" from y = 30pt, 11pt apart).
        $firstOnly = !empty($m['letterhead_first_page_only']) && $media === 'print';
        [$top, $hdrCss] = $firstOnly
            ? [30 + (count($m['pdf_running_header']['lines'] ?? []) + 1) * 11 + 16, '@page :first{margin-top:36pt;}']
            : PrintService::repeatingHeaderCss($m, $tw, 90, 36, 14);
        $pageCss = $media === 'print'
            ? '@page{size:' . ($a4 ? 'A4' : 'letter') . ' portrait;margin:' . $top . 'pt 72pt 54pt 72pt;}body{margin:0;}' . $hdrCss
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{position:relative;width:' . $pageW . 'pt;min-height:' . $pageH . 'pt;margin:0 auto;padding:36pt 72pt 54pt;'
              . 'box-sizing:border-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:11pt;color:#000;line-height:1.2;}'
            . 'p{margin:0;}'
            . '.hdr{text-align:center;margin-bottom:14pt;}'
            . '.hdr>img{width:auto !important;height:auto;max-width:' . $tw . 'pt;max-height:90pt;}'
            . '.date{margin:0 0 14pt;}.memo{font-weight:bold;margin:0 0 14pt;}'
            . 'table.rows{border-collapse:collapse;width:' . $tw . 'pt;}table.rows td{padding:0;vertical-align:top;}'
            . 'table.rows td.l{width:86.4pt;font-weight:bold;}table.rows td.c{width:21.6pt;}'
            . '.rt{margin-bottom:10pt;}'
            . '.rule{border-bottom:1pt solid #000;margin:8pt 0 12pt;}'
            . '.j{text-align:justify;margin:0 0 14pt;}'
            . '.sig{margin-top:44pt;}'
            . implode('', $css)
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . PrintService::headerHtml($m, $tw, $m['brand_document'] ?? 'LAAREQ') . '</div>', '')
            . '<p class="date">' . $e($m['date']) . '</p>'
            . '<p class="memo">MEMORANDUM</p>'
            . '<table class="rows">' . $rows . '</table>'
            . '<div class="rule"></div>'
            . $paragraphs
            . (($m['closing'] ?? '') !== '' ? '<p>' . $e($m['closing']) . '</p>' : '')
            . $certification
            . $signatures
            . PrintService::bodyClose($media, '');
    }
}
