<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees;

use function array_slice;
use function count;
use function strlen;

/**
 * Phonetic matching of strings.
 */
class Soundex
{
    // Determine the Daitch–Mokotoff Soundex code for a word
    // Original implementation by Gerry Kroll, and analysis by Meliza Amity

    // Max. table key length (in ASCII bytes -- NOT in UTF-8 characters!)
    private const int MAXCHAR = 7;

    /**
     * Name transformation arrays.
     * Used to transform the Name string to simplify the "sounds like" table.
     * This is especially useful in Hebrew.
     *
     * Each array entry defines the "from" and "to" arguments of an preg($from, $to, $text)
     * function call to achieve the desired transformations.
     *
     * Note about the use of "\x01":
     * This code, which can’t legitimately occur in the kind of text we're dealing with,
     * is used as a place-holder so that conditional string replacements can be done.
     */
    private const array TRANSFORM_NAMES = [
        // Force Yiddish ligatures to be treated as separate letters
        ['װ', 'וו'],
        ['ײ', 'יי'],
        ['ױ', 'וי'],
        ['בו', 'בע'],
        ['פו', 'פע'],
        ['ומ', 'עמ'],
        ['ום', 'עם'],
        ['ונ', 'ענ'],
        ['ון', 'ען'],
        ['וו', 'ב'],
        ["\x01", ''],
        ['ייה$', "\x01ה"],
        ['ייע$', "\x01ע"],
        ['יי', 'ע'],
        ["\x01", 'יי'],
    ];

    /**
     * The DM sound coding table is organized this way:
     * key: a variable-length string that corresponds to the UTF-8 character sequence
     * represented by the table entry. Currently, that string can be up to 7
     * bytes long. This maximum length is defined by the value of global variable
     * $maxchar.
     *
     * value: an array as follows:
     * [0]:  zero if not a vowel
     * [1]:  sound value when this string is at the beginning of the word
     * [2]:  sound value when this string is followed by a vowel
     * [3]:  sound value for other cases
     * [1],[2],[3] can be repeated several times to create branches in the code
     * an empty sound value means "ignore in this state"
     */
    private const array DM_SOUNDS = [
        'A'       => ['1', '0', '', ''],
        'À'       => ['1', '0', '', ''],
        'Á'       => ['1', '0', '', ''],
        'Â'       => ['1', '0', '', ''],
        'Ã'       => ['1', '0', '', ''],
        'Ä'       => ['1', '0', '1', '', '0', '', ''],
        'Å'       => ['1', '0', '', ''],
        'Ă'       => ['1', '0', '', ''],
        'Ą'       => ['1', '', '', '', '', '', '6'],
        'Ạ'       => ['1', '0', '', ''],
        'Ả'       => ['1', '0', '', ''],
        'Ấ'       => ['1', '0', '', ''],
        'Ầ'       => ['1', '0', '', ''],
        'Ẩ'       => ['1', '0', '', ''],
        'Ẫ'       => ['1', '0', '', ''],
        'Ậ'       => ['1', '0', '', ''],
        'Ắ'       => ['1', '0', '', ''],
        'Ằ'       => ['1', '0', '', ''],
        'Ẳ'       => ['1', '0', '', ''],
        'Ẵ'       => ['1', '0', '', ''],
        'Ặ'       => ['1', '0', '', ''],
        'AE'      => ['1', '0', '1', ''],
        'Æ'       => ['1', '0', '1', ''],
        'AI'      => ['1', '0', '1', ''],
        'AJ'      => ['1', '0', '1', ''],
        'AU'      => ['1', '0', '7', ''],
        'AV'      => ['1', '0', '7', '', '7', '7', '7'],
        'ÄU'      => ['1', '0', '1', ''],
        'AY'      => ['1', '0', '1', ''],
        'B'       => ['0', '7', '7', '7'],
        'C'       => ['0', '5', '5', '5', '34', '4', '4'],
        'Ć'       => ['0', '4', '4', '4'],
        'Č'       => ['0', '4', '4', '4'],
        'Ç'       => ['0', '4', '4', '4'],
        'CH'      => ['0', '5', '5', '5', '34', '4', '4'],
        'CHS'     => ['0', '5', '54', '54'],
        'CK'      => ['0', '5', '5', '5', '45', '45', '45'],
        'CCS'     => ['0', '4', '4', '4'],
        'CS'      => ['0', '4', '4', '4'],
        'CSZ'     => ['0', '4', '4', '4'],
        'CZ'      => ['0', '4', '4', '4'],
        'CZS'     => ['0', '4', '4', '4'],
        'D'       => ['0', '3', '3', '3'],
        'Ď'       => ['0', '3', '3', '3'],
        'Đ'       => ['0', '3', '3', '3'],
        'DRS'     => ['0', '4', '4', '4'],
        'DRZ'     => ['0', '4', '4', '4'],
        'DS'      => ['0', '4', '4', '4'],
        'DSH'     => ['0', '4', '4', '4'],
        'DSZ'     => ['0', '4', '4', '4'],
        'DT'      => ['0', '3', '3', '3'],
        'DDZ'     => ['0', '4', '4', '4'],
        'DDZS'    => ['0', '4', '4', '4'],
        'DZ'      => ['0', '4', '4', '4'],
        'DŹ'      => ['0', '4', '4', '4'],
        'DŻ'      => ['0', '4', '4', '4'],
        'DZH'     => ['0', '4', '4', '4'],
        'DZS'     => ['0', '4', '4', '4'],
        'E'       => ['1', '0', '', ''],
        'È'       => ['1', '0', '', ''],
        'É'       => ['1', '0', '', ''],
        'Ê'       => ['1', '0', '', ''],
        'Ë'       => ['1', '0', '', ''],
        'Ĕ'       => ['1', '0', '', ''],
        'Ė'       => ['1', '0', '', ''],
        'Ę'       => ['1', '', '', '6', '', '', ''],
        'Ẹ'       => ['1', '0', '', ''],
        'Ẻ'       => ['1', '0', '', ''],
        'Ẽ'       => ['1', '0', '', ''],
        'Ế'       => ['1', '0', '', ''],
        'Ề'       => ['1', '0', '', ''],
        'Ể'       => ['1', '0', '', ''],
        'Ễ'       => ['1', '0', '', ''],
        'Ệ'       => ['1', '0', '', ''],
        'EAU'     => ['1', '0', '', ''],
        'EI'      => ['1', '0', '1', ''],
        'EJ'      => ['1', '0', '1', ''],
        'EU'      => ['1', '1', '1', ''],
        'EY'      => ['1', '0', '1', ''],
        'F'       => ['0', '7', '7', '7'],
        'FB'      => ['0', '7', '7', '7'],
        'G'       => ['0', '5', '5', '5', '34', '4', '4'],
        'Ğ'       => ['0', '', '', ''],
        'GGY'     => ['0', '5', '5', '5'],
        'GY'      => ['0', '5', '5', '5'],
        'H'       => ['0', '5', '5', '', '5', '5', '5'],
        'I'       => ['1', '0', '', ''],
        'Ì'       => ['1', '0', '', ''],
        'Í'       => ['1', '0', '', ''],
        'Î'       => ['1', '0', '', ''],
        'Ï'       => ['1', '0', '', ''],
        'Ĩ'       => ['1', '0', '', ''],
        'Į'       => ['1', '0', '', ''],
        'İ'       => ['1', '0', '', ''],
        'Ỉ'       => ['1', '0', '', ''],
        'Ị'       => ['1', '0', '', ''],
        'IA'      => ['1', '1', '', ''],
        'IE'      => ['1', '1', '', ''],
        'IO'      => ['1', '1', '', ''],
        'IU'      => ['1', '1', '', ''],
        'J'       => ['0', '1', '', '', '4', '4', '4', '5', '5', ''],
        'K'       => ['0', '5', '5', '5'],
        'KH'      => ['0', '5', '5', '5'],
        'KS'      => ['0', '5', '54', '54'],
        'L'       => ['0', '8', '8', '8'],
        'Ľ'       => ['0', '8', '8', '8'],
        'Ĺ'       => ['0', '8', '8', '8'],
        'Ł'       => ['0', '7', '7', '7', '8', '8', '8'],
        'LL'      => ['0', '8', '8', '8', '58', '8', '8', '1', '8', '8'],
        'LLY'     => ['0', '8', '8', '8', '1', '8', '8'],
        'LY'      => ['0', '8', '8', '8', '1', '8', '8'],
        'M'       => ['0', '6', '6', '6'],
        'MĔ'      => ['0', '66', '66', '66'],
        'MN'      => ['0', '66', '66', '66'],
        'N'       => ['0', '6', '6', '6'],
        'Ń'       => ['0', '6', '6', '6'],
        'Ň'       => ['0', '6', '6', '6'],
        'Ñ'       => ['0', '6', '6', '6'],
        'NM'      => ['0', '66', '66', '66'],
        'O'       => ['1', '0', '', ''],
        'Ò'       => ['1', '0', '', ''],
        'Ó'       => ['1', '0', '', ''],
        'Ô'       => ['1', '0', '', ''],
        'Õ'       => ['1', '0', '', ''],
        'Ö'       => ['1', '0', '', ''],
        'Ø'       => ['1', '0', '', ''],
        'Ő'       => ['1', '0', '', ''],
        'Œ'       => ['1', '0', '', ''],
        'Ơ'       => ['1', '0', '', ''],
        'Ọ'       => ['1', '0', '', ''],
        'Ỏ'       => ['1', '0', '', ''],
        'Ố'       => ['1', '0', '', ''],
        'Ồ'       => ['1', '0', '', ''],
        'Ổ'       => ['1', '0', '', ''],
        'Ỗ'       => ['1', '0', '', ''],
        'Ộ'       => ['1', '0', '', ''],
        'Ớ'       => ['1', '0', '', ''],
        'Ờ'       => ['1', '0', '', ''],
        'Ở'       => ['1', '0', '', ''],
        'Ỡ'       => ['1', '0', '', ''],
        'Ợ'       => ['1', '0', '', ''],
        'OE'      => ['1', '0', '', ''],
        'OI'      => ['1', '0', '1', ''],
        'OJ'      => ['1', '0', '1', ''],
        'OU'      => ['1', '0', '', ''],
        'OY'      => ['1', '0', '1', ''],
        'P'       => ['0', '7', '7', '7'],
        'PF'      => ['0', '7', '7', '7'],
        'PH'      => ['0', '7', '7', '7'],
        'Q'       => ['0', '5', '5', '5'],
        'R'       => ['0', '9', '9', '9'],
        'Ř'       => ['0', '4', '4', '4'],
        'RS'      => ['0', '4', '4', '4', '94', '94', '94'],
        'RZ'      => ['0', '4', '4', '4', '94', '94', '94'],
        'S'       => ['0', '4', '4', '4'],
        'Ś'       => ['0', '4', '4', '4'],
        'Š'       => ['0', '4', '4', '4'],
        'Ş'       => ['0', '4', '4', '4'],
        'SC'      => ['0', '2', '4', '4'],
        'ŠČ'      => ['0', '2', '4', '4'],
        'SCH'     => ['0', '4', '4', '4'],
        'SCHD'    => ['0', '2', '43', '43'],
        'SCHT'    => ['0', '2', '43', '43'],
        'SCHTCH'  => ['0', '2', '4', '4'],
        'SCHTSCH' => ['0', '2', '4', '4'],
        'SCHTSH'  => ['0', '2', '4', '4'],
        'SD'      => ['0', '2', '43', '43'],
        'SH'      => ['0', '4', '4', '4'],
        'SHCH'    => ['0', '2', '4', '4'],
        'SHD'     => ['0', '2', '43', '43'],
        'SHT'     => ['0', '2', '43', '43'],
        'SHTCH'   => ['0', '2', '4', '4'],
        'SHTSH'   => ['0', '2', '4', '4'],
        'ß'       => ['0', '', '4', '4'],
        'ST'      => ['0', '2', '43', '43'],
        'STCH'    => ['0', '2', '4', '4'],
        'STRS'    => ['0', '2', '4', '4'],
        'STRZ'    => ['0', '2', '4', '4'],
        'STSCH'   => ['0', '2', '4', '4'],
        'STSH'    => ['0', '2', '4', '4'],
        'SSZ'     => ['0', '4', '4', '4'],
        'SZ'      => ['0', '4', '4', '4'],
        'SZCS'    => ['0', '2', '4', '4'],
        'SZCZ'    => ['0', '2', '4', '4'],
        'SZD'     => ['0', '2', '43', '43'],
        'SZT'     => ['0', '2', '43', '43'],
        'T'       => ['0', '3', '3', '3'],
        'Ť'       => ['0', '3', '3', '3'],
        'Ţ'       => ['0', '3', '3', '3', '4', '4', '4'],
        'TC'      => ['0', '4', '4', '4'],
        'TCH'     => ['0', '4', '4', '4'],
        'TH'      => ['0', '3', '3', '3'],
        'TRS'     => ['0', '4', '4', '4'],
        'TRZ'     => ['0', '4', '4', '4'],
        'TS'      => ['0', '4', '4', '4'],
        'TSCH'    => ['0', '4', '4', '4'],
        'TSH'     => ['0', '4', '4', '4'],
        'TSZ'     => ['0', '4', '4', '4'],
        'TTCH'    => ['0', '4', '4', '4'],
        'TTS'     => ['0', '4', '4', '4'],
        'TTSCH'   => ['0', '4', '4', '4'],
        'TTSZ'    => ['0', '4', '4', '4'],
        'TTZ'     => ['0', '4', '4', '4'],
        'TZ'      => ['0', '4', '4', '4'],
        'TZS'     => ['0', '4', '4', '4'],
        'U'       => ['1', '0', '', ''],
        'Ù'       => ['1', '0', '', ''],
        'Ú'       => ['1', '0', '', ''],
        'Û'       => ['1', '0', '', ''],
        'Ü'       => ['1', '0', '', ''],
        'Ũ'       => ['1', '0', '', ''],
        'Ū'       => ['1', '0', '', ''],
        'Ů'       => ['1', '0', '', ''],
        'Ű'       => ['1', '0', '', ''],
        'Ų'       => ['1', '0', '', ''],
        'Ư'       => ['1', '0', '', ''],
        'Ụ'       => ['1', '0', '', ''],
        'Ủ'       => ['1', '0', '', ''],
        'Ứ'       => ['1', '0', '', ''],
        'Ừ'       => ['1', '0', '', ''],
        'Ử'       => ['1', '0', '', ''],
        'Ữ'       => ['1', '0', '', ''],
        'Ự'       => ['1', '0', '', ''],
        'UE'      => ['1', '0', '', ''],
        'UI'      => ['1', '0', '1', ''],
        'UJ'      => ['1', '0', '1', ''],
        'UY'      => ['1', '0', '1', ''],
        'UW'      => ['1', '0', '1', '', '0', '7', '7'],
        'V'       => ['0', '7', '7', '7'],
        'W'       => ['0', '7', '7', '7'],
        'X'       => ['0', '5', '54', '54'],
        'Y'       => ['1', '1', '', ''],
        'Ý'       => ['1', '1', '', ''],
        'Ỳ'       => ['1', '1', '', ''],
        'Ỵ'       => ['1', '1', '', ''],
        'Ỷ'       => ['1', '1', '', ''],
        'Ỹ'       => ['1', '1', '', ''],
        'Z'       => ['0', '4', '4', '4'],
        'Ź'       => ['0', '4', '4', '4'],
        'Ż'       => ['0', '4', '4', '4'],
        'Ž'       => ['0', '4', '4', '4'],
        'ZD'      => ['0', '2', '43', '43'],
        'ZDZ'     => ['0', '2', '4', '4'],
        'ZDZH'    => ['0', '2', '4', '4'],
        'ZH'      => ['0', '4', '4', '4'],
        'ZHD'     => ['0', '2', '43', '43'],
        'ZHDZH'   => ['0', '2', '4', '4'],
        'ZS'      => ['0', '4', '4', '4'],
        'ZSCH'    => ['0', '4', '4', '4'],
        'ZSH'     => ['0', '4', '4', '4'],
        'ZZS'     => ['0', '4', '4', '4'],
        // Cyrillic alphabet
        'А'       => ['1', '0', '', ''],
        'Б'       => ['0', '7', '7', '7'],
        'В'       => ['0', '7', '7', '7'],
        'Г'       => ['0', '5', '5', '5'],
        'Д'       => ['0', '3', '3', '3'],
        'ДЗ'      => ['0', '4', '4', '4'],
        'Е'       => ['1', '0', '', ''],
        'Ё'       => ['1', '0', '', ''],
        'Ж'       => ['0', '4', '4', '4'],
        'З'       => ['0', '4', '4', '4'],
        'И'       => ['1', '0', '', ''],
        'Й'       => ['1', '1', '', '', '4', '4', '4'],
        'К'       => ['0', '5', '5', '5'],
        'Л'       => ['0', '8', '8', '8'],
        'М'       => ['0', '6', '6', '6'],
        'Н'       => ['0', '6', '6', '6'],
        'О'       => ['1', '0', '', ''],
        'П'       => ['0', '7', '7', '7'],
        'Р'       => ['0', '9', '9', '9'],
        'РЖ'      => ['0', '4', '4', '4'],
        'С'       => ['0', '4', '4', '4'],
        'Т'       => ['0', '3', '3', '3'],
        'У'       => ['1', '0', '', ''],
        'Ф'       => ['0', '7', '7', '7'],
        'Х'       => ['0', '5', '5', '5'],
        'Ц'       => ['0', '4', '4', '4'],
        'Ч'       => ['0', '4', '4', '4'],
        'Ш'       => ['0', '4', '4', '4'],
        'Щ'       => ['0', '2', '4', '4'],
        'Ъ'       => ['0', '', '', ''],
        'Ы'       => ['0', '1', '', ''],
        'Ь'       => ['0', '', '', ''],
        'Э'       => ['1', '0', '', ''],
        'Ю'       => ['0', '1', '', ''],
        'Я'       => ['0', '1', '', ''],
        // Greek alphabet
        'Α'       => ['1', '0', '', ''],
        'Ά'       => ['1', '0', '', ''],
        'ΑΙ'      => ['1', '0', '1', ''],
        'ΑΥ'      => ['1', '0', '1', ''],
        'Β'       => ['0', '7', '7', '7'],
        'Γ'       => ['0', '5', '5', '5'],
        'Δ'       => ['0', '3', '3', '3'],
        'Ε'       => ['1', '0', '', ''],
        'Έ'       => ['1', '0', '', ''],
        'ΕΙ'      => ['1', '0', '1', ''],
        'ΕΥ'      => ['1', '1', '1', ''],
        'Ζ'       => ['0', '4', '4', '4'],
        'Η'       => ['1', '0', '', ''],
        'Ή'       => ['1', '0', '', ''],
        'Θ'       => ['0', '3', '3', '3'],
        'Ι'       => ['1', '0', '', ''],
        'Ί'       => ['1', '0', '', ''],
        'Ϊ'       => ['1', '0', '', ''],
        'ΐ'       => ['1', '0', '', ''],
        'Κ'       => ['0', '5', '5', '5'],
        'Λ'       => ['0', '8', '8', '8'],
        'Μ'       => ['0', '6', '6', '6'],
        'ΜΠ'      => ['0', '7', '7', '7'],
        'Ν'       => ['0', '6', '6', '6'],
        'ΝΤ'      => ['0', '3', '3', '3'],
        'Ξ'       => ['0', '5', '54', '54'],
        'Ο'       => ['1', '0', '', ''],
        'Ό'       => ['1', '0', '', ''],
        'ΟΙ'      => ['1', '0', '1', ''],
        'ΟΥ'      => ['1', '0', '1', ''],
        'Π'       => ['0', '7', '7', '7'],
        'Ρ'       => ['0', '9', '9', '9'],
        'Σ'       => ['0', '4', '4', '4'],
        'ς'       => ['0', '', '', '4'],
        'Τ'       => ['0', '3', '3', '3'],
        'ΤΖ'      => ['0', '4', '4', '4'],
        'ΤΣ'      => ['0', '4', '4', '4'],
        'Υ'       => ['1', '1', '', ''],
        'Ύ'       => ['1', '1', '', ''],
        'Ϋ'       => ['1', '1', '', ''],
        'ΰ'       => ['1', '1', '', ''],
        'ΥΚ'      => ['1', '5', '5', '5'],
        'ΥΥ'      => ['1', '65', '65', '65'],
        'Φ'       => ['0', '7', '7', '7'],
        'Χ'       => ['0', '5', '5', '5'],
        'Ψ'       => ['0', '7', '7', '7'],
        'Ω'       => ['1', '0', '', ''],
        'Ώ'       => ['1', '0', '', ''],
        // Hebrew alphabet
        'א'       => ['1', '0', '', ''],
        'או'      => ['1', '0', '7', ''],
        'אג'      => ['1', '4', '4', '4', '5', '5', '5', '34', '34', '34'],
        'בב'      => ['0', '7', '7', '7', '77', '77', '77'],
        'ב'       => ['0', '7', '7', '7'],
        'גג'      => ['0', '4', '4', '4', '5', '5', '5', '45', '45', '45', '55', '55', '55', '54', '54', '54'],
        'גד'      => ['0', '43', '43', '43', '53', '53', '53'],
        'גה'      => ['0', '45', '45', '45', '55', '55', '55'],
        'גז'      => ['0', '44', '44', '44', '45', '45', '45'],
        'גח'      => ['0', '45', '45', '45', '55', '55', '55'],
        'גכ'      => ['0', '45', '45', '45', '55', '55', '55'],
        'גך'      => ['0', '45', '45', '45', '55', '55', '55'],
        'גצ'      => ['0', '44', '44', '44', '45', '45', '45'],
        'גץ'      => ['0', '44', '44', '44', '45', '45', '45'],
        'גק'      => ['0', '45', '45', '45', '54', '54', '54'],
        'גש'      => ['0', '44', '44', '44', '54', '54', '54'],
        'גת'      => ['0', '43', '43', '43', '53', '53', '53'],
        'ג'       => ['0', '4', '4', '4', '5', '5', '5'],
        'דז'      => ['0', '4', '4', '4'],
        'דד'      => ['0', '3', '3', '3', '33', '33', '33'],
        'דט'      => ['0', '33', '33', '33'],
        'דש'      => ['0', '4', '4', '4'],
        'דצ'      => ['0', '4', '4', '4'],
        'דץ'      => ['0', '4', '4', '4'],
        'ד'       => ['0', '3', '3', '3'],
        'הג'      => ['0', '54', '54', '54', '55', '55', '55'],
        'הכ'      => ['0', '55', '55', '55'],
        'הח'      => ['0', '55', '55', '55'],
        'הק'      => ['0', '55', '55', '55', '5', '5', '5'],
        'הה'      => ['0', '5', '5', '', '55', '55', ''],
        'ה'       => ['0', '5', '5', ''],
        'וי'      => ['1', '', '', '', '7', '7', '7'],
        'ו'       => ['1', '7', '7', '7', '7', '', ''],
        'וו'      => ['1', '7', '7', '7', '7', '', ''],
        'וופ'     => ['1', '7', '7', '7', '77', '77', '77'],
        'זש'      => ['0', '4', '4', '4', '44', '44', '44'],
        'זדז'     => ['0', '2', '4', '4'],
        'ז'       => ['0', '4', '4', '4'],
        'זג'      => ['0', '44', '44', '44', '45', '45', '45'],
        'זז'      => ['0', '4', '4', '4', '44', '44', '44'],
        'זס'      => ['0', '44', '44', '44'],
        'זצ'      => ['0', '44', '44', '44'],
        'זץ'      => ['0', '44', '44', '44'],
        'חג'      => ['0', '54', '54', '54', '53', '53', '53'],
        'חח'      => ['0', '5', '5', '5', '55', '55', '55'],
        'חק'      => ['0', '55', '55', '55', '5', '5', '5'],
        'חכ'      => ['0', '45', '45', '45', '55', '55', '55'],
        'חס'      => ['0', '5', '54', '54'],
        'חש'      => ['0', '5', '54', '54'],
        'ח'       => ['0', '5', '5', '5'],
        'טש'      => ['0', '4', '4', '4'],
        'טד'      => ['0', '33', '33', '33'],
        'טי'      => ['0', '3', '3', '3', '4', '4', '4', '3', '3', '34'],
        'טת'      => ['0', '33', '33', '33'],
        'טט'      => ['0', '3', '3', '3', '33', '33', '33'],
        'ט'       => ['0', '3', '3', '3'],
        'י'       => ['1', '1', '', ''],
        'יא'      => ['1', '1', '', '', '1', '1', '1'],
        'כג'      => ['0', '55', '55', '55', '54', '54', '54'],
        'כש'      => ['0', '5', '54', '54'],
        'כס'      => ['0', '5', '54', '54'],
        'ככ'      => ['0', '5', '5', '5', '55', '55', '55'],
        'כך'      => ['0', '5', '5', '5', '55', '55', '55'],
        'כ'       => ['0', '5', '5', '5'],
        'כח'      => ['0', '55', '55', '55', '5', '5', '5'],
        'ך'       => ['0', '', '5', '5'],
        'ל'       => ['0', '8', '8', '8'],
        'לל'      => ['0', '88', '88', '88', '8', '8', '8'],
        'מנ'      => ['0', '66', '66', '66'],
        'מן'      => ['0', '66', '66', '66'],
        'ממ'      => ['0', '6', '6', '6', '66', '66', '66'],
        'מם'      => ['0', '6', '6', '6', '66', '66', '66'],
        'מ'       => ['0', '6', '6', '6'],
        'ם'       => ['0', '', '6', '6'],
        'נמ'      => ['0', '66', '66', '66'],
        'נם'      => ['0', '66', '66', '66'],
        'ננ'      => ['0', '6', '6', '6', '66', '66', '66'],
        'נן'      => ['0', '6', '6', '6', '66', '66', '66'],
        'נ'       => ['0', '6', '6', '6'],
        'ן'       => ['0', '', '6', '6'],
        'סתש'     => ['0', '2', '4', '4'],
        'סתז'     => ['0', '2', '4', '4'],
        'סטז'     => ['0', '2', '4', '4'],
        'סטש'     => ['0', '2', '4', '4'],
        'סצד'     => ['0', '2', '4', '4'],
        'סט'      => ['0', '2', '4', '4', '43', '43', '43'],
        'סת'      => ['0', '2', '4', '4', '43', '43', '43'],
        'סג'      => ['0', '44', '44', '44', '4', '4', '4'],
        'סס'      => ['0', '4', '4', '4', '44', '44', '44'],
        'סצ'      => ['0', '44', '44', '44'],
        'סץ'      => ['0', '44', '44', '44'],
        'סז'      => ['0', '44', '44', '44'],
        'סש'      => ['0', '44', '44', '44'],
        'ס'       => ['0', '4', '4', '4'],
        'ע'       => ['1', '0', '', ''],
        'פב'      => ['0', '7', '7', '7', '77', '77', '77'],
        'פוו'     => ['0', '7', '7', '7', '77', '77', '77'],
        'פפ'      => ['0', '7', '7', '7', '77', '77', '77'],
        'פף'      => ['0', '7', '7', '7', '77', '77', '77'],
        'פ'       => ['0', '7', '7', '7'],
        'ף'       => ['0', '', '7', '7'],
        'צג'      => ['0', '44', '44', '44', '45', '45', '45'],
        'צז'      => ['0', '44', '44', '44'],
        'צס'      => ['0', '44', '44', '44'],
        'צצ'      => ['0', '4', '4', '4', '5', '5', '5', '44', '44', '44', '54', '54', '54', '45', '45', '45'],
        'צץ'      => ['0', '4', '4', '4', '5', '5', '5', '44', '44', '44', '54', '54', '54'],
        'צש'      => ['0', '44', '44', '44', '4', '4', '4', '5', '5', '5'],
        'צ'       => ['0', '4', '4', '4', '5', '5', '5'],
        'ץ'       => ['0', '', '4', '4'],
        'קה'      => ['0', '55', '55', '5'],
        'קס'      => ['0', '5', '54', '54'],
        'קש'      => ['0', '5', '54', '54'],
        'קק'      => ['0', '5', '5', '5', '55', '55', '55'],
        'קח'      => ['0', '55', '55', '55'],
        'קכ'      => ['0', '55', '55', '55'],
        'קך'      => ['0', '55', '55', '55'],
        'קג'      => ['0', '55', '55', '55', '54', '54', '54'],
        'ק'       => ['0', '5', '5', '5'],
        'רר'      => ['0', '99', '99', '99', '9', '9', '9'],
        'ר'       => ['0', '9', '9', '9'],
        'שטז'     => ['0', '2', '4', '4'],
        'שתש'     => ['0', '2', '4', '4'],
        'שתז'     => ['0', '2', '4', '4'],
        'שטש'     => ['0', '2', '4', '4'],
        'שד'      => ['0', '2', '43', '43'],
        'שז'      => ['0', '44', '44', '44'],
        'שס'      => ['0', '44', '44', '44'],
        'שת'      => ['0', '2', '43', '43'],
        'שג'      => ['0', '4', '4', '4', '44', '44', '44', '4', '43', '43'],
        'שט'      => ['0', '2', '43', '43', '44', '44', '44'],
        'שצ'      => ['0', '44', '44', '44', '45', '45', '45'],
        'שץ'      => ['0', '44', '', '44', '45', '', '45'],
        'שש'      => ['0', '4', '4', '4', '44', '44', '44'],
        'ש'       => ['0', '4', '4', '4'],
        'תג'      => ['0', '34', '34', '34'],
        'תז'      => ['0', '34', '34', '34'],
        'תש'      => ['0', '4', '4', '4'],
        'תת'      => ['0', '3', '3', '3', '4', '4', '4', '33', '33', '33', '44', '44', '44', '34', '34', '34', '43', '43', '43'],
        'ת'       => ['0', '3', '3', '3', '4', '4', '4'],
        // Arabic alphabet
        'ا'       => ['1', '0', '', ''],
        'ب'       => ['0', '7', '7', '7'],
        'ت'       => ['0', '3', '3', '3'],
        'ث'       => ['0', '3', '3', '3'],
        'ج'       => ['0', '4', '4', '4'],
        'ح'       => ['0', '5', '5', '5'],
        'خ'       => ['0', '5', '5', '5'],
        'د'       => ['0', '3', '3', '3'],
        'ذ'       => ['0', '3', '3', '3'],
        'ر'       => ['0', '9', '9', '9'],
        'ز'       => ['0', '4', '4', '4'],
        'س'       => ['0', '4', '4', '4'],
        'ش'       => ['0', '4', '4', '4'],
        'ص'       => ['0', '4', '4', '4'],
        'ض'       => ['0', '3', '3', '3'],
        'ط'       => ['0', '3', '3', '3'],
        'ظ'       => ['0', '4', '4', '4'],
        'ع'       => ['1', '0', '', ''],
        'غ'       => ['0', '0', '', ''],
        'ف'       => ['0', '7', '7', '7'],
        'ق'       => ['0', '5', '5', '5'],
        'ك'       => ['0', '5', '5', '5'],
        'ل'       => ['0', '8', '8', '8'],
        'لا'      => ['0', '8', '8', '8'],
        'م'       => ['0', '6', '6', '6'],
        'ن'       => ['0', '6', '6', '6'],
        'هن'      => ['0', '66', '66', '66'],
        'ه'       => ['0', '5', '5', ''],
        'و'       => ['1', '', '', '', '7', '', ''],
        'ي'       => ['0', '1', '', ''],
        'آ'       => ['0', '1', '', ''],
        'ة'       => ['0', '', '', '3'],
        'ی'       => ['0', '1', '', ''],
        'ى'       => ['1', '1', '', ''],
    ];

    /**
     * Which algorithms are supported.
     *
     * @return array<string>
     */
    public static function getAlgorithms(): array
    {
        return [
            /* I18N: https://en.wikipedia.org/wiki/Soundex */
            'std' => I18N::translate('Russell'),
            /* I18N: https://en.wikipedia.org/wiki/Daitch–Mokotoff_Soundex */
            'dm'  => I18N::translate('Daitch-Mokotoff'),
        ];
    }

    /**
     * Is there a match between two soundex codes?
     *
     * @param string $soundex1
     * @param string $soundex2
     *
     * @return bool
     */
    public static function compare(string $soundex1, string $soundex2): bool
    {
        if ($soundex1 !== '' && $soundex2 !== '') {
            return array_intersect(explode(':', $soundex1), explode(':', $soundex2)) !== [];
        }

        return false;
    }

    /**
     * Generate Russell soundex codes for a given text.
     *
     * @param string $text
     *
     * @return string
     */
    public static function russell(string $text): string
    {
        $words         = explode(' ', $text);
        $soundex_array = [];

        foreach ($words as $word) {
            $soundex = soundex($word);

            // Only return codes from recognisable sounds
            if ($soundex !== '0000') {
                $soundex_array[] = $soundex;
            }
        }

        // Combine words, e.g. “New York” as “Newyork”
        if (count($words) > 1) {
            $soundex_array[] = soundex(str_replace(' ', '', $text));
        }

        // A varchar(255) column can only hold 51 4-character codes (plus 50 delimiters)
        $soundex_array = array_slice(array_unique($soundex_array), 0, 51);

        return implode(':', $soundex_array);
    }

    /**
     * Generate Daitch–Mokotoff soundex codes for a given text.
     *
     * @param string $text
     *
     * @return string
     */
    public static function daitchMokotoff(string $text): string
    {
        $words         = explode(' ', $text);
        $soundex_array = [];

        foreach ($words as $word) {
            $soundex_array = array_merge($soundex_array, self::daitchMokotoffWord($word));
        }
        // Combine words, e.g. “New York” as “Newyork”
        if (count($words) > 1) {
            $soundex_array = array_merge($soundex_array, self::daitchMokotoffWord(str_replace(' ', '', $text)));
        }

        // A varchar(255) column can only hold 36 6-character codes (plus 35 delimiters)
        $soundex_array = array_slice(array_unique($soundex_array), 0, 36);

        return implode(':', $soundex_array);
    }

    /**
     * Calculate the Daitch-Mokotoff soundex for a word.
     *
     * @param string $name
     *
     * @return array<string> List of possible DM codes for the word.
     */
    private static function daitchMokotoffWord(string $name): array
    {
        // Apply special transformation rules to the input string
        $name = I18N::strtoupper($name);
        foreach (self::TRANSFORM_NAMES as $transformRule) {
            $name = str_replace($transformRule[0], $transformRule[1], $name);
        }

        // Initialize
        $name_script = I18N::textScript($name);
        $noVowels    = $name_script === 'Hebr' || $name_script === 'Arab';

        $lastPos         = strlen($name) - 1;
        $currPos         = 0;
        $state           = 1; // 1: start of input string, 2: before vowel, 3: other
        $result          = []; // accumulate complete 6-digit D-M codes here
        $partialResult   = []; // accumulate incomplete D-M codes here
        $partialResult[] = ['!']; // initialize 1st partial result  ('!' stops "duplicate sound" check)

        // Loop through the input string.
        // Stop when the string is exhausted or when no more partial results remain
        while ($partialResult !== [] && $currPos <= $lastPos) {
            // Find the DM coding table entry for the chunk at the current position
            $thisEntry = substr($name, $currPos, self::MAXCHAR); // Get maximum length chunk
            while ($thisEntry !== '') {
                if (isset(self::DM_SOUNDS[$thisEntry])) {
                    break;
                }
                $thisEntry = substr($thisEntry, 0, -1); // Not in table: try a shorter chunk
            }
            if ($thisEntry === '') {
                $currPos++; // Not in table: advance pointer to next byte
                continue; // and try again
            }

            $soundTableEntry = self::DM_SOUNDS[$thisEntry];
            $workingResult   = $partialResult;
            $partialResult   = [];
            $currPos += strlen($thisEntry);

            // Not at beginning of input string
            if ($state !== 1) {
                if ($currPos <= $lastPos) {
                    // Determine whether the next chunk is a vowel
                    $nextEntry = substr($name, $currPos, self::MAXCHAR); // Get maximum length chunk
                    while ($nextEntry !== '') {
                        if (isset(self::DM_SOUNDS[$nextEntry])) {
                            break;
                        }
                        $nextEntry = substr($nextEntry, 0, -1); // Not in table: try a shorter chunk
                    }
                } else {
                    $nextEntry = '';
                }
                if ($nextEntry !== '' && self::DM_SOUNDS[$nextEntry][0] !== '0') {
                    $state = 2;
                } else {
                    // Next chunk is a vowel
                    $state = 3;
                }
            }

            while ($state < count($soundTableEntry)) {
                // empty means 'ignore this sound in this state'
                if ($soundTableEntry[$state] === '') {
                    foreach ($workingResult as $workingEntry) {
                        $tempEntry                        = $workingEntry;
                        $tempEntry[count($tempEntry) - 1] .= '!'; // Prevent false 'doubles'
                        $partialResult[]                  = $tempEntry;
                    }
                } else {
                    foreach ($workingResult as $workingEntry) {
                        if ($soundTableEntry[$state] !== $workingEntry[count($workingEntry) - 1]) {
                            // Incoming sound isn't a duplicate of the previous sound
                            $workingEntry[] = $soundTableEntry[$state];
                        } elseif ($noVowels) {
                            // Incoming sound is a duplicate of the previous sound
                            // For Hebrew and Arabic, we need to create a pair of D-M sound codes,
                            // one of the pair with only a single occurrence of the duplicate sound,
                            // the other with both occurrences
                            $workingEntry[] = $soundTableEntry[$state];
                        }

                        if (count($workingEntry) < 7) {
                            $partialResult[] = $workingEntry;
                        } else {
                            // This is the 6th code in the sequence
                            // We're looking for 7 entries because the first is '!' and doesn't count
                            $tempResult = str_replace('!', '', implode('', $workingEntry));
                            // Only return codes from recognisable sounds
                            if ($tempResult !== '') {
                                $result[] = substr($tempResult . '000000', 0, 6);
                            }
                        }
                    }
                }
                $state += 3; // Advance to next triplet while keeping the same basic state
            }
        }

        // Zero-fill and copy all remaining partial results
        foreach ($partialResult as $workingEntry) {
            $tempResult = str_replace('!', '', implode('', $workingEntry));
            // Only return codes from recognisable sounds
            if ($tempResult !== '') {
                $result[] = substr($tempResult . '000000', 0, 6);
            }
        }

        return $result;
    }
}
