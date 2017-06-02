<?php
/**
 * Copyright (c) 2017 Edward Zhang (zhangqi)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace cn\hsrzq;

/**
 * Class SuperDown
 *
 * A parser library for superdown text, which is similar as markdown,
 * but more powerful and strict.
 * @package cn\hsrzq
 * @author zhangqi
 */
class SuperDown
{
    /**
     * @var int Nest indent space(blank) count
     */
    public $cfgBLK = 4;
    /**
     * @var int Table of contents level
     */
    public $cfgTOC = 2;
    /**
     * @var array Head N format
     */
    public $cfgHNF = [];
    /**
     * @var bool If auto link enabled
     */
    public $cfgATL = false;

    /**
     * @var string Raw SuperDown text
     */
    private $text;

    private $headings = [];

    public function __construct($text)
    {
        $this->text = $text;
    }

    public function makeHtml()
    {
        $lines = explode("\n", $this->text);
        $this->parseConfig($lines);
        return $this->parseLines($lines, true);
    }

    private function parseConfig(array &$lines)
    {
        $config = false;
        foreach ($lines as $key => $line) {
            switch (true) {
                case $key == 0 && !preg_match('/^(\^{3,})$/', $line): {
                    return;
                }
                case preg_match('/^(\^{3,})$/', $line): {
                    if (!$config) {
                        $config = true;
                        break;
                    } else {
                        $lines = array_slice($lines, $key + 1);
                        return;
                    }
                }
                case preg_match('/^BLK:\s*(\d{1,})\s*$/', $line, $matches): {
                    $this->cfgBLK = $matches[1];
                    break;
                }
                case preg_match('/^TOC:\s*([1-7])\s*$/', $line, $matches): {
                    $this->cfgTOC = $matches[1];
                    break;
                }
                case preg_match('/^H([1-7]):(.+)$/', $line, $matches): {
                    $this->cfgHNF[$matches[1]] = trim($matches[2]);
                    break;
                }
                case preg_match('/^ATL:\s*(true|false)\s*$/', $line, $matches): {
                    $this->cfgATL = $matches[1] == 'true';
                    break;
                }
            }
        }
    }

    private function parseLines(array $lines, $nested = false)
    {
        // block = [type, start, end, extra]
        $blocks = $this->parseBlock($lines, $nested);

        $html = '';
        foreach ($blocks as $block) {
            $method = 'make' . ucfirst($block[0]);

            $result = $this->{$method}($lines, $block) . "\n";
            $html .= $result;
        }
        return $html;
    }

    private function parseBlock(array $lines, $nested)
    {
        $position = -1;
        $blocks = [];

        foreach ($lines as $key => $line) {
            switch (true) {
                // horizontal line
                case $nested && preg_match('/^\-{3,}$/', $line): {
                    $blocks[++$position] = ['hr', $key, $key];
                    break;
                }
                // head
                case $nested && preg_match('/^(#+)(.*)$/', $line, $matches): {
                    $level = strlen($matches[1]);
                    $name = trim($matches[2], ' #');
                    if ($name == '') continue;

                    $indexes = [];
                    $heading = &$this->headings;
                    for ($i = 0; $i < $level - 1; $i++) {
                        if (empty($heading)) $heading[] = [];
                        $index = count($heading) - 1;
                        $indexes[] = $index;
                        $heading[$index][1] = $indexes;

                        // sub head
                        $heading = &$heading[$index][2];
                    }
                    $heading[] = [$name];
                    $index = count($heading) - 1;
                    $indexes[] = $index;

                    $heading[$index][1] = $indexes;
                    $blocks[++$position] = ['hn', $key, $key, [$level, $indexes]];
                    break;
                }
                // table of contents
                case $nested && preg_match('/^\[toc\]$/i', $line): {
                    $blocks[++$position] = ['toc', $key, $key];
                    break;
                }
                // ordered list
                case preg_match('/^[0-9a-z]+\..+/', $line, $matches): {
                    // already ol
                    if ($blocks[$position] && $blocks[$position][0] == 'ol') {
                        $blocks[$position][2] = $key;
                    } else {
                        $blocks[++$position] = ['ol', $key, $key];
                    }
                    break;
                }
                // unordered list
                case preg_match('/^\+.+/', $line, $matches): {
                    // already ul
                    if ($blocks[$position] && $blocks[$position][0] == 'ul') {
                        $blocks[$position][2] = $key;
                    } else {
                        $blocks[++$position] = ['ul', $key, $key];
                    }
                    break;
                }
                // definition list
                case preg_match('/^[;\:]/', $line, $matches): {
                    if ($blocks[$position] && $blocks[$position][0] == 'dl') {
                        $blocks[$position][2] = $key;
                    } else {
                        $blocks[++$position] = ['dl', $key, $key];
                    }
                    break;
                }
                // task list
                case preg_match('/^\- \[[ x]\]/i', $line, $matches): {
                    if ($blocks[$position] && $blocks[$position][0] == 'tl') {
                        $blocks[$position][2] = $key;
                    } else {
                        $blocks[++$position] = ['tl', $key, $key];
                    }
                    break;
                }
                // table
                case preg_match('/^\|.*\|$/', $line, $matches): {
                    if ($blocks[$position][0] != 'table') {
                        // check next line
                        if (preg_match('/^\|.*\|$/', $lines[$key + 1])) {
                            $blocks[++$position] = ['table', $key, $key];
                        } else {
                            $blocks[++$position] = ['normal', $key, $key];
                            continue;
                        }
                    }
                    switch (true) {
                        // caption
                        case preg_match('/^\|=\-.+\-=\|$/', $line)
                            && $blocks[$position][1] == $key: {
                            $blocks[$position][3]['caption'] = $key;
                            break;
                        }
                        case preg_match('/^\|:?([=\-])\1*:?\|(:?\1+:?\|)*$/', $line, $matches)
                            && !isset($blocks[$position][3]['thead']): {
                            $blocks[$position][3]['thead'] = $key;
                            $blocks[$position][3]['tfoot'] = $matches[1] == "=";

                            $cols = explode('|', trim($matches[0], '|'));
                            $aligns = [];
                            foreach ($cols as $col) {
                                $align = 'none';
                                if (preg_match('/^\s*(:?)[\-=]+(:?)\s*$/', $col, $matches)) {
                                    if (!empty($matches[1]) && !empty($matches[2])) {
                                        $align = 'center';
                                    } else if (!empty($matches[1])) {
                                        $align = 'left';
                                    } else if (!empty($matches[2])) {
                                        $align = 'right';
                                    }
                                }
                                $aligns[] = $align;
                            }
                            $blocks[$position][3]['aligns'] = $aligns;
                            break;
                        }
                        default: {
                            $blocks[$position][2] = $key;
                            break;
                        }
                    }
                    break;
                }
                // indent line, maybe nested list
                case preg_match("/^(\t| {{$this->cfgBLK}})(.+)$/", $line): {
                    switch ($blocks[$position][0]) {
                        case 'ol':
                        case 'ul':
                        case 'dl':
                        case 'tl': {
                            $blocks[$position][2] = $key;
                            break;
                        }
                        default: {
                            $blocks[++$position] = ['normal', $key, $key];
                            break;
                        }
                    }
                    break;
                }
                default: {
                    $blocks[++$position] = ['normal', $key, $key];
                    break;
                }
            }
        }
        return $blocks;
    }

    private function makeHr()
    {
        return "<hr />";
    }

    private function makeHn(array $lines, array $block)
    {
        list($type, $start, $end, $extra) = $block;

        $level = $extra[0];
        $indexes = $extra[1];
        $text = $this->makeInline(trim($lines[$start], '# '));
        if (isset($this->cfgHNF[$level])) {
            $text = preg_replace_callback(
                '/(?<!\\\\)\{H(\d|H)(?<!\\\\)\}/',
                function ($matches) use ($level, $indexes, $text) {
                    $match = $matches[1];
                    if ($match == 'H') {
                        return $text;
                    } elseif ($match <= $level) {
                        return $indexes[$match - 1] + 1;
                    } else {
                        return $matches[0];
                    }
                }, $this->cfgHNF[$level]);
        }
        $id = 'hn-' . implode('-', $indexes);
        return "<h{$level} id='$id'>{$text}</h{$level}>";
    }

    private function makeToc()
    {
        $headings = $this->headings;
        // filter heading level
        for ($i = 0; $i < $this->cfgTOC - 1; $i++) {
            $result = [];
            foreach ($headings as $heading) {
                if (isset($heading[2])) {
                    $result = array_merge($result, $heading[2]);
                }
            }
            $headings = $result;
        }
        return $this->makeTocEntry($headings);
    }

    private function makeOl(array $lines, array $block)
    {
        list($type, $start, $end, $extra) = $block;
        $lines = array_slice($lines, $start, $end - $start + 1);

        $blocks = $this->parseList($lines, '/^([0-9a-z]+\.)(.+)$/');
        $html = "<ol>";
        foreach ($blocks as $block) {
            $html .= "<li>" . $this->parseLines(array_slice($lines, $block[0], $block[1] - $block[0] + 1)) . "</li>";
        }
        $html .= "</ol>";
        return $html;
    }

    private function makeUl(array $lines, array $block)
    {
        list($type, $start, $end, $extra) = $block;
        $lines = array_slice($lines, $start, $end - $start + 1);

        $blocks = $this->parseList($lines, '/^(\+)(.+)$/');
        $html = "<ul>";
        foreach ($blocks as $block) {
            $html .= "<li>" . $this->parseLines(array_slice($lines, $block[0], $block[1] - $block[0] + 1)) . "</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    private function makeDl(array $lines, array $block)
    {
        list($type, $start, $end, $extra) = $block;
        $lines = array_slice($lines, $start, $end - $start + 1);

        $blocks = $this->parseList($lines, '/^([;\:])(.+)$/');
        $html = "<dl>";
        foreach ($blocks as $block) {
            $tag = $block[2][1] == ';' ? 'dt' : 'dd';
            $html .= "<$tag>" . $this->parseLines(array_slice($lines, $block[0], $block[1] - $block[0] + 1)) . "</$tag>";
        }
        $html .= "</dl>";
        return $html;
    }

    private function makeTl(array $lines, array $block)
    {
        list($type, $start, $end, $extra) = $block;
        $lines = array_slice($lines, $start, $end - $start + 1);

        $blocks = $this->parseList($lines, '/^(?:\- \[([ xX])\])(.+)$/');
        $html = "<ul>";
        foreach ($blocks as $block) {
            $checked = $block[2] == ' ' ? '' : 'checked';
            $html .= "<li><input type='checkbox' disabled $checked/>"
                . $this->parseLines(array_slice($lines, $block[0], $block[1] - $block[0] + 1))
                . "</li>";
        }
        $html .= "</ul>";
        return $html;
    }

    private function makeTable($lines, $block)
    {
        list($type, $start, $end, $extra) = $block;

        $html = "<table>";
        if (isset($block[3]['caption'])) {
            $html .= preg_replace_callback('/^\|=\-(.+)\-=\|$/',
                function ($matches) {
                    return "<caption>" . htmlspecialchars($matches[1]) . "</caption>";
                }, $lines[$block[3]['caption']]);
            $start = $block[3]['caption'] + 1;
        }
        if (isset($block[3]['thead'])) {
            $temp = $this->makeTableRows(array_slice($lines, $start, $block[3]['thead'] - $start), $extra['aligns'], true);
            $html .= "<thead>$temp</thead>";
            if ($block[3]['tfoot']) {
                $html .= "<tfoot>$temp</tfoot>";
            }
            $start = $block[3]['thead'] + 1;
        }
        $html .= "<tbody>" . $this->makeTableRows(array_slice($lines, $start, $end - $start + 1), $extra['aligns']) . "</tbody>";
        $html .= "</table>";
        return $html;
    }

    private function makeNormal(array $lines, $block)
    {
        list($type, $start, $end, $extra) = $block;

        $text = implode(' ', array_slice($lines, $start, $end - $start + 1));
        return $this->makeInline($text);
    }

    private function makeInline($text, $remove = false)
    {
        // code
        $text = preg_replace_callback(
            '/(?<!\\\\)(`)([^`]+?)(?<!\\\\)\1/',
            function ($matches) use ($remove) {
                return $remove ? $matches[2] : "<code>$matches[2]</code>";
            }, $text);
        // strong
        $text = preg_replace_callback(
            '/(?<!\\\\)(\*{2})(.+?)(?<!\\\\)\1/',
            function ($matches) use ($remove) {
                return $remove ? $matches[2] : "<strong>$matches[2]</strong>";
            }, $text);
        // italic
        $text = preg_replace_callback(
            '/(?<!\\\\)(\/{2})(.+?)(?<!\\\\)\1/',
            function ($matches) use ($remove) {
                return $remove ? $matches[2] : "<i>$matches[2]</i>";
            }, $text);
        // underline
        $text = preg_replace_callback(
            '/(?<!\\\\)(_{2})(.+?)(?<!\\\\)\1/',
            function ($matches) use ($remove) {
                return $remove ? $matches[2] : "<u>$matches[2]</u>";
            }, $text);
        // strickout
        $text = preg_replace_callback(
            '/(?<!\\\\)(~{2})(.+?)(?<!\\\\)\1/',
            function ($matches) use ($remove) {
                return $remove ? $matches[2] : "<s>$matches[2]</s>";
            }, $text);
        // auto link
        if ($this->cfgATL) {
            $text = preg_replace_callback('/(?:^|\s)(https?:\/\/\S+)(?:$|\s)/', function ($matches) {
                return "<a href='$matches[1]'>$matches[1]</a>";
            }, $text);
        }

        return $this->escapeSymbol($text);
    }

    private function escapeSymbol($text)
    {
        return str_replace(
            ['\[', '\]', '\(', '\)', '\|', '\=', '\-', '\+', '\`', '\*', '\/', '\_', '\~', '\\\\'],
            ['[', ']', '(', ')', '|', '=', '-', '+', '`', '*', '/', '_', '~', '\\'], $text);
    }

    private function makeTocEntry($tocs)
    {
        $html = "<ul>";
        foreach ($tocs as $toc) {
            $html .= "<li>";
            $name = $toc[0] ?: 'title-' . implode('-', $toc[1]);
            $level = count($toc[1]);
            $indexes = $toc[1];
            $id = 'hn-' . implode('-', $toc[1]);
            $text = $this->makeInline($name, true);
            if (isset($this->cfgHNF[$level])) {
                $text = preg_replace_callback(
                    '/(?<!\\\\)\{H(\d|H)(?<!\\\\)\}/',
                    function ($matches) use ($level, $indexes, $text) {
                        $match = $matches[1];
                        if ($match == 'H') {
                            return $text;
                        } elseif ($match <= $level) {
                            return $indexes[$match - 1] + 1;
                        } else {
                            return $matches[0];
                        }
                    }, $this->cfgHNF[$level]);
            }
            $html .= "<a href='#$id'>$text</a>";
            $html .= "</li>";

            if (isset($toc[2])) {
                $html .= $this->makeTocEntry($toc[2]);
            }
        }
        $html .= "</ul>";
        return $html;
    }

    private function parseList(&$lines, $pattern)
    {
        $position = -1;
        $blocks = [];
        foreach ($lines as $key => $line) {
            if (preg_match($pattern, $line, $matches)) {
                $blocks[++$position] = [$key, $key, $matches];
                $lines[$key] = trim($matches[2]);
            } elseif (preg_match("/^(\t| {{$this->cfgBLK}})(.+)$/", $line, $matches)) {
                $blocks[$position][1] = $key;
                $lines[$key] = $matches[2];
            } else {
                $blocks[$position][1] = $key;
            }
        }

        return $blocks;
    }

    private function makeTableRows($lines, $aligns = null, $head = false)
    {
        $html = '';
        foreach ($lines as $line) {
            $datas = array_map(function ($data) {
                return preg_match("/^\s+$/", $data) ? ' ' : trim($data);
            }, explode('|', substr($line, 1, -1)));

            $columns = [];
            $last = -1;
            foreach ($datas as $data) {
                if (strlen($data) > 0) {
                    $last++;
                    $columns[$last] = [isset($columns[$last]) ? $columns[$last][0] + 1 : 1, $data];
                } else if (isset($columns[$last])) {
                    $columns[$last][0]++;
                } else {
                    $columns[0] = [1, $data];
                }
            }

            $html .= "<tr>";
            foreach ($columns as $key => $column) {
                list ($num, $text) = $column;
                $tag = $head ? 'th' : 'td';
                $html .= "<$tag";
                if ($num > 1) {
                    $html .= " colspan='{$num}' align='center'";
                } elseif (isset($aligns[$key]) && $aligns[$key] != 'none') {
                    $html .= " align='{$aligns[$key]}'";
                }
                $html .= '>' . $this->makeInline($text) . "</{$tag}>";
            }
            $html .= "</tr>";
        }
        return $html;
    }
}
