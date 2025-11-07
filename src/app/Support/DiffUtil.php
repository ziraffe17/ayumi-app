<?php
namespace App\Support;

final class DiffUtil
{
    /**
     * @param array $before
     * @param array $after
     * @param array $maskKeys  例: ['password','remember_token','care_notes_enc']
     * @return array           変更のあったキーのみ {key: [before, after]}
     */
    public static function diff(array $before, array $after, array $maskKeys = []): array
    {
        $out = [];
        $all = array_unique(array_merge(array_keys($before), array_keys($after)));
        foreach ($all as $k) {
            if (in_array($k, $maskKeys, true)) continue;
            $b = $before[$k] ?? null;
            $a = $after[$k] ?? null;
            if ($b !== $a) {
                $out[$k] = [$b, $a];
            }
        }
        return $out;
    }
}
