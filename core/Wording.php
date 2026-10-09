<?php

/**
 * Wording
 * What people SEE says "staff" and "cashback"; the code, database and
 * routes still use worker/tip internally. This cleans text that was stored
 * before the wording changed (old audit-log rows) at display time.
 */
class Wording
{
    /** Free text: descriptions, user labels. Keeps capital letters. */
    public static function visible(?string $text): string
    {
        $text = (string) $text;
        $rules = [
            '/\((worker)\)/i'                           => 'staff',
            "/(?<![A-Za-z])worker's(?![A-Za-z])/i"       => "staff member's",
            '/(?<![A-Za-z])workers(?![A-Za-z])/i'        => 'staff',
            '/(?<![A-Za-z])worker(?![A-Za-z])/i'         => 'staff member',
            '/(?<![A-Za-z])tips?(?![A-Za-z])/i'          => 'cashback',
        ];
        foreach ($rules as $pattern => $replacement) {
            $text = preg_replace_callback($pattern, function ($m) use ($replacement, $pattern) {
                $out = $pattern === '/\((worker)\)/i' ? '(staff)' : $replacement;
                return ctype_upper(substr($m[0], 0, 1)) ? ucfirst($out) : $out;
            }, $text);
        }
        return $text;
    }

    /** Action codes like worker_accept_record -> staff_accept_record. */
    public static function action(?string $code): string
    {
        return preg_replace(
            ['/(?<![a-z])worker(?![a-z])/i', '/(?<![a-z])tips?(?![a-z])/i'],
            ['staff', 'cashback'],
            (string) $code
        );
    }
}
