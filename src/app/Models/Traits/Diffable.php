<?php

namespace App\Models\Traits;

trait Diffable
{
    /**
     * 変更差分を配列で返す（dirty のみ）
     */
    public function diff(): array
    {
        $dirty = $this->getDirty();
        $original = $this->getOriginal();

        $diff = [];
        foreach ($dirty as $k => $v) {
            $diff[$k] = ['old' => $original[$k] ?? null, 'new' => $v];
        }
        return $diff;
    }
}
