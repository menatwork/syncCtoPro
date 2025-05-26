<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * @package Diff
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

if(file_exists(TL_ROOT . '/system/modules/core/vendor/phpdiff/Diff/Renderer/Html/Array.php')) {
    require_once TL_ROOT . '/system/modules/core/vendor/phpdiff/Diff/Renderer/Html/Array.php';
} elseif(file_exists(TL_ROOT . '/vendor/phpspec/php-diff/lib/Diff/Renderer/Html/Array.php')) {
    require_once TL_ROOT . '/vendor/phpspec/php-diff/lib/Diff/Renderer/Html/Array.php';
}else {
    throw new \RuntimeException('Could not find the Renderer Class.');
}


class Diff_Renderer_Html_Contao extends Diff_Renderer_Html_Array
{

    /**
     * Render a and return diff with changes between the two sequences
     * displayed inline (under each other)
     *
     * @return string The generated inline diff.
     */
    public function render()
    {
        $changes = parent::render();

        if (empty($changes))
        {
            return '';
        }

        $html = "\n" . '<div class="change">';

        // Add the field name
        if (isset($this->options['field']))
        {
            $html .= "\n<h2>" . $this->options['field'] . '</h2>';
        }

        $html .= "\n<table class=\"diffview\">";
        $html .= "\n<colgroup>";
        $html .= "\n<col width=\"50%\" />";
        $html .= "\n<col width=\"50%\" />";
        $html .= "\n</colgroup>";

        foreach ($changes as $i => $blocks)
        {
            // If this is a separate block, we're condensing code so output …,
            // indicating a significant portion of the code has been collapsed
            if ($i > 0)
            {
                $html .= '<tr><td class="skipped">…</td></tr>';
            }

            foreach ($blocks as $change)
            {
                $html .= "\n<tr valign=\"top\">";

                // Equal changes should be shown on both sides of the diff
                if ($change['tag'] == 'equal')
                {
                    foreach ($change['base']['lines'] as $line)
                    {
                        $html .= "\n  " . '<td> class="' . $change['tag'] . ' left">' . ($line ? : '&nbsp') . '</td>';
                    }
                }
                // Added lines only on the right side
                elseif ($change['tag'] == 'insert')
                {
                    foreach ($change['changed']['lines'] as $line)
                    {
                        $html .= "\n " . '<td class="' . $change['tag'] . ' right"><ins>' . ($line ? : '&nbsp') . '</ins></td>';
                    }
                }
                // Show deleted lines only on the left side
                elseif ($change['tag'] == 'delete')
                {
                    foreach ($change['base']['lines'] as $line)
                    {
                        $html .= "\n  " . '<td class="' . $change['tag'] . ' left"><del>' . ($line ? : '&nbsp') . '</del></td>';
                    }
                }
                // Show modified lines on both sides
                elseif ($change['tag'] == 'replace')
                {
                    foreach ($change['changed']['lines'] as $line)
                    {
                        $html .= "\n  " . '<td class="' . $change['tag'] . ' right"><span>' . ($line ? : '&nbsp') . '</span></td>';
                    }
                    foreach ($change['base']['lines'] as $line)
                    {
                        $html .= "\n  " . '<td class="' . $change['tag'] . ' left"><span>' . ($line ? : '&nbsp') . '</span></td>';
                    }
                }

                $html .= "\n</tr>";
            }
        }

        $html .= "\n</table>\n</div>\n";
        return $html;
    }

}
