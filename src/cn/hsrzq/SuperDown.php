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
        return '';
    }

    private function escapeSymbol($text)
    {
        return str_replace(
            ['\[', '\]', '\(', '\)', '\|', '\=', '\-', '\+', '\`', '\*', '\/', '\_', '\~', '\\\\'],
            ['[', ']', '(', ')', '|', '=', '-', '+', '`', '*', '/', '_', '~', '\\'], $text);
    }
}
