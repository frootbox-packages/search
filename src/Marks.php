<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Search;

/**
 * Marks the words of a search in a text, e.g. in the excerpt of a hit
 * (decision 0228).
 */
final class Marks
{
    /**
     * The excerpt in parts, those with a word of the search marked, e.g.
     * for <mark>; case does not matter.
     *
     * @return list<array{text: string, mark: bool}>
     */
    public static function parts(string $text, string $q): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $q, flags: \PREG_SPLIT_NO_EMPTY);
        $words = array_filter(false === $words ? [] : $words, static fn(string $word): bool => mb_strlen($word) > 1);

        if ([] === $words || '' === $text) {
            return '' === $text ? [] : [['text' => $text, 'mark' => false]];
        }

        $pattern = '/(' . implode('|', array_map(static fn(string $word): string => preg_quote($word, '/'), $words)) . ')/iu';
        $pieces = preg_split($pattern, $text, flags: \PREG_SPLIT_DELIM_CAPTURE);
        $parts = [];

        foreach (false === $pieces ? [$text] : $pieces as $index => $piece) {
            if ('' !== $piece) {
                $parts[] = ['text' => $piece, 'mark' => 1 === $index % 2];
            }
        }

        return $parts;
    }
}
